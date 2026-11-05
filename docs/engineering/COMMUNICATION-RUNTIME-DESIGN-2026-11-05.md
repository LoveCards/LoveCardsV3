# 通信运行时与事件投递统一设计

- 设计状态：基线已冻结；Gateway、票据、通知 signal、能力探测和契约迁移已实现，Runtime 控制面与主题通信客户端待接入
- 日期：2026-11-05
- 适用版本：LoveCardsV3
- 责任边界：本文件定义 LC 基础能力、运行时控制面、通信模式和迁移验收；具体实现按任务包拆分并行完成

## 1. 目标与非目标

### 目标

1. 后台可以在 `polling` 与 `realtime` 之间选择目标模式。
2. 启动和运行期间能检测 Worker、Gateway、Scheduler、Supervisor 的实际能力。
3. 业务模块只调用 LC 的事件/通知能力，不判断通信方式。
4. Polling 与 Realtime 共享 Outbox、Delivery、通知持久化和游标，不丢事件、不重复通知。
5. 轮询频率、重试退避、心跳等安全范围内的运行参数可以在后台配置，并且有版本和审计。
6. 旧的伪 SSE、旧的固定轮询、旧的 SDK raw 快照契约全部有明确迁移和删除条件。
7. 设计能够拆成独立任务包并行开发，最后通过契约生成和统一测试门禁合并。

### 非目标

- 本轮不引入 RabbitMQ、Kafka 或新的可靠消息队列。
- 本轮不把 PHP-FPM 改成常驻 Web 进程。
- 本轮不让前端直接触发通知、不让业务模块直接写 `notifications`。
- 本轮不把 Redis wakeup 变成可靠状态源；MySQL Outbox 仍是权威状态。
- 本轮不同时长期运行两套浏览器通知链路。

## 2. 现状基线

当前已经存在并必须保留：

```text
业务事务
  -> DomainEventBus
  -> EventRecorder
  -> outbox_events + event_deliveries
  -> RequestExecutor 或 WorkerLoop
  -> HandlerRegistry
  -> EventHandler
```

现有运行能力：

- `RequestExecutor`：无常驻 Worker 时按预算处理 `request_safe`。
- `WorkerLoop`：CLI 常驻消费 `request_safe` 与 `daemon_required`，写入 `runtime_workers` 心跳。
- `SchedulerRunOnce`：执行一次性调度任务。
- `RuntimeFacilityCatalog` + `ResolveFacilities`：读取 env facility 配置并探测。
- `/admin/operations/runtime`：只读展示 facility、worker、Outbox。
- `/admin/operations/deliveries`：查看 Delivery，死信可重试。

当前缺陷和迁移对象：

| 现状 | 处理结论 |
|---|---|
| `config/runtime.php` 只有 `eventing.wakeup`、`eventing.worker`、旧 `realtime.sse` | 已迁移为 `realtime.gateway`；`fpm-sse` 不再是有效 Provider，必须由 Gateway capability probe 决定 effective mode |
| `Platform\\Realtime::stream()` 只查一次并返回快照，且 SSE 换行拼接错误 | 删除伪长连接；Realtime 模式改由独立 Gateway 提供连接 |
| `RealtimePublisher`/`NullRealtimePublisher` 无实际调用方 | 改成有明确语义的 Gateway Publisher，或在垂直切片完成时移除空接口；不得继续空绑定 |
| 旧 `notifications.stream` SDK 契约是 `Promise<string>` 且 `after` 为数字 | 已删除旧路由和生成方法，改为 `notifications.streamTicket`；主题通信客户端待接入 |
| `InboxPage`/`AppShell` 各自固定 20s/30s 轮询 | 收敛到一个通信运行时客户端 |
| 私信打开/发送后手动重新加载 | Polling 用游标，Realtime 用信号后回源；统一去重 |
| Runtime API 只有 GET | 增加目标配置、校验/应用、状态查询接口，并更新权限和审计 |
| 通知 Handler 固定写入通知 | 保留事件事实与 Handler 幂等；通知策略只控制允许的投影，不绕过代码注册 |

