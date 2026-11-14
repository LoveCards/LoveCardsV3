# 可裁剪运行时

> 2026-11-11 复审：当前基线仍有配置读取失败被解释为轮询、健康口径不一、阶段运行资格未闭合等缺口；孤立 Redis wakeup 已退役。目标和完成证据见 [收敛设计](PLATFORM-CONVERGENCE-2026-11-11.md)，下文不得视为这些缺口已验收。

> 状态：2026-11-11 事件消费与通信分层实施契约。当前代码已移除普通请求的 `EventingRequestDrain`，并将定时/常驻领取门禁接入 Eventing；`lc:runtime:status` 已与管理端共用两份模式快照。部署与 E2E 验收仍以最终测试报告为准。假 Supervisor 探测已从实时门禁移除。

LoveCards 的最小 Web 运行单元是 Nginx + PHP-FPM + MySQL。事件处理和前端通信是两种独立的运行策略：事件选择 `scheduled`（定时消费）或 `resident`（常驻消费）；通信选择 `polling`（浏览器轮询）或 `realtime`（SSE）。业务模块统一写 MySQL Outbox、读取 PHP API，不识别部署形态。两种形态下业务操作、API 数据与最终结果一致；处理时延和提示形式可以不同。定时事件处理还需要 cron 或安全的外部定时触发，不能仅凭 Web 服务运行就宣称异步任务健康。

## 同一套代码如何适配两种环境

PHP-FPM 每个请求都从持久化状态重新建立上下文，请求结束后不依赖内存继续存在；常驻 Worker 则在同一个 PHP 进程中循环领取任务。两者共享 Domain/Application/Contract、Outbox 和投递状态机，区别只在“何时调用处理器”和“由哪个进程调用”：

```text
业务事务 -> MySQL outbox_events / event_deliveries
                |
                +-> 最小环境：外部 cron 调用一次性消费者
                |
                +-> 完整环境：lc:eventing:work 常驻领取任务
```

可靠状态始终在 MySQL；Redis 用于实时模式的瞬时信号传输，Worker 内存只保存本轮循环状态。因此 Worker 重启、Redis 不可用或 FPM 请求结束不会丢失待处理事件。Redis Pub/Sub 不是业务事实或可靠投递账本。Redis 是系统 Config 管理的可选基础设施，位于事件与通信能力之下；设置连接不会自动开启缓存或启动服务，使用 Redis 的功能在启用时检查它。

ThinkPHP 缓存默认仍为 file。只有部署方显式选择 `cache.driver=redis` 时，`Platform/Config/Infrastructure/SystemRedisCache` 才延迟读取同一份系统 Redis 连接配置并建立连接；长驻进程最多每 5 秒重新读取连接配置，版本变化后重连。配置未启用或连接失败时报告错误，不静默切回 file。系统配置的 `redis` 分组本身跳过通用缓存，以免出现配置读取递归。后台 Redis 配置页管理连接和测试，缓存驱动选择仍属于部署配置，不因保存 Redis 连接自动改变。

## 部署形态

| 形态 | 必需 | 事件处理 | 前端通信 | 可观察体验 |
| --- | --- | --- | --- | --- |
| 虚拟主机 | Nginx、PHP-FPM、MySQL、一个可靠定时触发源 | `scheduled` | `polling` | 通知和私信由浏览器定期读取；信息流可手动刷新，异步任务按定时触发推进 |
| 服务器、只加速任务 | Nginx、PHP-FPM、MySQL、Event Worker 及进程托管 | `resident` | `polling` | 异步任务低延迟处理，浏览器仍按周期读取 |
| 服务器、实时提示 | 上述服务及已配置 Redis、Gateway 和同源入口 | `resident` | `realtime` | 通知、私信和信息流收到变化提示后调用 PHP API 读取 |

组合只有上表三种；`scheduled + realtime` 不合法。事件策略互斥的是**领取者**，共享业务代码和数据不互斥。常驻 Worker 及 Gateway 是两个独立角色，LC3 通过角色、协议版本、Release/Schema/Catalog 与健康报告识别，不要求同一种实现语言；当前 Worker/Handler 是 PHP，Gateway 是 Node。发送私信、已读、发布内容等写操作继续走 PHP API。

