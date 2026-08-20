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

错误记录只保存经过 `FailureSanitizer` 清理的稳定摘要，不保存 Token、凭证或完整个人数据。管理员可在 `/apps/operations/deliveries` 查看投递、筛选失败项并填写原因重试；手工重试需要 `operations.retry` 并写审计。

## 两种执行模式

- `request_safe`：没有 Worker 时由受预算的 `RequestExecutor` 处理，单请求最大 jobs 在 1–20 之间；Handler 必须短、可重入、不得依赖请求结束后的内存。
- `worker`：由 `lc:eventing:work` 常驻消费，适合发送、媒体处理和较慢任务；Worker 以 `runtime_workers` 心跳报告状态。

Redis `eventing.wakeup` 只减少 Worker 的轮询延迟。publish 失败不回滚 MySQL Outbox，Worker 始终可以通过 MySQL poll 找到任务。

## 当前跨模块事件

Handler Registry 当前固定注册：

- `social.followed@1` -> `SocialFollowedNotificationHandler`；
- `message.sent@1` -> `MessageSentNotificationHandler`；
- `file.purge.requested@1` -> `PurgeFileHandler`。

新增事件/Handler 必须同时完成：事件 schema/version、Registry 注册、Handler 幂等与重试测试、Release Handler Catalog 更新、投递可观测字段和失败处理。不得为了“以后可能需要”给每个 CRUD 都发布事件。

## 运维入口

```bash
php think lc:eventing:work --max-jobs=0
php think lc:scheduler:run-once
php think lc:runtime:status
```

管理端/SDK：`client.operations.deliveries()`、`retryDelivery()`、`notificationStats()` 和 `runtime()`。
