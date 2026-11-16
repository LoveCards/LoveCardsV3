# 事件队列接入与治理规范

> 状态：本分支待人工审查；合并后适用于 `apps/backend` 的所有 Event Handler；事件业务边界仍以 [事件总线、任务与投递](EVENTING.md) 为准。

## 目的

`queue_name` 是事件处理任务的**逻辑分类与路由字段**。它按副作用、外部依赖和运维责任划分任务，不是业务模块名称，也不是资源优先级。一个业务事件可以登记多个 Handler，因此可以同时进入多个队列；例如 `message.sent@1` 同时进入 `notifications` 和 `realtime`。

当前队列目录由代码 [QueueCatalog](../../apps/backend/app/modules/Platform/Eventing/Public/QueueCatalog.php) 维护。Handler 注册时会校验队列是否已登记，以及队列是否允许该执行模式。未知队列不能通过拼写字符串直接接入。

当前消费者跨队列领取，队列键本身不提供独立 Worker、并发配额、吞吐或资源隔离保证。需要物理隔离时必须另行设计消费者和部署契约。

## 当前队列目录

| 队列 | 治理责任模块 | 任务分类 | 当前使用 | 允许执行模式 | 主要依赖 |
| --- | --- | --- | --- | --- | --- |
| `notifications` | Platform/Notifications | 短事务型业务副作用，生成或更新站内通知 | 关注、点赞、评论、提及、系统通知 | `request_safe` | MySQL |
| `realtime` | Platform/Eventing | 不携带业务正文的浏览器主动提示，以及信号资格判断 | 新通知、新私信、已读变化、信息流变化 | `daemon_required` | MySQL、Redis、Realtime Gateway |
| `storage` | Platform/Files | 文件或对象存储生命周期操作 | 文件清理 | `daemon_required` | MySQL、Storage Driver |

“全部事件”只是管理端筛选项，不是队列。队列目录没有暗含优先级；同一队列中的 Handler 仍按各自的 `max_attempts` 和退避时间处理。

## 三个维度必须分开

```text
event_type + event_version  // 已发生的业务事实
        ↓
handler_key                 // 一个具体副作用
        ↓
queue_name                  // 副作用的逻辑分类与路由
execution_mode              // Handler 的运行环境要求
consumer_mode               // 站点选择 scheduled 或 resident
```

- `queue_name` 决定处理任务的逻辑分类和管理端筛选；当前不保证独立领取或吞吐。
- `execution_mode` 目前只有 `request_safe` 和 `daemon_required`。它是 Handler 既有的运行环境声明，不是站点消费策略；当前定时一次性消费者和常驻 Worker 都可领取已登记队列中的相应任务，不能把 `daemon_required` 解读成只能在 `resident` 策略下运行。
- `consumer_mode` 是站点运行策略。`scheduled` 与 `resident` 共享队列和投递账本，不改变 Handler 的业务语义。
- 资源消耗是选择队列时的输入，但不是单独的等级系统。当前没有 `low/normal/high` 这种队列优先级契约。

## 新模块接入流程

### 1. 先确认是否真的需要事件

只有跨模块事实传播、可重试副作用或外部传输适合事件。强一致校验、权限判断、余额和同一请求必须立即返回的核心写入继续使用直接调用。

### 2. 选择已有队列

按下列问题判断：

1. 只是写入站内通知记录，并且可以在短事务预算内完成？使用 `notifications`。
2. 只是向浏览器发送变化提示，依赖 Redis/Gateway，且不携带私密业务正文？使用 `realtime`，并声明 `signal` 元数据。
3. 涉及文件或对象存储清理、外部存储访问或长退避？使用 `storage`。
4. 都不符合？先提交新的队列目录变更，说明任务分类依据、依赖、失败处理、执行模式、所有者和运维指标，再添加 Handler。不能为了方便把新工作塞进 `notifications` 或 `realtime`。

预计可能出现的独立边界包括外部邮件/短信投递、搜索索引同步、媒体转码和批量分析。这些只是**候选需求，不预建空队列**：只有当实际 Handler 具有不同凭据、限流、超时、失败恢复或容量要求时才申请新队列；容量隔离还需单独实现消费与部署机制；若只是现有通知或文件清理的另一种业务来源，继续复用现有队列。

