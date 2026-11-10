# 事件总线、任务与投递

> 状态：2026-11-11 消费策略收敛实施契约。当前工作树已接入持久模式、领取门禁及定时入口；真实数据库、升级和 dev 验收结果以最终测试报告为准。下文保留迁移前基线以解释删除条件，不将其视为现行行为。

LoveCards 的系统级事件能力是一套小型、可测试的领域事件协议，不是进程内“万能广播器”，也不要求部署 RabbitMQ。它只解决跨模块事实传播和需要重试的副作用；同一模块内的直接调用仍应保持直接。

## 边界

```text
Publisher
  -> EventEnvelope
  -> EventRecorder
  -> outbox_events + event_deliveries（与业务写入同一事务）
  -> 定时一次性消费或常驻 WorkerLoop（二选一的领取策略）
  -> HandlerRegistry -> EventHandler
```

`DomainEventBus` 提供模块发布入口；`HandlerRegistry` 显式注册 `event_type + event_version + handler_key + queue + execution_mode`。Handler 不能通过扫描目录或反射隐式出现，因为 Release 的 Handler Catalog 必须可签名、可比较。

适合使用事件的场景：关注后创建通知、私信后通知收件人、文件清理、外部发送器投递、审计旁路和后续可重试处理。不适合使用事件的场景：同一请求必须立即得到结果的校验、权限判断、余额/计数等强一致核心写入。

## EventEnvelope

事件至少包含：

- `event_type`、`event_version`；
- `aggregate_type`、`aggregate_key`；
- `actor_id`；
- `correlation_id`、可选 `causation_id`；
- JSON `payload`、`occurred_at`；
- 创建它的 `created_release_id`。

事件是已经发生的事实，命名使用过去式，例如 `social.followed`、`message.sent`、`file.purge.requested`。发布后不得修改 payload；破坏性结构变化必须增加 event version 和兼容 Handler。

## 可靠投递状态机

`outbox_events` 保存事实；`event_deliveries` 为每个 Handler 保存独立投递。`UNIQUE(event_id, handler_key)` 防止重复建投递，队列复合索引支持按 mode/status/available_at/id 领取。

```text
pending -> claimed -> delivered
              |
              +-> retry（available_at 退避）
              +-> dead-letter（超过最大次数）
```

claim 使用随机 token、worker id 与 lease expiry；完成或失败更新必须匹配 claim token。Handler 仍须以 `DeliveryContext::idempotencyKey()` 或业务唯一键幂等，因为“副作用成功但进程在 checkpoint 前退出”会导致至少一次重放。

错误记录只保存经过 `FailureSanitizer` 清理的稳定摘要，不保存 Token、凭证或完整个人数据。管理员可在 `/apps/operations/deliveries` 查看投递、筛选失败项并填写原因重试；手工重试需要 `operations.retry` 并写审计。

## 事件处理运行策略

管理员为站点选择 `scheduled`（定时消费，默认）或 `resident`（常驻消费）。两者共用 MySQL Outbox、`event_deliveries`、Handler Registry、领取租约、重试与幂等键；区别只在消费进程何时启动。`request_safe` 与 `daemon_required` 是现有 Handler 对执行环境的声明，**不是**站点运行策略。定时消费者运行于独立 CLI/受认证的一次性 HTTP 请求，不在用户请求内顺带处理任务；两种策略都可处理已注册队列中的相应任务，Handler 不得依赖用户 HTTP 请求上下文。

选择的策略必须持久化。每次领取任务要在同一个 MySQL 事务内读取并锁定该策略，确认调用者的策略匹配且未暂停领取，再锁定并领取投递；仅靠管理端选项、进程配置或心跳不足以防止两个消费者同时工作。`scheduled` 与 `resident` 不自动代班；常驻 Worker 故障不会偷偷启动定时消费者。租约到期后只有当前选定的策略可以重新领取，Handler 保持至少一次执行和幂等要求。

切换策略时，控制面先暂停新的领取，等待已领取任务完成或租约结算，确认没有在途领取后再保存目标策略并恢复领取。等待超时或持久化失败时保持原策略或明确的暂停状态并报告失败，不能把未完成切换显示为成功。并发配置变更使用 revision CAS；涉及通信模式的切换先固定锁顺序，防止相反方向同时变更。切至 `scheduled` 前须先将前端通信设为 `polling`；切至 `resident` 后还须确认 Worker、Redis、Gateway 等实时依赖才可开启 `realtime`。

定时消费的 CLI 与受认证 HTTP POST 入口只是同一个有界一次性消费用例的薄适配器。`eventing_consumer_state.scheduled_trigger` 只能选择 `none`（新安装默认）、`cli` 或 `http`，一个站点只配置其中一种触发源；`scheduler_runs.trigger_source` 记录实际调用来源。HTTP 触发要求 HTTPS、独立凭据、单次并发控制和批量/耗时上限，不提供公开 GET URL。`scheduled` 未声明触发源、最近一次运行失败/超时或最近成功来源与声明不一致，管理端必须报告事件处理不可用，即使浏览器轮询仍可读取已有数据。最近成功只能证明近期运行，不能证明将来还会按时触发；部署方仍需配置并监控 cron/外部定时服务。常驻模式根据新鲜心跳、Release、Schema 和 Handler Catalog 判定消费者就绪；进程故障时 Outbox 留待恢复，不自动切到定时策略。告警依赖可靠的外部运维渠道，不能只发入故障中的同一队列。

