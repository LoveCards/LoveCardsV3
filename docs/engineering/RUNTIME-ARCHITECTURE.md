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
| `realtime.sse` | `LC_SSE_MODE` | `fpm-sse` | `refresh` |

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

`--max-jobs=0` 表示常驻循环；发布、维护或内存基线要求定期重启时可设置有限任务数。管理端 `/apps/system/runtime` 与 `client.operations.runtime()` 展示 Facility 决策、fallback 和 Worker 心跳。

## 扩容边界

当前 MySQL Outbox 适合单机到中大型社区的可靠异步基础。应先通过队列等待时间、claim 冲突、重试率和数据库负载测量瓶颈；只有 MySQL 已成为明确吞吐瓶颈时，再增加专用消息系统适配器。迁移时保留 `EventEnvelope`、Handler Registry、幂等键和投递状态机，业务模块不随基础设施重写。
