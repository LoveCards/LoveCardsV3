# 通信运行时实施契约（Phase 0 冻结）

- 状态：Implementation Contract / 冻结待架构复审
- 日期：2026-11-06
- 上游依据：`COMMUNICATION-RUNTIME-DESIGN-2026-11-05.md`（分支 `docs/communication-runtime-design` @ `c6cf8c0`，尚未并入 main）
- 责任：主开发 agent 按设计 §12 提交的五项冻结件 + 并行 ownership。本文件是实现期唯一契约源；与上游设计冲突处以本文件"差异登记"为准，待架构确认后回写设计。

## 1. Runtime Contract（状态模型）

### 1.1 字段（冻结）

| 字段 | 类型 | 语义 |
|---|---|---|
| `desired_mode` | `polling` \| `realtime` | 管理员选择的目标模式（持久化） |
| `effective_mode` | `polling` \| `realtime` | 当前实际可用模式，**每次读取由 Resolver 经 probe 派生，永不持久化为权威** |
| `status` | `ready` \| `degraded` | 派生：desired 的能力全部满足=ready；desired=realtime 但能力缺失=degraded（此时 effective 回落 polling） |
| `revision` | INT UNSIGNED 单调 | 配置行乐观并发版本（CAS 凭据）。**设计 4.1 的 `config_version` 统一更名 `revision`**（差异登记 D1） |
| `reason_code` | VARCHAR(64) \| null | 降级/锁定原因（枚举见 1.3） |
| `checked_at` | DATETIME(6) UTC | 本次派生时间 |

派生快照（API 响应形状，`AdminRuntimeConfigResource`）：

```json
{
  "desired_mode": "polling",
  "effective_mode": "polling",
  "status": "ready",
  "revision": 1,
  "reason_code": null,
  "checked_at": "2026-11-06T00:00:00.000000Z",
  "settings": { "…见 2.2 白名单" },
  "last_apply_state": "applied",
  "last_apply_reason": null,
  "capabilities": [
    { "key": "event-worker", "ready": true,  "provider": "mysql-runtime_workers", "reason_code": null, "checked_at": "…" },
    { "key": "realtime-gateway", "ready": false, "provider": null, "reason_code": "gateway-not-configured", "checked_at": "…" },
    { "key": "scheduler", "ready": true, "provider": "mysql-scheduler_runs", "reason_code": null, "checked_at": "…" },
    { "key": "supervisor", "ready": null, "provider": null, "reason_code": "not-required", "checked_at": "…" }
  ]
}
```

### 1.2 状态机（冻结）

持久化状态只有 `desired_mode` + `settings_json` + `revision`。`applying`/`failed` **不持久化**（差异登记 D2，待架构确认）：

- `applying` 仅存在于 apply/reconcile 请求的响应瞬间（同步流程，无异步窗口，持久化只会产生悬挂脏状态）。
- 失败结果落两列快照供 UI 回显：`last_apply_state ∈ {applied, rejected, failed}`、`last_apply_reason`（= 首个 violation 或错误码），并在审计中保留完整 payload。
- 模式切换失败**不回写 desired 之外的一切**，effective 由下次 probe 继续按旧 revision 派生（验收 #3 的机制保证）。

apply 流程（单请求内）：解析 → 白名单/范围校验 → env 锁检查 → CAS(revision) → 写审计 → 重派生返回快照。校验失败：`422` violations[]；CAS 冲突：`409 stale-revision`；env 锁：`409 config-locked`。

### 1.3 reason_code 枚举（冻结）

`config-locked`（env 硬锁）、`schema-not-ready`（配置表缺失，fail-safe）、`worker-heartbeat-stale`、`gateway-not-configured`、`gateway-unreachable`、`gateway-protocol-mismatch`、`gateway-auth-unavailable`、`gateway-not-ready`（Gateway 自报未就绪）、`realtime-transport-degraded`（Redis pub/sub 断，Gateway 回源模式）、`supervisor-required-missing`、`probe-stale` / `probe-unavailable`（advisory 探测过期或不可用）、`not-required`（advisory 能力）。优先级取第一个命中项。