不要求把 ThinkPHP Web Host 改造成常驻内存 Web。FPM 继续处理 HTTP，Worker 是独立 CLI 进程；这样既保留请求隔离，也避免每个请求承担全部异步工作。

## 唯一模式状态与诊断

Eventing 的 `eventing_consumer_state` 保存 `scheduled/resident`；Runtime 的 `runtime_config_state` 保存 `polling/realtime`。`RuntimeCommunication::statusSnapshot()` 组合 `event_processing` 和 `communication` 两份快照：前者结合定时运行记录或 Worker 心跳判定健康，后者检查实时依赖。管理端 `/apps/operations/eventing` 与 `/apps/operations/communication`、管理 API 与 `php think lc:runtime:status` 使用同一投影；页面分别负责事件消费和浏览器通信配置。旧 `/apps/operations/runtime` 是书签兼容跳转，不承载第三套运行逻辑。状态中的 `mode`/`desired_mode` 是选择，`status` 与 `reason_code` 是探测结果；探测故障不修改选择。CLI 输出为一个 JSON 对象，包含 `event_processing` 与 `communication`，不再输出 `request/polling` fallback。

管理 API 的旧 `facilities` 字段暂时返回空数组，概览 API 的旧 `runtime` 字段也返回空数组，以保留现有响应形状。旧 `config/runtime.php`、`FacilityResolver`、配套探测类及 `fallback` 逻辑已从当前代码删除；不得为了填充兼容空字段恢复第二套模式判断。

业务模块只能依赖能力接口或稳定用例，不判断 Redis 扩展、不自行启动进程。选定的常驻消费者按自身循环间隔从 MySQL 领取任务；实时变化信号暂停投递并显示故障，通信不自动切轮询。不能把 Redis 当可靠队列。

## 定时与常驻消费者规则

- 定时 CLI 与受认证 HTTP POST 调用同一个有界一次性消费用例；每站点只配置一种触发源。HTTP 须 HTTPS、独立凭据、单次并发控制、批量及耗时上限。普通用户请求不触发队列消费。
- Worker 使用 claim token、lease expiry 和 attempt count，崩溃后任务可重新领取。
- `runtime_workers` 持久化 worker id、type、state、心跳、进程信息和累计 jobs，管理端不依赖进程内状态猜测健康度。
- 定时一次性运行记录保存在 `scheduler_runs`；最近一次失败或探测过期均报告不可用。人工执行一次不能证明长期触发源已配置。
- 常驻 Worker 不缓存用户、权限、配置或数据库连接的业务事实；每个 job 必须重建作用域并释放临时资源。

`eventing_consumer_state` 持久化选定模式、切换状态、定时触发来源和 revision。定时触发来源为 `none`（默认）、`cli` 或 `http`，运行台账记录实际来源；只将声明与近期成功记录匹配时显示近期健康，不以一次人工成功证明持续触发已部署。每次 claim 在 MySQL 事务内核对并锁定策略行，仅选定且处于 active 的消费者可以领取任务。切换先暂停领取并等待在途任务或租约结算，再启用目标；期间不得让定时和常驻同时领取。失败保持原模式或明确的 draining 状态，管理员可重试，不得显示为已成功切换。进入实时的顺序是先确认事件为 active/resident，再验证 Redis、Worker、Gateway，最后切通信；退出常驻前先把通信切到 polling。并发转换使用 `eventing_consumer_state` → `runtime_config_state` 的固定锁顺序，两个配置的 revision CAS 分别生效。故障不会自动启动另一种消费者；积压留在 MySQL，服务恢复或管理员切换后由新选定策略继续处理。

从 `scheduled` 切到 `resident` 时，先在互斥门禁下切换选定策略，再由部署方启动或确认 Worker；Worker 新鲜心跳出现前事件处理显示 degraded。若 Worker 已启动但站点尚选 `scheduled` 或处于 draining，进程可以继续心跳并空闲，不领取任务；切换完成后才开始 claim。不能要求尚未获准 claim 的 Worker 预先报告可处理任务，否则无法完成切换。开启 `realtime` 仍须等待 active/resident 及 Worker、Redis、Gateway 全部就绪。

## 启动与诊断

虚拟主机无需额外守护进程，但需要 cron 调用一次性消费者，或由受认证的外部定时服务经 HTTPS POST 触发同一用例。常驻进程由 systemd、supervisord 或容器编排器在 LC3 外部托管：