## 3. 目标架构

```text
业务模块
  -> LC EventBus / Notification Facade
  -> Outbox（MySQL 权威）
  -> Event Worker 或 RequestExecutor
  -> Delivery / HandlerRegistry
  -> Notification Projector（持久化通知）
  -> ClientDeliveryAdapter
       ├─ PollingStore：浏览器按游标读取
       └─ RealtimeGatewayPublisher：Gateway 推送信号
             -> Realtime Gateway
             -> 浏览器 EventSource
```

业务模块不能出现：

```php
if ($mode === 'realtime') { ... }
if (env('LC_SSE_MODE')) { ... }
Db::table('notifications')->insert(...);
```

业务模块只发布业务事实，例如 `message.sent`、`content.liked`、`social.followed`。内部 Handler 根据事件和已发布的通知策略生成通知。Realtime 信号只提示“有新数据”，浏览器仍通过游标 API 回源，不能把推送 payload 当权威事实。

### LC 基础能力分层

```text
Platform/Eventing/Public       EventBus、EventEnvelope、DeliveryContext
Platform/Eventing/Application  RequestExecutor、WorkerLoop、DeliveryProcessor
Platform/Notifications/Public  通知 Facade、策略读取、游标查询
Operations/Runtime/Public      RuntimeSnapshot、Mode、Capability、Config Port
Operations/Runtime/Application  配置校验、模式切换、启动探测、降级决策
Infrastructure/Driver           MySQL、Redis wakeup、Realtime Gateway、Supervisor adapter
Host/API                        Controller、Validate、Resource、Route Meta
```

Contract 只表达业务稳定能力；具体 MySQL、Gateway、Supervisor、systemd/Supervisor 实现放在 Infrastructure。HTTP Controller 不直接访问 Model、ConfigService 或进程命令。

## 4. 通信模式和状态模型

### 4.1 配置模型

对外只暴露：

```text
communication_mode = polling | realtime
```

持久化至少区分：

```text
desired_mode       管理员选中的目标模式
effective_mode     当前实际可用模式
status             ready | degraded | applying | failed
config_version     单调递增版本
last_checked_at
reason_code
```

默认 seed 为 `polling`。`.env` 只允许作为首次 bootstrap 或紧急锁定，不得继续覆盖后台已经保存的通信模式；否则后台切换不会生效。通用 `config.update` 不能直接写这些键，必须经过 Runtime Application 的白名单、范围验证和审计。

### 4.2 Polling

- 不启用浏览器长连接。
- 通知、未读数、私信使用统一游标 API。
- 轮询客户端根据服务端配置、页面可见性和退避策略工作。
- 数据库中的通知/消息是唯一事实源。
- 无常驻 Worker 时，`request_safe` 由 RequestExecutor 按预算处理；慢任务继续由 Scheduler/Worker 处理。

建议配置边界：

```text
notification_interval_seconds  5..300
message_interval_seconds       5..300
unread_interval_seconds        10..600
batch_size                     1..100
error_backoff_seconds          1..300
hidden_page_pause              boolean
```

### 4.3 Realtime

Realtime 模式的通信硬依赖是：

```text
Event Worker + Realtime Gateway
```

Supervisor 是进程治理能力，不是通信协议硬依赖；Scheduler 是补偿和定时任务能力，也不是实时推送硬依赖。部署策略可以要求 Supervisor，也可以由容器编排或人工托管进程；Runtime 只在策略要求时把 Supervisor 缺失计入模式降级。

SSE 只作为 Gateway 到浏览器的单向协议：

- 客户端发送仍走 HTTP API；
- Gateway 负责连接、心跳、断线和重连；
- Event Worker 负责消费 Outbox；
- 浏览器连接使用短期 stream ticket 或 HttpOnly auth cookie，不能把 JWT 长期放进 URL；
- 推送信号包含 topic 和 cursor hint，不承载可替代的业务事实；
- 重连后用 `after`/`Last-Event-ID` 游标补齐。

