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

可靠状态始终在 MySQL；Redis 可做最佳努力的 Worker 唤醒和实时模式的瞬时信号传输，Worker 内存只保存本轮循环状态。因此 Worker 重启、Redis 不可用或 FPM 请求结束不会丢失待处理事件。Redis Pub/Sub 不是业务事实或可靠投递账本。

## 部署形态

| 形态 | 必需 | 推荐配置 | 可观察体验 |
| --- | --- | --- | --- |
| 虚拟机/中小型社区 | Nginx、PHP-FPM、MySQL | 默认 request fallback 与浏览器轮询 | 通知和私信按配置间隔刷新；后台任务在请求预算或一次性调度中推进 |
| 原生服务器/中大型社区 | Nginx、PHP-FPM、MySQL | Redis + Event Worker + Scheduler；按容量决定 SSE | Outbox 低延迟消费、独立重试和批处理，通知可实时到达 |

不要求把 ThinkPHP Web Host 改造成常驻内存 Web。FPM 继续处理 HTTP，Worker 是独立 CLI 进程；这样既保留请求隔离，也避免每个请求承担全部异步工作。

## 旧 Facility 解析（迁移中）

`config/runtime.php` 定义三项当前设施，`FacilityResolver` 将 `mode + configured + probe` 解析成 enabled/provider/fallback 状态：

| Facility | 配置 | Provider | Fallback |
| --- | --- | --- | --- |
| `eventing.wakeup` | `LC_EVENTING_WAKEUP_MODE`、系统 `redis` 配置组 | `redis` | `mysql-poll` |
| `eventing.worker` | `LC_WORKER_MODE`、`LC_WORKER_ENABLED` | `php-worker` | `request` |
| `realtime.gateway` | `LC_REALTIME_MODE`、`LC_REALTIME_GATEWAY_URL` | `external-gateway`（独立 Node 进程） | 旧字段 `polling`，不得用于当前模式判定 |

业务模块只能依赖能力接口或稳定用例，不判断 Redis 扩展、不自行启动进程。Redis 扩展或连接不可用时，`OptionalRedisWakeupTransport` 可继续从 MySQL 领取异步任务；实时变化信号暂停投递并由运行状态显示故障，不把通信模式自动改为轮询。不能把 Redis 当可靠队列。

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

`--max-jobs=0` 表示常驻循环；发布、维护或内存基线要求定期重启时可设置有限任务数。管理端 `/apps/operations/runtime` 与 `client.operations.runtime()` 展示通信模式、真实探测和 Worker 心跳。旧 Facility fallback 仅用于迁移前诊断，不得作为通信状态源。

## 前端变化信号运行时（2026-11-10，实施中）

轮询与实时是管理员持久化选择的通信模式。`desired_mode` 是唯一模式事实；
`effective_mode` 暂保留给现有 API，但必须与 `desired_mode` 相同。实时依赖故障时
`status=degraded`、保留 `effective_mode=realtime`，不得悄悄启用浏览器轮询。
从轮询切到实时必须先通过 Redis、Gateway、Worker 和可选 Supervisor 的就绪检查。

Eventing 的实时信号投递使用 Runtime 公开的三态门禁：`deliver` 表示实时且健康，
`pause` 表示实时故障并保留待投递信号，`skip` 表示轮询且无需主动信号。
业务数据与站内通知仍由 PHP/MySQL 保存和读取；故障只停止主动变化信号。
Redis、Gateway 与 Worker 的实现可以更换，但需满足既有协议、Handler Catalog
与健康检查。Redis 连接统一由 `Platform/Config/Public/RedisConnectionSettings`
读取：新安装默认关闭，管理员可在配置组 `redis` 设置地址、端口、数据库和密码，
并调用 `POST /api/admin/config/redis/test` 检查当前连接。密码在管理读取中遮罩；
新安装不预填 Redis 地址；连接只由统一配置组维护，旧 `LC_REDIS_*` 环境变量
不再覆盖后台值。既有部署升级前须迁入连接参数。TLS 开关和证书名称属于同一份系统连接配置，PHP 与 Gateway
必须同时验证证书链和名称；详见 `FRONTEND-SIGNALS.md`。TLS 故障不允许
回退到明文 Redis 连接。