```bash
php think lc:runtime:status
php think lc:eventing:work --max-jobs=0
php think lc:scheduler:run-once
```

`--max-jobs=0` 表示常驻循环；发布、维护或内存基线要求定期重启时可设置有限任务数。`lc:runtime:status`、管理端和管理 API 均展示**事件处理模式与健康**、**通信模式与健康**；模式选择和探测结果分开。`supervisor` 旧字段只曾表示管理员期望，没有真实 systemd/supervisord 探测；从实时硬门禁和可保存设置移除，进程托管是否存在由部署方核实，不能伪装成探测成功。

## 前端变化信号运行时（2026-11-11 收敛契约）

轮询与实时是管理员持久化选择的通信模式。`desired_mode` 是唯一模式事实；
`effective_mode` 暂保留给现有 API，但必须与 `desired_mode` 相同。实时依赖故障时
`status=degraded`、保留 `effective_mode=realtime`，不得悄悄启用浏览器轮询。
从轮询切到实时必须先确认事件处理为 active/resident，再通过 Redis、Gateway 和 Worker 的就绪检查。

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
Scheduler 是定时事件处理模式的健康诊断；resident 模式不要求 Scheduler，realtime 门禁也不检查它。旧 `require_supervisor` 没有真实探测依据，不作为启用门禁。
实时开启前检查依赖，失败拒绝保存；运行中故障时保留 realtime/degraded，
Eventing 暂停实时信号领取。业务 API 与已提交的业务事实继续可用；若常驻事件消费者自身故障，通知投影和其他异步任务留在 MySQL，待同一消费者恢复或管理员完成受控策略切换后处理，不能声称通知已生成。

环境锁 `LC_RUNTIME_LOCK=polling` 拒绝从轮询开启实时；若已经选择实时，
锁定视为实时故障，不自动切换。Gateway 地址与内部 token 当前仍来自 env。
通信配置写入走 `GET/PUT /admin/operations/runtime/config`、`POST runtime/validate`
和 `POST runtime/reconcile`；事件策略通过 `GET/PUT /admin/operations/event-consumption` 读写，底层调用 Eventing Public。两个 PUT 均需各自 `expected_revision` 并写审计；状态页只组织安全顺序，不在前端伪造一次性原子切换。

浏览器从 `GET /api/me/communication/settings` 获取管理员选择和健康状态。
`communication-runtime` 是唯一取数节奏调度器；实时模式故障时不启动轮询。
Gateway 由独立进程提供 SSE，PHP-FPM 不承载长连接；浏览器凭一次性 ticket
连接 Gateway。通知、私信和信息流信号共用连接，数据仍通过各自 PHP API 读取。

票据签发、存储、消费和传输资源归属 `Operations/Runtime`，通过 Auth Public
`SessionAccess` 复用现有登录会话、用户状态与安全版本检查。`stream_tickets.session_public_id`
绑定签发请求当前会话；消费时复核会话和所选实时模式，旧的未绑定票据拒绝。
外部 `notifications.streamTicket` URL/SDK 操作名暂保留，内部 `cursor_seed` 固定 null，
不再查询通知业务。内部验证必返 `connection_ttl_seconds`（1—60），Gateway 将单次
连接限制为该期限并扣除验证耗时；缺少期限拒绝连接。撤销、封禁、安全版本变化对
已连 SSE 的失效窗口最多 60 秒，不承诺即时踢下线。到期重签票据、重连及 API 补读
使用现有通信调度器，不开启业务轮询。健康协议必须声明整数 `session_ttl_max_seconds`（1—60）；探测拒绝未声明或超限的旧 Gateway。部署须同步更新 Gateway。

`COMMUNICATION-RUNTIME-CONTRACT-2026-11-06.md` 保留为历史实施契约，
其中自动轮询回退与 Gateway `source-poll` 规定已被本节取代。

## 扩容边界

当前 MySQL Outbox 适合单机到中大型社区的可靠异步基础。应先通过队列等待时间、claim 冲突、重试率和数据库负载测量瓶颈；只有 MySQL 已成为明确吞吐瓶颈时，再增加专用消息系统适配器。迁移时保留 `EventEnvelope`、Handler Registry、幂等键和投递状态机，业务模块不随基础设施重写。