Event Worker 到 Gateway 的进程间通知使用带内部凭证的 HTTP Publisher，发送低价值唤醒信号；MySQL notifications/cursor 仍是权威事实。Publisher 失败不回滚业务写入，浏览器必须通过定期回源补偿丢失的唤醒；不能把未定义的空 Publisher 当作实时已启用。Redis pub/sub 仅在测量到 HTTP Publisher 瓶颈后作为可替换传输评估。

如果 Worker 或 Gateway 不 ready，系统不得宣称 Realtime 已生效，应保持 `effective_mode=polling`、`status=degraded`，并在 Runtime 页面显示原因。

### 4.4 模式切换

Polling -> Realtime：先探测并确认 Worker/Gateway ready；若部署策略要求 process manager，再额外确认 Supervisor。通过后原子提升 effective mode。前端读取新配置后建立连接，连接建立前继续按游标读取。

Realtime -> Polling：先停止新增实时信号，完成已提交 Outbox/Delivery，再发布新配置；前端用最后游标补齐后切换到轮询。

任何切换失败都必须保留原 effective mode，不能把“目标已保存”误报成“已生效”。

## 5. 启动和运行探测

### 能力

| 能力 | 权威检查 |
|---|---|
| Event Worker | `runtime_workers` 中 `worker_type=eventing` 的新鲜 heartbeat |
| Realtime Gateway | 带内部凭证的 health/capability probe；不能只看端口 |
| Scheduler | 当前 `SchedulerRunOnce` 只有一次性执行，没有常驻 heartbeat；需要新增 heartbeat 或一次性 run ledger 后才能显示维护能力 ready |
| Supervisor | 外部 Supervisor adapter 的进程状态和版本；是否为硬门槛由 deployment policy 决定 |
| Outbox | MySQL 查询、积压、过期 lease、dead 数量 |

`runtime_workers` 继续复用，不新增重复心跳表。Provider、版本、checked_at、reason 必须进入 Runtime Snapshot。启动检查和运行时刷新使用同一套 Resolver，避免页面显示与实际执行分叉。

## 6. 后台页面职责

### Runtime

包含：

- desired/effective mode；
- 切换、校验、应用结果；
- Polling/Realtime/Worker/Scheduler 参数；
- facilities、provider、版本、ready/degraded；
- Worker 心跳、Supervisor 状态；
- Outbox 和队列统计；
- 当前降级原因和最后检查时间。

高风险操作要求 `operations.runtime.configure`，应用前显示影响、旧值、新值和降级条件，成功后写审计。

### Deliveries

保留现有事件投递监控：

- event + handler；
- queue、execution mode；
- pending/processing/retry/dead；
- attempt、lease、last_error、correlation id；
- 只有允许状态可手工重试，重试需要 `operations.retry` 和二次确认。

### Notification Policies

建议单独页面，不把业务规则塞进 Runtime：

- 事件目录；
- 是否允许投影为通知；
- 通知类型；
- 收件人规则；
- 用户是否可关闭；
- 去重策略；
- 配置版本和审计。

安全、权限、审核终态等强制通知必须由代码标记为不可关闭。

## 7. 配置与通知策略持久化

### 7.1 Runtime 配置

本方案选择新增 Runtime 专用单例状态表，而不是让通用 `configs` 直接承载通信控制。建议字段：

```text
runtime_config_state
  id = 1
  desired_mode
  settings_json
  revision
  updated_by
  updated_at
```

`settings_json` 只允许 Runtime Application 定义的白名单键。`revision` 用于乐观并发控制；后台提交时必须携带读取到的 revision，旧页面不能覆盖新配置。审计仍写现有 `audit_logs`，记录 before/after、desired/effective、reason 和 correlation id。

通信模式状态和单项设施状态分层：`effective_mode=polling` 可以是整体可用，同时某个 storage queue 或 scheduler facility 处于 degraded；只有通信模式本身无法按安全路径运行时才把整体标记为 degraded。