Gateway 经原有内部 Bearer 通道读取
`GET /api/internal/realtime/redis-config`，取得同一份配置及不可逆的版本指纹；
配置不可读时返回 503。Gateway 报告已加载版本，Runtime 对比版本，版本不一致
不判定为就绪。配置页面不管理 Redis 服务器或 Gateway 进程的启动。

`runtime_config_state` 单例行只持久化 `desired_mode` 与白名单运行参数，使用
`revision` 做乐观并发。Resolver 由当前探测派生 `status` 与 `reason_code`，
不把故障转换为另一种模式。默认安装为 polling/ready；轮询不要求 Redis、
Gateway 或 Event Worker。

实时硬依赖包括 Redis 发布端连接、Gateway 经认证的 `lc-realtime/1` 健康协议
与 Redis 订阅状态、Event Worker 新鲜心跳及匹配当前 Release 的 Handler Catalog。
Worker 启动时从当前代码的 Migration Catalog 和 `system_upgrade_steps` 迁移账本
计算 Schema 身份：每个代码迁移必须在账本中完成且 checksum 一致，账本不得有
代码未声明的迁移；对排序后的完整 `migration_id => checksum` 映射计算 SHA-256，
写入现有心跳 `schema_version` 字段。FPM 探测用只读账本与自身迁移目录重算
身份，不执行 DDL，并要求心跳完全匹配；自身代码超前、未知、未完成或不一致
均拒绝实时就绪。Worker 启动校验成功后只在该进程内缓存代码迁移目录；热循环
仍每次只读账本以发现变化，不反复扫描迁移文件。FPM 请求不预热此缓存，继续
校验其当前 Release 的目录。
升级中旧 Worker 的启动快照
不会因数据库改变而自动更新，须由部署管理器在迁移与激活后重启 Worker。
Schema 身份验证的是受审迁移账本与代码目录的一致性，不代替升级 Verify 阶段的
实际结构检查，也不在生产运行时生成或执行 schema diff。
部署要求 Supervisor 时再检查该项；Scheduler 是独立诊断，不阻塞实时模式。
实时开启前检查依赖，失败拒绝保存；运行中故障时保留 realtime/degraded，
Eventing 暂停实时信号领取。业务 API 与站内通知生成继续运行。

环境锁 `LC_RUNTIME_LOCK=polling` 拒绝从轮询开启实时；若已经选择实时，
锁定视为实时故障，不自动切换。Gateway 地址与内部 token 当前仍来自 env。
配置写入走 `GET/PUT /admin/operations/runtime/config`、`POST runtime/validate`
和 `POST runtime/reconcile`；PUT 需 `expected_revision` 并写审计。

浏览器从 `GET /api/me/communication/settings` 获取管理员选择和健康状态。
`communication-runtime` 是唯一取数节奏调度器；实时模式故障时不启动轮询。
Gateway 由独立进程提供 SSE，PHP-FPM 不承载长连接；浏览器凭一次性 ticket
连接 Gateway。通知、私信和信息流信号共用连接，数据仍通过各自 PHP API 读取。

`COMMUNICATION-RUNTIME-CONTRACT-2026-11-06.md` 保留为历史实施契约，
其中自动轮询回退与 Gateway `source-poll` 规定已被本节取代。

## 扩容边界

当前 MySQL Outbox 适合单机到中大型社区的可靠异步基础。应先通过队列等待时间、claim 冲突、重试率和数据库负载测量瓶颈；只有 MySQL 已成为明确吞吐瓶颈时，再增加专用消息系统适配器。迁移时保留 `EventEnvelope`、Handler Registry、幂等键和投递状态机，业务模块不随基础设施重写。
