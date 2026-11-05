# 事件总线、任务与投递

LoveCards 的系统级事件能力是一套小型、可测试的领域事件协议，不是进程内“万能广播器”，也不要求部署 RabbitMQ。它只解决跨模块事实传播和需要重试的副作用；同一模块内的直接调用仍应保持直接。

## 边界

```text
Publisher
  -> EventEnvelope
  -> EventRecorder
  -> outbox_events + event_deliveries（与业务写入同一事务）
  -> RequestExecutor 或 WorkerLoop
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

错误记录只保存经过 `FailureSanitizer` 清理的稳定摘要，不保存 Token、凭证或完整个人数据。管理员可在 `/apps/operations/deliveries` 查看投递和筛选失败项；手工重试需要 `operations.retry` 并写审计。

## 两种执行模式

- `request_safe`：没有 Worker 时由受预算的 `RequestExecutor` 处理，单请求最大 jobs 在 1–20 之间；Handler 必须短、可重入、不得依赖请求结束后的内存。
- `daemon_required`：由 `lc:eventing:work` 或 Scheduler 消费，适合发送、媒体处理和较慢任务；Worker 以 `runtime_workers` 心跳报告状态。

队列只在 `HandlerRegistry` 注册。HTTP request drain 只从其中派生
`request_safe` 队列；Scheduler 与 Worker 派生全部模式。当前 `notifications`
允许 HTTP 预算兜底，`storage` 仅允许 Scheduler/Worker，不能把外部文件删除放入用户请求。

Redis `eventing.wakeup` 只减少 Worker 的轮询延迟。Redis wakeup 发布失败不回滚已经提交的 MySQL Outbox，Worker 始终可以通过 MySQL poll 找到任务。

## 当前跨模块事件

Handler Registry 当前固定注册：

- `social.followed@1` -> `SocialFollowedNotificationHandler`；
- `message.sent@1` -> `MessageSentNotificationHandler`；
- `content.liked@1` -> `ContentLikedNotificationHandler`；
- `library.saved@1` -> `LibrarySavedNotificationHandler`；
- `content.comment.published@1` -> `ContentCommentPublishedNotificationHandler`；
- `content.mentions.added@1` -> `ContentMentionsAddedNotificationHandler`；
- `system.notification.issued@1` -> `SystemNotificationIssuedHandler`；
- `file.purge.requested@1` -> `PurgeFileHandler`。

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