`.env` 只负责首次安装默认值或不可绕过的紧急禁用开关，不能与这张表形成第二个正常运行时事实源。通用 `ConfigService` 不直接暴露 Runtime 写入入口。

### 7.2 Notification Policy

通知策略使用专用的 `notification_policies` 与 `notification_policy_versions`（或等价的规范化结构），不改 `notifications` 核心语义。事件入 Outbox 并创建 `event_deliveries` 时必须解析并冻结策略版本：`event_deliveries` 增加 nullable `policy_revision` 或稳定 `policy_rule_key`，避免 pending delivery 在重试时因后台后来关闭规则而改变语义。

建议字段至少包括 `event_type`、`event_version`、`rule_key`、`enabled`、`notification_type`、`recipient_rule`、`user_disableable`、`dedupe_strategy`、`locked`、`revision` 和更新审计字段。关闭规则时仍保留可观测的投递决策：Delivery 进入明确的 `skipped`/`no-op` 结果，或写入等价的 policy decision 记录，不能让事件从投递监控中凭空消失。

策略只能控制可配置的互动通知投影；`system.security`、`system.moderation` 等强制事件由代码锁定，普通管理员不能关闭。

通知投影发布 Realtime 信号时，必须区分“首次插入”和“幂等命中旧记录”。只有真正新建的通知才发布信号，避免重试因 dedupe 命中而重复唤醒浏览器。现有 `NotificationRepository::create(): int` 公共契约不应直接改成结构返回值；可增加内部 `createWithReceipt()` 或等价 Port，逐步迁移 fake 和测试。

## 8. API 和数据契约

建议新增接口（最终名称按现有路由命名规范确认）：

```text
GET  /admin/operations/runtime
GET  /admin/operations/runtime/config
POST /admin/operations/runtime/validate
PUT  /admin/operations/runtime/config
POST /admin/operations/runtime/reconcile
```

前端通信上下文建议：

```text
GET /runtime/communication
```

返回：

```json
{
  "desired_mode": "polling",
  "effective_mode": "polling",
  "status": "ready",
  "config_version": 3,
  "polling": { "notifications": 20, "messages": 10, "unread": 30 },
  "realtime": { "endpoint": null, "heartbeat": 15, "reconnect": [1, 2, 5, 15, 30] },
  "cursor_strategy": "opaque",
  "reason_code": null
}
```

旧 `GET /me/notifications/stream` 伪 SSE 接口已删除。Realtime 模式通过 `GET /me/notifications/stream-ticket` 取得短期票据，再连接独立 Gateway 的 `/stream`；Polling 模式直接使用通知与私信 API。Gateway 连接可取消，`after` 和 `Last-Event-ID` 仅承载不透明 cursor hint；断线后重新签发 ticket 并通过业务 API 回源补齐，不恢复数字 id 契约。

## 9. 旧代码迁移清单

### 后端

- `apps/backend/config/runtime.php`
  - 增加 Runtime 配置 schema 和默认 polling；`realtime.gateway` 由能力探测决定，不再使用 `fpm-sse`。
  - 明确 env/bootstrap 与 DB desired config 的优先级。
- `app/api/controller/Platform/Realtime.php`
  - 在 Gateway 切片完成前删除伪快照；不得修成 FPM 内部循环。
- `app/modules/Platform/Eventing/Contract/RealtimePublisher.php`
  - 改成有调用方、可探测的 Gateway publisher port；否则随旧 stream 一起删除。
- `NullRealtimePublisher.php`、`RealtimeSignal.php`
  - 仅在新 adapter 有明确 fallback 语义时保留；不能作为“已实时”的证明。
- `ResolveFacilities`、`FacilityResolver`、`NativeFacilityProbe`
  - 增加 desired/effective、Gateway/Scheduler/Supervisor probe 和 degraded reason。
- `AdminReadModel::runtime()`、`AdminRuntimeResource`
  - 增加模式、配置版本、provider、能力和切换结果字段。
- `Administration.php`、`app/api/route/operations.php`
  - 通过 Runtime Public/Application 增加配置/校验/应用接口；Controller 只读 validated input。