## 2. runtime_config_state 表与迁移策略（冻结）

### 2.1 DDL（迁移 `20261106000001.sql`）

```sql
CREATE TABLE IF NOT EXISTS `runtime_config_state` (
  `id` TINYINT UNSIGNED NOT NULL,                -- 恒为 1；MySQL 5.7 无 CHECK，单行由代码保证（只 UPDATE 不 INSERT）
  `desired_mode` VARCHAR(16) NOT NULL DEFAULT 'polling',
  `settings_json` TEXT NOT NULL,
  `revision` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_apply_state` VARCHAR(16) NOT NULL DEFAULT 'applied',
  `last_apply_reason` VARCHAR(64) NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- 种子：id=1, desired='polling', settings=全部默认值, revision=1（UTC_TIMESTAMP(6)）
```

同文件新增 `scheduler_runs` 台账（见 §4 探测）；**Phase 1 对既有表零 ALTER**（expand-only）。

### 2.2 settings 白名单与上下限（冻结；服务端强制）

| 键 | 默认 | 范围 |
|---|---|---|
| `notification_interval_seconds` | 30 | 5..300 |
| `message_interval_seconds` | 45 | 5..300 |
| `unread_interval_seconds` | 20 | 5..120 |
| `polling_backoff_factor` | 2 | 1.0..5.0 |
| `polling_max_interval_seconds` | 300 | 60..900 |
| `pause_when_hidden` | true | bool |
| `worker_stale_seconds` | 30 | 10..300 |
| `scheduler_stale_seconds` | 3600 | 300..86400 |
| `gateway_stale_seconds` | 15 | 5..120 |
| `require_supervisor` | false | bool（deployment policy） |

白名单外键一律 422；Gateway URL / 内部 token 等基础设施参数**不入库**，只走 env（§4.2）。

### 2.3 迁移策略（冻结）

- `20261106000001.sql`：`runtime_config_state` + `scheduler_runs` + 默认行（Phase 1）。
- `20261106000002.sql`：`capabilities` 追加 `operations.runtime.configure`（幂等 LEFT JOIN 种子，risk_level=3）（Phase 1）。
- 实际落号（实现期核对）：
  - `20261106000004.sql`：系统角色模板差分种子 `operations_admin + operations.runtime.configure`（Phase 1，见 D7）。
  - `20261106000005.sql`：`stream_tickets`（一次性 stream ticket 哈希表，Phase 2）。
  - `20261106000006.sql`：`notification_policies` + `notification_policy_versions` + 种子 + `event_deliveries` **ADD COLUMN**（`policy_revision` INT UNSIGNED NULL、`policy_decision` VARCHAR(24) NULL）（Phase 3，expand→backfill→verify→complete 四阶段，回滚=只停写不 DROP；历史在途投递不回填，NULL=not-configured fail-open）。
  - `20261106000007.sql`：`capabilities` 追加 `communications.configure` + legacy admin 角色种子（Phase 3）。
  - `20261106000008.sql`：系统角色模板差分种子 `operations_admin + communications.configure`（Phase 3，D7 工作流）。
- 回滚原则：新表/新列可空/只增，旧代码无感；不写 down 脚本，`schema_migrations` 账本为事实源（既有 SystemMigrate 流程）。

## 3. 旧资产删除/迁移清单（冻结）

| 资产 | 处置 | 时点 |
|---|---|---|
| `GET /admin/operations/runtime` 的 `operations.runtime` 名与 `operations.read` | 保留，响应新增 `communication` 块 | Phase 1 |
| 伪 SSE：`api/controller/Platform/Realtime.php::stream()`、路由 `me/notifications/stream`（`community.php:74`）、`ValidationScenes` stream 场景、`Community.php:114,134` scene | **删除**（不修帧）。契约三件套+SDK 由生成脚本重导出 | Phase 2 首 commit（B），与 D 同 PR |
| `RealtimePublisher` 契约 + `NullRealtimePublisher` 空绑定（provider.php） | 替换为 `RealtimeSignalPublisher` 端口 + Redis/回源两个 adapter，必有真实调用方 | Phase 2 |
| SDK `notifications.stream(): Promise<string>` 生成物 | 随路由删除消失；新增 `streamTicket()` 与 `runtime.*` 方法（生成链产物，禁手改） | Phase 2 / SDK 再生成 |
| `default-theme` `parseNotificationSseSnapshot`（community-service.js:80）+ `community-service.test.js` 引用 | 删除；由统一通信客户端替代 | Phase 4（C） |
| `AppShell.jsx:71` 30s 与 `InboxPage.jsx:126` 20s `setInterval` | 删除，接入 communication context | Phase 4（C） |
| `config/runtime.php` `realtime.sse`（fpm-sse）facility | 替换为 `realtime.gateway`（provider 由 probe 决定，fallback polling） | Phase 2 |
| `packages/sdk/test/integration-suite.mjs:372-375` | 删除对 stream 的断言（原 G1 修帧方案作废，并入 T3 决策=删除端点） | Phase 2 |
| EVENTING.md / RUNTIME-ARCHITECTURE.md 相关表述 | Phase 1 更新控制面与降级语义；fpm-sse 废止说明随 Phase 2 | D |
| `eventing.wakeup`（DeliveryWakeup 未接线，审计 G3） | **不在本轮范围**：outbox 唤醒与浏览器实时信号是两个通道；本轮只冻结 realtime 通道，wakeup 保持文档如实标注 + backlog | 不排期 |

## 4. Gateway 契约（冻结；Phase 2 实现）

### 4.1 形态与 ownership

独立进程 workspace `apps/realtime-gateway`（B owns；参考实现 Node 18+，无框架依赖；差异登记 D3：技术栈选型待架构确认，契约与语言无关）。PHP-FPM 内永不长连接（红线）。

### 4.2 配置（env，不入 settings）

`LC_REALTIME_GATEWAY_URL`（backend probe 用，未配置=能力缺失 `gateway-not-configured`）、`LC_RUNTIME_INTERNAL_TOKEN`（backend↔gateway 共享密钥）、`LC_REALTIME_GATEWAY_PUBLIC_URL`（ticket 响应里的 connect_url 前缀）、`LC_REDIS_HOST`（信号通道）。

### 4.3 health/capability（验收 #7：禁止只查端口）

`GET {gateway}/internal/health`，Header `Authorization: Bearer ${LC_RUNTIME_INTERNAL_TOKEN}`，响应：

```json
{ "protocol": "lc-realtime/1", "ready": true, "cursor": true, "auth": ["ticket"], "version": "semver", "transport": "redis|source-poll" }
```

probe 判定：HTTP 200 + `protocol === 'lc-realtime/1'` + `cursor === true` + `auth` 含 `ticket` + `ready`。任一不满足 → ready=false，reason 按 `gateway-unreachable / gateway-protocol-mismatch / gateway-auth-unavailable` 分类。probe 超时 2s，结果缓存 `gateway_stale_seconds`。

### 4.4 stream ticket（浏览器短期凭证）

- `POST /api/me/notifications/stream-ticket`（JWT）→ `{ "ticket": "<26-hex>", "expires_in": 60, "connect_url": "${PUBLIC_URL}/stream", "heartbeat_seconds": 15 }`
- 存储：`stream_tickets(ticket_hash CHAR(64) PK, uid BIGINT, expires_at DATETIME(6), used_at DATETIME(6) NULL, created_at)`——一次性，verify 即原子置 `used_at`（重复使用=拒绝）。
- 校验通道：Gateway `POST {backend}/api/internal/realtime/ticket/verify`（Bearer internal token）`{ticket}` → 200 `{uid, cursor_seed}` 或 401。
- v1 只实现 ticket；HttpOnly cookie 模式记为可选扩展（差异登记 D4），不双轨。
- 长期 JWT 进 URL 一律禁止（设计红线）。

### 4.5 SSE 协议（Gateway 出流）

- `GET {gateway}/stream?ticket=…`：`Content-Type: text/event-stream`、`X-Accel-Buffering: no`。
- 帧（真实 `\n` 字节，单帧示例）：

```text
retry: 2000
id: <opaque cursor（与轮询 next_cursor 同一游标编码）>
event: signal
data: {"topic":"notifications","cursor":"<opaque>"}
```

- topic：`notifications` | `messages` | `unread`。**信号只携带 topic/recipient scope/cursor hint，不携带业务事实**；浏览器收到后一律经游标 API 回源。
- `: ping` 15s；连接最长 900s 后服务端关闭（客户端凭 `retry` 自动重连）；`Last-Event-ID` 按游标续传，游标失效→ `event: resync`（data 空），客户端全量补齐。
- Worker→Gateway 信号：Redis pub/sub channel `lovecards:realtime`，消息 `{"topic","uid","cursor"}`；发布者 = `RealtimeSignalPublisher` 端口（B owns），调用方 = 通知投影**首次 insert 成功回执**（`notifications` 写入返回 row id 时；`dedupe` 命中不发信号——避免重复唤醒）。Redis 断：Gateway 以 `source-poll`（默认 5s 游标回源）继续供流；runtime 快照 `realtime-transport-degraded`（差异登记 D5：transport 退化时 effective 仍可为 realtime，仅当 Gateway/Worker 本体不可用才回落 polling——待架构确认）。

### 4.6 探测硬依赖（冻结）

realtime 生效 ⇔ `event-worker.ready && realtime-gateway.ready (&& supervisor.ready 当 require_supervisor=true)`。Scheduler 是补偿/ advisory，不阻塞 realtime，但 UI 必须显示其真实态（台账缺失=unknown，不装作可用）。

- Worker ready：`runtime_workers` 存在 `worker_type='eventing'` 且 `heartbeat_at > now - worker_stale_seconds`（沿用既有 `ThinkDbWorkerHeartbeatStore`，不改心跳协议；元数据扩展列属 Phase 2 backlog）。
- Scheduler ready：`scheduler_runs` 最新 `finished_at > now - scheduler_stale_seconds`（`SchedulerRunOnce` 每次运行写台账：insert running → 完成置 `outcome/jobs_processed/finished_at`）。
- Supervisor：Phase 1 只落 `require_supervisor` 策略位；probe 实现（systemd/supervisord adapter）随部署形态立项，未实现时 `require_supervisor=true` 诚实报 `supervisor-required-missing`。

## 5. 通知策略契约（冻结；Phase 3 实现）

### 5.1 表

```sql
CREATE TABLE IF NOT EXISTS `notification_policies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(128) NOT NULL,
  `event_version` SMALLINT UNSIGNED NOT NULL,
  `rule_key` VARCHAR(128) NOT NULL,              -- uq(event_type,event_version,rule_key)
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `notification_type` VARCHAR(64) NULL,          -- 投影类型（NULL=纯策略行）
  `recipient_rule` VARCHAR(64) NOT NULL DEFAULT 'handler',   -- 收件人仍由 Handler 决定，策略只开关门
  `user_disableable` TINYINT(1) NOT NULL DEFAULT 1,
  `locked` TINYINT(1) NOT NULL DEFAULT 0,        -- locked=1 普通管理员不可改
  `policy_revision` INT UNSIGNED NOT NULL,       -- 该行所属全局版本
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_np_rule` (`event_type`,`event_version`,`rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_policy_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_revision` INT UNSIGNED NOT NULL,
  `snapshot_json` LONGTEXT NOT NULL,             -- 目录全量（canonical order）
  `source` VARCHAR(16) NOT NULL,                 -- seed | admin | system
  `reason` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_np_rev` (`policy_revision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

种子（migration 内建 revision=1）：六类互动事件（`social.followed`、`message.sent`、`content.liked`、`content.saved`、`comment.published`、`mentions.added`）`enabled=1, locked=0, user_disableable=1`；`system.notification.issued`（security/moderation/账号安全投影）`locked=1, enabled=1`——普通管理员**只能**改互动类。

### 5.2 policy_revision 冻结与 skipped 语义

- 事件登记（EventRecorder 写 `event_deliveries`）时把**当时**的全局 `policy_revision` 写入 `event_deliveries.policy_revision`（同事务）。后续策略修改不改变在途投递语义（重放/retry 按冻结版本读快照）。
- 关闭规则不删除事件/投递：Handler 照常执行到"投影前决策点"，按冻结版本判定 → 命中关闭 → 投递进入显式结果 `policy_decision='skipped'`，status 仍 `completed`（差异登记 D6：不新增 DeliveryStatus 枚举值，用 `policy_decision ∈ {applied, skipped, not-configured}` 列表达，UI 显示"已按策略跳过"，死信统计不受影响——待架构确认）。
- 未种子化的新事件默认 `not-configured` → 按代码注册执行（fail-open 到"照旧发通知"，绝不因策略缺行丢通知）。
- 策略修改走 `PUT /admin/communications/policies`（capability `communications.configure`，Phase 3 seed），批量 CAS（`expected_revision`），全量审计 before/after + 新 policy_revision。

## 6. API / SDK / 能力冻结

### 6.1 Phase 1 路由（operations.php，meta 同 `$access/$response/$request` 范式）

| 路由 | name | capability | 请求/响应 |
|---|---|---|---|
| `GET runtime/config` | `operations.runtimeConfig` | `operations.read` | → AdminRuntimeConfigResource |
| `POST runtime/validate` | `operations.validateRuntimeConfig` | `operations.runtime.configure` | scene `runtimeConfig` → AdminRuntimeValidateResource（`{valid, violations[]}`，无副作用） |
| `PUT runtime/config` | `operations.applyRuntimeConfig` | `operations.runtime.configure` | scene `runtimeConfig` → 快照；审计 `runtime.communication.configure`（payload=before/after/revision，`_ip`） |
| `POST runtime/reconcile` | `operations.reconcileRuntime` | `operations.runtime.configure` | → 快照（重探+必要时重派生） |

`AdminValidate` 新增 scene `runtimeConfig`：`normal=[desired_mode, expected_revision, notification_interval_seconds, message_interval_seconds, unread_interval_seconds, polling_backoff_factor, polling_max_interval_seconds, pause_when_hidden, worker_stale_seconds, scheduler_stale_seconds, gateway_stale_seconds, require_supervisor]`，`require=[desired_mode, expected_revision]`；范围校验在 Application（422 结构化 violations，不在 Validate 规则里塞区间魔法）。

### 6.2 能力与角色（冻结）

- `CapabilityCatalog` +`operations.runtime.configure`（"变更运行时通信模式与运行参数"）；Phase 3 再 +`communications.configure`（"启停互动通知策略"）。
- `SystemRolePolicy::administrationTemplates()`：`operations_admin` 增 `operations.runtime.configure`；`auditor`/其余不动；`super_admin` 显式 grant 机制不变（模板外）。

### 6.3 SDK 方法（生成链产物，`npm run generate:sdk` 导出，禁手改）

`operations.runtimeConfig()/validateRuntimeConfig()/applyRuntimeConfig()/reconcileRuntime()`；Phase 2 +`notifications.streamTicket()`；Phase 3 +`communications.policies()/applyPolicies()`。生成链：`npm run export:api` + `generate-sdk-contract.mjs`（route manifest 由 `check-route-manifest.mjs` 再生成）+ `check:generated:sdk` 守门。

## 7. Ownership 与并行依赖（冻结）

| Owner | 范围（独占写） | 关键依赖 |
|---|---|---|
| A Runtime | `modules/Operations/Runtime/**`、`config/runtime.php`、`AdminReadModel::runtime()`、Runtime 测试 | 迁移 1/2 由 D 出，A 只读 schema |
| B Eventing/Realtime | `modules/Platform/Eventing/**`（信号端口/投影回执）、`apps/realtime-gateway/**`、删 FPM SSE 三处、ticket 端点 | 依赖 A 的 settings 快照；迁移 3 由 D 出 |
| C SDK/Theme 客户端 | `packages/sdk` 手写包装层、`apps/default-theme/**` 通信上下文 | 依赖 B 的 ticket/SSE 协议冻结（§4 已冻，可并行起步） |
| D 整合 | 迁移文件、`provider.php`、`api/route/**`、`CapabilityCatalog`、`SystemRolePolicy`、`contracts/**`+generated、跨模块测试、文档 | **串行**：所有跨 owner 文件只经 D |

依赖图：`迁移1/2 → A → (B ∥ C) → D 汇总闸口`。单人实施期（当前）按 A→B→C→D 串行执行，边界与文件 ownership 不变。
分支计划：Phase 1 `feat/runtime-control-plane`；Phase 2 `feat/realtime-gateway-adapter`（含删旧链）；Phase 3 `feat/notification-policies`；Phase 4 `feat/communication-client`；文档 PR 随各阶段。

## 8. 差异登记（需架构确认）

| # | 设计原文 | 本契约 | 理由 |
|---|---|---|---|
| D1 | `config_version` | 统一 `revision` | 与任务书字段清单一致，避免双名 |
| D2 | status 含 `applying/failed`（4.1） | 持久化只 `ready/degraded`；applying 瞬时、失败落 `last_apply_*` 快照列 | 无异步窗口，持久化瞬时态会悬挂 |
| D3 | Gateway"独立进程" | 参考实现 Node 18+（`apps/realtime-gateway`） | SSE/重连实现成本最低；协议与语言无关，换栈不动契约 |
| D4 | ticket 或 HttpOnly cookie（任务书二选一） | v1 仅 ticket | 单轨避免双凭证审计 |
| D5 | "Redis 不可用时…回源"（设计 §5） | transport 退化 effective 仍 realtime | 回落 polling 是能力判定，不因传输退化丢实时 |
| D6 | "Delivery 进入显式 skipped/no-op 或等价记录"（设计 §7） | `policy_decision` 列 + status=completed | 不扩 DeliveryStatus 枚举，死信/重试语义零改动 |
| D8 | 浏览器通信节奏来源（§6 API 冻结未列出用户侧快照端点） | 新增 `GET /api/me/communication/settings`（登录态，返回 `effective_mode + status + settings` 节奏快照） | 服务端裁决模式、客户端零模式分叉的必要入口；不暴露设施与管理能力细节 |
| D9 | 策略决策落点（§5.2 "投影前决策点"） | 决策在签发器 `ManageNotifications::create` 内、通知投影 insert 之前；`policy_decision` 由 `EventRecorder::notePolicyDecision` 落 Delivery 列 | Handler 照常执行到签发点；关闭规则只跳过投影，事件与投递记录保留 |
| D7 | 系统角色模板授权变更方式 | 历史种子 `20260823000001.sql` 冻结（checksum 入 ledger 不可改写），模板新增授权以差分迁移追加（`20261106000004.sql` 起），`SystemRoleTemplatesBaseline` 联合解析两文件 | 直接编辑历史种子会触发 DirtyMigrationException，破坏既有实例升级 |

## 9. 验收映射（对任务书 10 条）

1 默认 seed polling + 无 worker 时 desired=polling → ready（Resolver 规则）。2 capabilities 逐项 ready+reason，UI 分列展示。3 apply 失败不回写、effective 按旧 revision 派生。4 游标 + Last-Event-ID + dedupe 回执（§4.5/§5）。5 上下限 §2.2 + `publicContext()` 下发。6 基线测试 `tests/Runtime/CommunicationControlPlaneBaseline.php`（静态+fake：禁 mode 分支、禁直写通知表的架构位检查并入 check:architecture 既有规则评估）。7 §4.3。8 每阶段闸口记录。9 dev 四进程不重启、不碰生产（迁移只新增表，dev 可直接 SystemMigrate）。10 每阶段 PR 后汇报变更/迁移/风险/下一阶段依赖，等复审。