Redis 由系统配置统一管理，低于 Eventing 与通信层。它的 `eventing.wakeup` 只缩短常驻 Worker 的 MySQL 领取等待，发布失败不回滚已提交 Outbox；常驻 Worker 仍按自身策略从 MySQL 轮询任务。这不是事件处理策略回退。Redis 也可用于显式启用的 ThinkPHP 缓存用途；保存 Redis 连接不自动切全站缓存。实时通信的信号传输必须检查 Redis，数据库中的可靠投递仍以 MySQL 为准。

HTTP 定时入口为 `POST /api/internal/eventing/run-once`。独立 Bearer 凭据由部署变量 `LC_EVENTING_CRON_TOKEN` 提供，长度至少 32 字符，不能写入仓库、日志或后台响应。HTTPS 判定只依据由受信反代传入 PHP-FPM 的 `HTTPS=on`、`REQUEST_SCHEME=https` 或 TLS 端口，不能信任客户端自填的 `X-Forwarded-Proto`；反代应剥除客户端原始 Forwarded 头。凭据轮换由部署方执行，后台只展示触发源声明与运行状态。

未选中的常驻进程可以维持心跳并空闲，但 claim 必须被门禁拒绝；策略状态缺失时应明确失败。

### 迁移前基线与完成条件

截至本设计落地前，`EventingRequestDrain` 在普通请求结束时执行 `RequestExecutor`，`lc:scheduler:run-once` 也调用它，`lc:eventing:work` 另起常驻循环；尚无能在领取事务中强制互斥的站点策略。这是迁移基线，不是目标已完成。施工先接通新定时入口与持久策略、验证所有 Handler 可被定时消费，再移除用户请求自动 drain；升级前后未完成任务必须仍在原账本中，不能因迁移丢失。新增表或列进入现有 SystemLifecycle 迁移账本，并通过干净安装 schema 与旧版本迁移后 schema 一致性测试。

## 当前跨模块事件

Handler Registry 当前固定注册：

- `social.followed@1` -> `SocialFollowedNotificationHandler`；
- `message.sent@1` -> `MessageSentNotificationHandler`；
- `content.liked@1` -> `ContentLikedNotificationHandler`；
- `library.saved@1` -> `LibrarySavedNotificationHandler`；
- `content.comment.published@1` -> `ContentCommentPublishedNotificationHandler`；
- `content.mentions.added@1` -> `ContentMentionsAddedNotificationHandler`；
- `system.notification.issued@1` -> `SystemNotificationIssuedHandler`；
- `notification.created@1` -> `NotificationCreatedSignalHandler`（`notifications.new` 主动提示）；
- `message.sent@1` -> `MessageSentSignalHandler`（`messages.new` 主动提示；与站内提醒独立投递）；
- `message.read@1` -> `MessageReadSignalHandler`（`messages.read` 主动提示）；
- `content.card.published@1` -> `CardPublishedSignalHandler`（`feed.published` 主动提示）；
- `content.card.feed.batch.requested@1` -> `CardPublishedSignalHandler`（每批至多 200 名关注者的续批投递）；
- `file.purge.requested@1` -> `PurgeFileHandler`。

`realtime` 队列只负责前端变化信号；目录项与开关以 [FRONTEND-SIGNALS.md](FRONTEND-SIGNALS.md) 为准。轮询模式的实时投递登记即标记跳过，实时故障时不领取，手动切轮询时结算未发送投递。通知记录与私信数据不因此暂停。

互动通知事件与业务写入共用事务。`content.liked` 只在首次点赞时发布，携带
`actor_id/target_type/target_id/owner_id/card_id/comment_id?`；取消点赞不发布事件。
`library.saved` 只在首次收藏时发布，卡片收藏额外携带 `owner_id/card_id`。
两者分别使用 `like:{type}:{target}:actor:{actor}` 与
`save:card:{card}:actor:{actor}` 作为通知业务幂等键。

评论仅在真正变为可见时发布 `content.comment.published`。一级评论的主接收人是
卡片作者，回复的主接收人是父评论作者；主接收人同时被提及时只保留更具体的
评论/回复通知。卡片标题、卡片正文和评论正文中的有效提及先写入
`content_mentions`，只有新关系才发布 `content.mentions.added`，通知幂等键为
`mention:{resource_type}:{resource_id}:user:{recipient_id}`。

`system.notification.issued` 只能由可信后端服务发布，类型限于
`system.notice/system.security/system.moderation`，payload 仅允许
`message/action_url/severity`，其中 `action_url` 必须是安全的站内路径。
哪些业务必须发送系统通知、类型选择、前后端边界和验收清单统一以
[NOTIFICATIONS.md](NOTIFICATIONS.md) 为准。

新增事件/Handler 必须同时完成：事件 schema/version、Registry 注册、Handler 幂等与重试测试、Release Handler Catalog 更新、投递可观测字段和失败处理。不得为了“以后可能需要”给每个 CRUD 都发布事件。

## 运维入口

```bash
php think lc:eventing:work --max-jobs=0
php think lc:scheduler:run-once
php think lc:runtime:status
```

管理端/SDK：`client.operations.deliveries()`、`retryDelivery()`、`notificationStats()` 和 `runtime()`。