- `provider.php`
  - 绑定 Runtime Config/Control、Gateway publisher 和 Supervisor adapter；不再永久绑定空 realtime publisher。
- `HandlerRegistry` 与通知 Handler
  - 保留事件注册和幂等；Realtime 推送作为明确副作用 Handler/adapter，不改业务事务事实。

### 数据库和配置

- 新增并唯一使用 `runtime_config_state` 单例表，保存通信配置、revision、desired mode 和更新者；不得同时把 `configs` 作为第二个正常运行时事实源。
- `effective_mode`、provider、reason 和探测时间由 Runtime Resolver 每次结合 probe 派生；可持久化为带 `checked_at` 的状态快照，但管理员不能直接写 effective mode，也不能用过期快照代替实时探测。
- 新增通知策略和策略版本表（不修改 notifications 核心语义）。
- 复用 `runtime_workers`，扩展 worker type/provider 元数据，不重复建 heartbeat 表。
- Scheduler 不能直接冒充 `runtime_workers` 心跳：在新增 heartbeat/run ledger 前，Runtime 必须显示 `scheduler=unknown` 或 `degraded`，不能声称 ready。
- 新增 capability：建议 `operations.runtime.configure`、`communications.configure`，同步 CapabilityCatalog、SystemRolePolicy、迁移 seed、navigation 和 contract 检查。
- 迁移按 expand → backfill/default → verify → contract 的顺序；旧版本忽略新表也能安全启动，回滚不删除旧表。Runtime 配置表缺失或数据库不可用时必须 fail-safe 到 `polling/degraded`；只读状态返回 `schema-not-ready`，写入接口拒绝并审计失败。

### SDK、管理端、主题

- `packages/sdk`：新增 runtime context/config、admin runtime mutation、realtime adapter 类型；不能手改 generated 文件。
- `contracts/route-manifest.json`、`contracts/openapi.json`、`packages/sdk/src/generated/**`：由生成链更新。
- `apps/admin/pages/apps/operations/runtime.vue`：从只读改为目标模式、参数、校验、应用、失败和审计提示。
- `apps/admin/pages/apps/operations/deliveries/**`：保留现有投递监控，新增模式/transport 过滤字段时同步契约。
- `apps/default-theme/src/pages/community/InboxPage.jsx`、`AppShell.jsx`：删除各自固定 interval，使用统一通信客户端。
- `ConversationPanel.jsx`、`MessagesPage.jsx`、`community-service.js`：消息游标、轮询/实时切换、断线补偿、按 message id/client_message_id 去重。
- `streamTicket` 适配：主题端取得短期票据后由 EventSource 连接 Gateway；通知事实仍通过游标 API 回源。
- 更新 `FRONTEND-AGENT-HANDOFF.md`、`COMMUNITY-CAPABILITIES.md`、`RUNTIME-ARCHITECTURE.md`、`API_REFERENCE.md`、`API_ENDPOINT_REFERENCE.md` 等文档。
- 同步 `DATABASE-OPERATIONS.md`、`SYSTEM-LIFECYCLE.md` 中的迁移、启动和回滚说明；历史审计文档只追加勘误，不伪造旧测试数字。

## 10. 并行任务包

### 我负责：架构控制面与验收

1. 维护本设计文档、模块边界和迁移账本。
2. 设计 Runtime Contract：desired/effective、Capability、Probe、Config Version、Audit。
3. 审查后端 Runtime/Application/Public/Infrastructure 的实现是否符合模块依赖规则。
4. 审查 Outbox/Delivery、切换无丢失、幂等和降级行为。
5. 补充/审阅架构基线、状态机、模式切换和真实 dev 验收清单。
6. 检查旧代码是否全部迁移、生成物是否由命令更新、文档是否如实。

### 主开发 Agent 负责：垂直实现

可拆成以下可并行子任务，各子任务先新增回归基线，再实现：

- **A：Runtime 控制面**
  - 配置持久化、校验、探测、desired/effective、切换 API、权限、审计、Runtime 页面。