### 3. 在模块自己的 Public 装配中登记

模块在 `apps/backend/app/modules/<Module>/Public/EventHandlers.php` 返回 `HandlerRegistration`，应用组合根 [app/EventHandlers.php](../../apps/backend/app/EventHandlers.php) 显式合并它。不得扫描目录、反射发现或在管理端动态创建 Handler。

现有“点赞后生成通知”的登记可作为最小参考：

```php
new HandlerRegistration(
    'content.liked', 1, 'notifications.content-liked',
    'notifications', ExecutionMode::REQUEST_SAFE,
    new ContentLikedNotificationHandler()
);
```

业务写入侧在现有事务内发布 `content.liked`；这一条登记只创建“生成通知”的投递。若同一事件还需要浏览器主动提示，应再登记独立的 `realtime` Handler，而不是让通知 Handler 顺带发布 Redis。

每条登记必须明确：

- `event_type`、`event_version` 和稳定的 `handler_key`；
- `queue_name`、`execution_mode`、`max_attempts`、退避时间；
- Handler 的幂等键和重复执行行为；
- 失败是否可重试、哪些失败应永久失败、错误摘要如何脱敏；
- 若新增根实时信号，`signal.key/topic/owner/display_name/impact_text/default_enabled`；现有信息流续批处理器复用根信号资格，不单独登记 `signal`；
- 事件 payload 的字段来源、隐私边界和 schema 版本。

### 4. 在业务事务内发布事实

业务写入、Outbox 事件和投递任务必须同事务提交。业务模块经公开用例或 `DomainEventBus::publish()` 发布事实；不得直接写 `outbox_events`、`event_deliveries` 或直接发布 Redis。外部网络、Redis 和对象存储 I/O 在投递处理阶段执行，并按各 Handler 的重试预算和退避规则处理。

### 5. 完成测试和运维接入

新增 Handler 必须至少覆盖：

- 事件 schema/version 与错误 payload；
- 事务回滚不会留下孤立事件；
- 幂等重放不会重复业务副作用；
- 失败、退避、死信和人工重试；
- 依赖不可用时的可观测错误；
- 权限、隐私和审计边界；
- Handler Catalog、QueueCatalog 和运行时队列覆盖检查。

如果新增的是实时信号，还要覆盖关闭不回放、资格代次、范围接收者和 Gateway/Redis 不可用情况。新增队列还要补管理端筛选文案、运行状态聚合、Worker 心跳队列声明和部署告警。

## 新增队列的审查门槛

新增队列不是为了给每个模块或每个事件单独命名。提案至少回答：

1. 现有三条队列为什么不能承载？
2. 新队列区分的是副作用、外部依赖、故障恢复还是运维责任？
3. 它允许哪些 `execution_mode`，为什么？
4. 是否需要独立 Worker、凭据、并发上限和告警？如需要，消费和部署契约如何落实？退避预算由哪些 Handler 管理？
5. 它的 payload 是否包含私密数据，失败摘要如何脱敏？
6. 该队列的积压、处理耗时、失败率和死信如何观测？
7. 如何迁移或回滚已有投递，如何避免新旧消费者重复领取？

平台维护者批准目录后，必须同步修改：

- `QueueCatalog` 及其拓扑测试；
- [EVENTING.md](EVENTING.md) 的边界和运维说明；
- 管理端事件筛选和运行状态展示；
- Worker/Scheduler 的队列覆盖与部署文档；
- 事件目录、架构检查和发布验收记录。

## 不允许的做法

- 用模块名、类名或事件名直接充当队列名；
- 为了“资源重”而新增队列，却没有独立的失败、并发或运维边界；
- 把 `request_safe`、`daemon_required` 当作队列名；
- 把 `scheduled`、`resident` 当作队列名；
- 在用户请求中顺手执行异步 Handler；
- 让实时信号携带通知正文、私信正文、文件路径或其他不必要的私密数据；
- 未登记队列、未更新 Catalog 就上线 Handler。
