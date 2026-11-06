# 可裁剪运行时

LoveCards 的最小运行单元是 Nginx + PHP-FPM + MySQL。Redis、常驻 Worker、独立 Scheduler 和实时通知属于可选设施；它们改变延迟、吞吐和运维形态，不改变 API、数据模型或业务正确性。

## 同一套代码如何适配两种环境

PHP-FPM 每个请求都从持久化状态重新建立上下文，请求结束后不依赖内存继续存在；常驻 Worker 则在同一个 PHP 进程中循环领取任务。两者共享 Domain/Application/Contract，区别只在“何时调用处理器”和“由哪个进程调用”：

```text
业务事务 -> MySQL outbox_events / event_deliveries
                |
                +-> 最小环境：RequestExecutor 按预算处理少量 request_safe 任务
                |
                +-> 完整环境：lc:eventing:work 常驻领取 worker 任务
```

可靠状态始终在 MySQL；Redis 只做最佳努力唤醒，Worker 内存只保存本轮循环状态。因此 Worker 重启、Redis 不可用或 FPM 请求结束不会丢失待处理事件。

## 部署形态

| 形态 | 必需 | 推荐配置 | 可观察体验 |
| --- | --- | --- | --- |
| 虚拟机/中小型社区 | Nginx、PHP-FPM、MySQL | 默认 request fallback；可选短 SSE | 通知刷新/轮询可见；后台任务在请求预算或一次性调度中推进 |
| 原生服务器/中大型社区 | Nginx、PHP-FPM、MySQL | Redis + Event Worker + Scheduler；按容量决定 SSE | Outbox 低延迟消费、独立重试和批处理，通知可实时到达 |

不要求把 ThinkPHP Web Host 改造成常驻内存 Web。FPM 继续处理 HTTP，Worker 是独立 CLI 进程；这样既保留请求隔离，也避免每个请求承担全部异步工作。

## Facility 解析

`config/runtime.php` 定义三项当前设施，`FacilityResolver` 将 `mode + configured + probe` 解析成 enabled/provider/fallback 状态：

| Facility | 配置 | Provider | Fallback |
| --- | --- | --- | --- |
| `eventing.wakeup` | `LC_EVENTING_WAKEUP_MODE`、`LC_REDIS_HOST` | `redis` | `mysql-poll` |
| `eventing.worker` | `LC_WORKER_MODE`、`LC_WORKER_ENABLED` | `php-worker` | `request` |
| `realtime.gateway` | `LC_REALTIME_MODE`、`LC_REALTIME_GATEWAY_URL` | `external-gateway`（独立 Node 进程） | `polling` |

业务模块只能依赖能力接口或稳定用例，不判断 Redis 扩展、不自行启动进程。Redis 扩展或连接不可用时，`OptionalRedisWakeupTransport` 静默退回 MySQL authoritative state；不能把 Redis 当可靠队列。

## 请求内与常驻进程规则

- RequestExecutor 使用 `RequestExecutionBudget`，单次最多处理 20 个任务；不能在 HTTP 请求里无界 drain 队列。
- Worker 使用 claim token、lease expiry 和 attempt count，崩溃后任务可重新领取。
- `runtime_workers` 持久化 worker id、type、state、心跳、进程信息和累计 jobs，管理端不依赖进程内状态猜测健康度。
- Scheduler 只调用幂等、可重入的一次性任务；最小环境可由 cron 或人工运行同一命令。
- 常驻 Worker 不缓存用户、权限、配置或数据库连接的业务事实；每个 job 必须重建作用域并释放临时资源。

## 启动与诊断

最小环境无需额外守护进程。完整环境按配置交给 systemd、Supervisor 或容器编排器运行：

```bash
php think lc:runtime:status
php think lc:eventing:work --max-jobs=0
php think lc:scheduler:run-once
```

`--max-jobs=0` 表示常驻循环；发布、维护或内存基线要求定期重启时可设置有限任务数。管理端 `/apps/operations/runtime` 与 `client.operations.runtime()` 展示 Facility 决策、fallback、通信模式和 Worker 心跳。

## 通信运行时控制面（2026-11-06 新增）

浏览器通信模式只有 `polling` 与 `realtime` 两种，契约冻结见
`COMMUNICATION-RUNTIME-CONTRACT-2026-11-06.md`。要点：

- 持久化只存 `desired_mode` + 白名单运行参数（`runtime_config_state` 单例行，`revision` 乐观并发）；
  `effective_mode`/`status`/`reason_code` 每次读取由 Resolver 经探测派生，永不持久化为权威。
- realtime 硬依赖 = Event Worker 心跳（`runtime_workers`）+ Realtime Gateway 能力探测
  （`GET {LC_REALTIME_GATEWAY_URL}/internal/health`，校验协议版本 `lc-realtime/1`、cursor 能力、
  ticket 鉴权与自报 ready，禁止只查端口）。Scheduler 是补偿能力（`scheduler_runs` 台账，
  `lc:scheduler:run-once` 每次运行写入），不阻塞 realtime，但必须如实显示。
- Supervisor 是否参与判定由 `require_supervisor` deployment policy 决定；Phase 1 未配置时记 not-required。
- 任一硬依赖缺失：`effective_mode=polling`、`status=degraded` 并给出 `reason_code`；
  默认安装为 polling/ready。Gateway/Worker 在场也不代表前端已切实时——以 effective 为唯一口径。
- 环境锁 `LC_RUNTIME_LOCK=polling` 只用于紧急锁定，拒绝后台切到 realtime（409 config-locked）；
  `.env` 不会静默覆盖已保存的目标模式。Gateway 地址与内部 token 只走 env（`LC_REALTIME_GATEWAY_URL`、
  `LC_RUNTIME_INTERNAL_TOKEN`），不入库。
- 配置写路径：`GET/PUT /admin/operations/runtime/config`、`POST runtime/validate`、`POST runtime/reconcile`，
  capability `operations.runtime.configure`；PUT 携带 `expected_revision`，冲突 409 stale-revision，
  失败保留旧 effective，成功写审计 `runtime.communication.configure`（before/after 全量）。
- FPM 伪 SSE（`me/notifications/stream` 与 `Platform/Realtime.php`）已于通信运行时 Phase 2 删除：
  realtime 由独立 Gateway 进程（`apps/realtime-gateway`）提供 SSE，PHP-FPM 内禁止长连接；
  浏览器经 `POST /api/me/notifications/stream-ticket` 领取一次性凭证连接 Gateway。
- `config/runtime.php` 的 `realtime.sse`（fpm-sse）设施条目已替换为 `realtime.gateway`
  （provider `external-gateway`，fallback `polling`）；运行状态页的通信模式卡片以本控制面
  探测为唯一实时口径，Gateway 不可用时 effective 回落 polling 并如实标记 degraded。
- 浏览器端唯一取数节奏调度器为 `communication-runtime`（主题侧），节奏来自
  `GET /api/me/communication/settings` 的服务端生效快照；组件不得自行 `setInterval`
  形成第二条长期取数链，realtime 链路故障时自动退回单条轮询链。

## 扩容边界

当前 MySQL Outbox 适合单机到中大型社区的可靠异步基础。应先通过队列等待时间、claim 冲突、重试率和数据库负载测量瓶颈；只有 MySQL 已成为明确吞吐瓶颈时，再增加专用消息系统适配器。迁移时保留 `EventEnvelope`、Handler Registry、幂等键和投递状态机，业务模块不随基础设施重写。