- **B：Eventing/Realtime adapter**
  - Gateway、短期 ticket、通知 signal、能力探测及旧 FPM 伪 SSE 移除已由独立进程任务完成；剩余 Runtime 模式门控、私信 signal 与真实部署联调。
- **C：SDK/Theme 通信客户端**
  - 统一 polling/realtime adapter、通知和私信消费、退避、可见性、去重、断线补偿。
- **D：契约/迁移/测试**
  - migration、capability seed、route/OpenAPI/SDK 生成、后端/SDK/Admin/Theme 测试和文档同步。

文件 ownership 在并行期间必须固定：

| 文件/目录 | 唯一负责人 |
|---|---|
| `app/modules/Operations/Runtime/**`、`config/runtime.php`、Runtime migration | A |
| `apps/realtime-gateway/**`、Gateway ticket/publisher/probe | 独立进程任务；B 仅接 Runtime 模式门控与私信 signal |
| `apps/default-theme/src/**`、主题通信测试、`apps/admin/pages/apps/operations/runtime.vue` | C |
| RBAC seed、route/OpenAPI/SDK 生成、跨模块测试和文档 | D |
| `provider.php`、共享 route 文件 | 先由 A/B 冻结接口，再由 D 串行修改；不能多人同时编辑 |

任务依赖：

```text
A Runtime Contract ─┬─> B Realtime adapter
                    ├─> C SDK/Theme client
                    └─> D contracts/migrations/tests
```

A 先冻结字段和状态；B/C 可以在 A 提供测试替身后并行；D 持续验证生成链，不要最后一次性补生成物。

## 11. 验收标准

### 运行时

- 默认新安装为 `polling/ready`。
- `desired_mode` 与 `effective_mode` 始终可区分。
- Realtime 依赖不满足时保持 polling 并显示 degraded 原因。
- Gateway、Worker、Scheduler、Supervisor 的状态来自可验证 probe/heartbeat。
- 后台参数修改有范围验证、版本、权限和审计。
- `.env` 不会静默覆盖数据库中的后台配置。

### 事件与通知

- 业务事务、Outbox、Delivery 在同一事务边界内保持一致。
- Worker 重试、切换、重连不会重复通知。
- 推送只做唤醒，浏览器能用游标补齐。
- 安全/治理强制通知不可被普通策略关闭。
- 不允许前端触发通知或业务模块直接写通知表。

### 前端与契约

- Polling 模式按后台频率工作，页面隐藏时按策略暂停/退避。
- Realtime 模式能建立、重连、心跳和按游标补偿。
- 私信、通知、未读数使用同一通信上下文，不再各自维护固定 interval。
- SDK/OpenAPI/route manifest/generated bundle 全部由脚本生成并一致。
- Admin Runtime、Deliveries、Notification Policies 页面权限和导航一致。

### 质量门禁

```text
npm.cmd run check
npm.cmd run build:sdk
npm.cmd run sync:sdk-theme
apps/backend 受影响 PHP 全部 php -l
一次性 localhost SDK integration
一次性 Admin/Theme E2E（发版候选）
```

未能运行的外部 dev 验收必须记录原因，不能用静态检查代替。

## 12. 当前决策与待确认项

已确定：

- 产品运行模式只有 `polling` 与 `realtime`。
- 业务代码不感知通信方式。
- Outbox/MySQL 是权威事实源。
- Realtime 需要独立 Gateway；SSE 只是 Gateway 的下行协议。
- 后台可切换模式并配置安全范围内的运行参数。
- 现有 FPM 伪 SSE 不能继续作为实时实现。

本轮基础接入已确定旧接口迁移方案与 Gateway 协议。主开发 Agent 继续提交：

1. Runtime Contract 字段和状态机；
2. Runtime 门控 `streamTicket` 与 Publisher 的接口和降级响应；
3. 数据库迁移文件和回滚/兼容说明；
4. 并行任务之间的文件 ownership，避免多人同时改同一文件。

