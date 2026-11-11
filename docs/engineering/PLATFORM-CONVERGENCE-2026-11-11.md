# 平台事件、通信与生命周期收敛实施设计

日期：2026-11-11。审查基线：`cc8b36fc`。

状态：设计已完成交叉复审，S1–S10 与部署命名空间切片已本地实现并通过主要质量门禁；dev 仅完成只读预检，尚未部署或完成运行验收。本文中的目标不等于当前代码已实现；末尾实施账本与测试证据是完成状态的唯一依据。没有真实数据库、故障注入及升级 E2E 证据的项目不得标记验收完成。

## 1. 目标与范围

保持模块化单体和现有 `app/modules` 目录。只消除已确认的错误行为、重复状态来源和历史债务，不建设第二套 Event Bus、Registry、Installer、Release Manager、Migration Runner、Backup Manager 或升级状态机。

- Eventing 管持久事件、投递、消费策略、领取/完成/重试门禁；Notifications、Messaging、Cards 等拥有业务事实和 Handler。
- Runtime 管前端通信选择、通信身份与跨能力就绪检查；Operations 页面是管理入口，不成为另一套状态所有者。
- Config 管共享基础设施连接；Redis 默认关闭，不因保存连接自动启动进程或改变缓存驱动。
- SystemLifecycle 管安装凭据、迁移、受审发行、运行资格、备份、激活与恢复。业务模块只提供迁移内容。
- Web API 继续同步执行 PHP 用例；异步副作用经 MySQL Outbox；SSE 仅提示重新读取 API。
- 不做 Go Worker、WebSocket、Redis Streams 或在线更新源。语言独立的健康识别协议不等于已实现外部 Worker 执行协议。

模块规则仍由 MODULE-ARCHITECTURE.md 维护；通知语义仍由 NOTIFICATIONS.md 维护。本文记录此次变更、依赖和验收，不复制第二套长期规范。各主文档随切片更新当前实现，并引用本设计中的尚未完成项。

## 2. 事实、状态与依赖

| 事实 | 所有者/存储 | 规则 |
| --- | --- | --- |
| 业务事实 | 各业务模块 MySQL 表 | 权限、可见性和 API 始终由业务模块裁决 |
| 已发生事件 | Eventing / outbox_events | 与业务写入同一事务，事件内容不可改写 |
| 每个处理器的投递 | Eventing / event_deliveries | 独立重试、claim token、租约、幂等；不以 Redis 为可靠账本 |
| 消费选择 | Eventing / eventing_consumer_state | scheduled/resident，互斥领取；不自动代班 |
| 通信选择 | Runtime / runtime_config_state | polling/realtime，不因故障自动改变 |
| 通知生成规则 | Notifications / notification_policies、notification_policy_versions | 按登记时冻结版本处理；系统强制规则不可关闭 |
| 主动提示开关 | Eventing / signal_policy_state | 每 signal_key 独立资格；不改变业务数据或通知生成 |
| Worker 存在证据 | Runtime / runtime_workers | 心跳在线不等于版本兼容或消费就绪 |
| 定时运行证据 | Eventing / scheduler_runs | 复用已有 release_id，记录实际执行身份，不以结束时间冒充目标版本 |
| 迁移与发行状态 | SystemLifecycle 原有账本 | 不删除历史 checksum，不用运行时 schema diff |

合法组合只有 scheduled+polling、resident+polling、resident+realtime。默认安装持久初始化 scheduled/none 与 polling；没有外部定时触发时事件处理不可报告正常。

运行期配置缺失、损坏或读取失败属于不可用，绝不伪造 polling 默认值。浏览器保留最后已知选择供说明，停止不可信调度并报告故障，只重试控制配置；恢复后按明确选择启动。配置的低频读取不是业务数据自动回退。

事件健康独立计算，通信仅引用事件就绪结果；CLI、后台、升级共享规则，不复制阈值。在线、兼容、就绪分别定义。polling 的浏览器配置读取不做 Redis/Gateway 等无关网络探测；开启实时使用完整强检查。所有探测都有时效和操作预算，不承诺即时发现所有断链。

## 3. 唯一事件链、策略与重试

业务事务 → EventRecorder → outbox_events + event_deliveries → 当前允许的消费者 → DeliveryProcessor → 业务 Handler → 独立的前端信号投递 → Redis Pub/Sub → Gateway SSE → SDK 业务 API。

### 3.1 领取与执行

所有 scheduled CLI/HTTP 和 resident 的 claim 共用同一事务门禁，核对策略、触发源、当前执行代码身份和升级暂停。健康探测不能代替领取授权。门禁只读持久事实，不在持锁事务中做网络探测。

领取互斥保证不是 exactly-once。租约过期不等于旧 Handler 已停止；完成/失败更新须匹配 token，业务副作用须幂等。长任务拆批、外部 I/O 设置连接和读取超时，执行预算与租约配合。需要破坏性 DDL 时必须额外取得真实无在途执行证据。旧版本消费者不具备新门禁时，部署必须停止旧进程及自动重启入口，不能假设新代码能控制旧进程。

### 3.2 人工重试

Administration 只调用 Eventing 公开用例，不直接更新投递表。重试保留事件、Handler、冻结策略、累计尝试和错误历史；操作者授权增加有界尝试预算并同事务审计。claim 后不能因旧预算已耗尽而立即拒绝本次授权。重试不绕过模式、升级暂停或执行身份；永久 skipped 信号不能复活。保留一份投递账本，不另建重试队列。

### 3.3 通知生成策略

输入必须匹配现行目录的完整 event_type/event_version/rule_key，拒绝未知、重复身份和非法布尔值。当前产品开关仍按 event_type，而非新增按版本分裂的开关。规则、冻结快照、revision CAS、before/after 与审计在同一事务提交，返回本次捕获的确定结果，不能提交后读取另一管理员的版本。

历史 policy_revision=NULL 可按明确的未配置兼容规则处理；非 NULL 的冻结版本缺失、损坏或查询异常不是未配置，必须产生可诊断的重试/延后，不得静默放行。安全、审核等通知仍遵守强制规则。清理不得删除仍被 pending、processing 或可恢复 dead 投递引用的快照。

### 3.4 主动信号开关

开关以信号登记/取得发送资格为边界，不倒推尚未生成通知的原业务发生时间。关闭阻止新资格；未获得发送资格的旧投递永久 skip；已获得资格的在途批次允许有界结束，网络中信号无法撤回。后台说明此窗口，不虚构瞬间全停。

资格按 signal_key 隔离，使用每键代次/关闭边界，不能用全局 revision 使其他业务积压失效。根信号记录资格，feed 续批继承；关闭时登记的根信号重开后不复活。通知冻结 revision 不得挪作信号资格。批次及重试全部核验原资格，不因重开获取新资格。

修改信号开关、通信模式、续批登记和结算必须审定统一锁序。涉及多个控制行时依次为升级/消费门禁、通信选择、信号策略、投递；仅需部分行时保持相对顺序。续批不得先锁 delivery 再反向取得通信锁。外部 publish 不放在数据库事务中；死锁重试仅限可安全重放的事务，不能重放不幂等外部副作用。

## 4. 注册目录与模块边界

业务模块以公开入口提供显式注册；组合根统一装配，Eventing Registry 只校验与查询。构建、Web、CLI、Worker、Scheduler、探测、测试使用同一确定性装配入口，不扫描目录/反射自动发现，不依赖数据库才能导出目录。

第一个切片保持 handler_key、event/version、queue、execution_mode、catalog JSON 与哈希完全一致。所有直接 new Registry 的入口一并迁移，防空目录。后续 execution_mode 退役必须迁移存量 pending/processing/dead，并在旧消费者停机后执行；不得只改枚举。

包完整性继续覆盖目录全部内容，包括文案。执行兼容指纹与展示内容解耦属于独立版本化协议切片，不能静默改变旧 handler_catalog_hash 含义。Release Builder 必须从待打包源码导出目录或拒绝异源构建，禁止 B 代码+A 运行时目录。

架构检查覆盖 use 和完整类名引用，并逐步登记可收缩的历史债务；检查通过不代替行为验证。不创建没有实际耦合问题的新抽象目录。

## 5. Redis、Gateway、身份和配置

### 5.1 统一配置

在现有 Config 中整组事务写入并 CAS，响应只返回本次已提交的脱敏快照与 revision。密码遮罩表示保留，不作为真实密码写回。提交后失效失败须明确已提交，不谎称数据库未写入。配置读取/提交/必要失效控制路径不得依赖被管理 Redis；显式使用 Redis 缓存时，关闭 Redis 前需切换部署缓存策略或保留数据库修复通道，不能自动回退 file。

明文与 TLS 都必须设置连接、命令读取与总体预算。Gateway 按整组配置指纹切换；版本变化断旧订阅，完成新订阅后才就绪。Redis 配置不启动任何进程。

### 5.2 站点隔离

稳定部署身份统一派生 Redis channel、缓存 key 前缀和 MySQL 命名锁。不能靠 Redis database 隔离 Pub/Sub；不能按密码、Release 生成身份。不是普通后台热改字段；克隆环境重新确认身份，迁移命名空间需暂停领取、结算在途并重启相关进程，避免新旧锁域并存。

### 5.3 连接协议

保留 SSE 与现有 Pub/Sub 提示通道，不新增可靠传输账本。发布成功仅说明传输设施接受；不代表浏览器收到或用户已读。断线重连补读业务 API，信息流提示用户点击刷新。故障不启动业务轮询。

Gateway 订阅连接增加有界存活检查，半断开超时撤销 ready、关闭 SSE 后受控重连。连接数、验票中名额、背压继续有界。公共连接地址与部署反代验收不能被内部端口健康代替。

一次性票据归属通信能力，绑定现有 Auth 会话/认证代次；消费时复核用户、会话、所选模式。已有连接按明确窗口重验或到期重连。JWT 正常轮换导致旧会话失效时，重新签票即可，不增加会话继承库。已删除通知 cursor_seed 查询耦合，内部兼容字段固定 null；外部 URL/SDK 操作名保留。票据模块已迁到 Operations/Runtime，经 Auth Public 会话能力复核状态。Gateway 强制内部连接 TTL（最多60秒，并扣除验证耗时）；已连接会话撤销窗口有界为60秒，须同步更新 Gateway。迁移 `20261111000004.sql` 添加现有表的会话绑定字段，旧无绑定票据拒绝。

当前孤立 Redis wakeup 尚未形成调用/订阅链，退役孤立代码与不准确说明；resident 仍按间隔领取 MySQL。这不是定时模式代班。后台无效的轮询退避参数必须退役或在唯一调度器内完整实现，不保留无效配置。

S10 本次切片只退役孤立的 DeliveryWakeup/Transport、未被浏览器调度器使用的 `polling_backoff_factor` 与 `polling_max_interval_seconds`，以及已无探测实现的 `require_supervisor` 残留。同步删除管理表单和 SDK 字段；新安装种子不再写入，通过新增迁移清理存量设置 JSON 并递增 revision，不修改历史迁移。`execution_mode` 仍参与现有目录指纹、筛选与存量任务，暂保留；其退役须在 S9/S11 后另做旧消费者停机和存量迁移验收。

## 6. 安装、升级与恢复

### 6.1 安装

完整 schema.sql + 默认 seed.sql → CleanInstallReceipt → 迁移账本。新装 root 种子密码为不可登录占位值，finalize 要求向导提交新密码并由 Auth 公开能力写入哈希；同时校验实际持久密钥、签验能力、凭据与账本，再原子生成安装锁。失败不写锁，重试幂等。pass 不得绕过完成校验。

### 6.2 阶段运行资格

继续由 UpgradeCoordinator 编排。SystemLifecycle 提供受控的阶段运行资格，供消费者领取、健康检查及恢复复用。完整模式仍要求 complete-only SchemaIdentity；pre-contract 资格只允许当前计划/目标 Release/checksum 集合完全匹配、所有前置 verify 有效且仅剩 contract/waiting。

迁移失败、checksum 漂移、计划终止、目标变化、Pointer 离开目标或 Contract 开始但未完成时撤销相应资格。只有 Redis/Gateway/Worker 等健康失败且目标仍有效时，保留目标受限启动资格以修复依赖；计划仍失败，不能执行 Contract。Worker 不得永久缓存资格。

流程继续使用备份、前置迁移验证、激活、新版本健康、独立 Contract 批准。Contract 前暂停全部领取并确认在途安全；失败保持暂停。Contract 后先验证最终身份，再只允许匹配目标的消费者受控运行，完整事件/通信健康通过后才宣告成功。新增步骤必须显式升级计划版本，旧持久计划不能静默重解释。

scheduled 台账写入已有 release_id 并返回实际启动证据；结束时间晚于激活不能证明新代码执行。与 resident 共用兼容判定，保留不同证据形式。

### 6.3 失败重试与长步骤

失败迁移通过当前 UpgradeCoordinator 显式重试当前计划/迁移/phase；保留 checksum、cursor、累计 attempts。管理员对失败计划再次 advance，或部署操作者明确执行 upgrade advance/run，即授权本次当前失败步骤的一次重试；RUNNING/PENDING 推进不获授权，失败账本会停止计划，后续调用不继承授权。HTTP 与 CLI 都在同一 Lease 内、任何状态复位前写入现有 AuditLogger，捕获调用前计划/步骤及真实迁移身份；审计失败不执行。账本和计划状态转换继续受 CAS 与权限保护，独立迁移 CLI 不绕过活动升级计划。完整当前契约见 SYSTEM-LIFECYCLE.md 的 S3 段。

900 秒 Lease 的过期不证明旧 SQL/备份结束。长步骤需有界分批/超时与执行级排他证据；无法确定执行结束时保持可诊断待处置，不允许第二执行者仅凭 TTL 重放危险副作用。不增加第二状态机。

### 6.4 恢复

Contract 前可恢复 Release pointer；数据库不自动回滚。恢复结果分别表达 pointer restored 与 service recovered。扩展后的账本可能阻止旧代码启动；只有受支持兼容窗口/桥接版本经审定后才能恢复其事件处理资格，不能删除新增账本伪装回滚。Contract 开始后禁止仅回切旧代码。

## 7. 数据库退役与保留规则

逐表源码证据、数据映射阻断条件与留存验收见 [数据库治理审计](DATABASE-GOVERNANCE-2026-11-11.md)。

退役候选：good、images、system、permissions、role_permissions、role_capabilities、card_comment_pins；旧迁移仍依赖其中部分表，必须保留历史 checksum，新增迁移在历史转换完成后退役。users.roles_id、files.ref_type/ref_id、cards.tags 先核实权威来源/兼容投影，不凭 grep 直接删除。

每表记录所属模块、唯一写入入口、权威或派生属性、保留/重建规则。计数、当前规则和不可变快照可以合理并存。outbox 与 delivery、通知与私信、迁移账本与升级步骤不合并。

清理复用当前消费入口，不增加 Scheduler 服务。已完成投递与父事件须按引用顺序清理；pending/processing/dead 可恢复记录、被引用策略快照不得按时间盲删。已用/过期票据、旧心跳、定时记录、审计按各自语义保留，不能统一 TTL。具体天数由数据规模及审计要求确定，未确定前不执行破坏性清理。

## 8. 验收与发布

| 层级 | 必须证明 |
| --- | --- |
| 行为测试 | 配置失败不回退；完整策略身份；原子审计；重试真正再次执行；非 NULL 快照不可读不放行；信号重开不复活旧资格；会话失效拒票；安装缺密钥不落锁 |
| 真实 MySQL | 双消费者互斥；切模式与业务/续批并发；过期租约旧 token 不能覆盖；配置整组无混合快照；审计失败回滚；重试额度/冻结决策持久正确 |
| Redis/Gateway | 两站隔离；明文/TLS黑洞有界；订阅半断开；版本切换；撤销会话；容量/背压；故障不业务轮询 |
| 升级 E2E | 三种合法组合含 Contract；旧 cron 跨激活不能代答；旧消费者失权；迁移失败可受控重试；Contract 前后健康；长步骤排他；Pointer 恢复语义；旧计划兼容 |
| 数据一致性 | 新装与支持的旧版本迁移终态表/列/索引/FK一致；代表性授权、文件、点赞数据转换正确；结构相同不能替代数据验证 |
| 工程 | 受影响 PHP lint、根 npm run check、管理端构建、SDK契约与需要的浏览器 E2E；失败/未运行明确记录 |

dev 验收前查 git status/log，保留服务器既有 TEST_REPORT.md；密码只经授权环境注入，不入库/日志；不改防火墙、不碰生产、不重启 6001/20080/20088/20089、不合并 PR。数据库/E2E 写入使用一次性隔离环境。部署行为与本地测试结果分开报告。

## 9. 实施账本

状态含义：待实施 → 开发中 → 本地通过 → 集成通过 → dev 验收通过。任何一项缺证据不得跨级。

| 切片 | 内容 | 初始状态 | 前置 |
| --- | --- | --- | --- |
| S1 | 配置不可读不回退；选择与健康解耦 | 本地通过：后端/主题行为、Gateway 测试；dev 未验 | 无 |
| S2 | 通知策略身份、冻结版本、事务审计 | 本地通过：身份、冻结故障及事务基线；dev 未验 | 无 |
| S3 | 安装终结密钥和初始 root 凭据；升级内失败迁移重试 | 本地通过：Auth SigningKeys、不可登录 root 种子与安装终结设密、升级内一次重试与 HTTP/CLI 执行前精确审计；InstallFinalizeKeys、InitialRootPasswordBaseline、InstallBaseline、UpgradeCoordinatorBaseline、BehaviorBaseline、UpgradeAuditHosts；隔离升级 E2E 已通过，dev 待验收 | 无 |
| S4 | 事件人工重试与唯一写入口 | 本地通过：Eventing Public 唯一重试入口、授权额度迁移及行为基线；dev 未验 | S2 |
| S5 | Config原子组、Redis有界I/O与隔离、Gateway存活 | 本地通过：组 revision/CAS、Redis I/O 预算、Gateway PING/PONG 与部署命名空间；新安装持久 ID、旧安装稳定派生；dev 未验 | S1 |
| S6 | 会话票据与通用通信归属 | 本地实现与行为测试通过；迁移/真实浏览器待 dev 验收 | S1、S5 |
| S7 | 信号资格/续批/锁序 | 本地通过：独立代次、发送前token/lease/五秒窗口、续批继承与锁序；19项SignalEligibility、feed/recovery/outbox基线；本地MariaDB12.3隔离E2E含跨连接控制锁、关闭重开、部分DDL重放与旧提示skip；dev/实际并发负载待验 | S2、S4 |
| S8 | 唯一注册装配、同源构建、边界检查 | 本地通过：目录哈希不变、构建异源拒绝、架构检查；真实发行 E2E 待验 | 固定现有目录快照 |
| S9 | 运行资格、升级暂停/最终健康、恢复与长步骤 | 本地实现及资格/编排行为基线通过；本机真实MariaDB会话锁、冷启动备份校验和隔离升级 E2E（含 Contract SQL probe）通过；dev 历史 Worker 停机待验，未知副作用须人工受控处置 | S3、S8 |
| S10 | 旧执行概念/无效配置/孤立实现退役 | 部分本地通过：孤立 wakeup、两项无效轮询参数和 Supervisor 残留退役；控制面 52 断言、主题 runtime 19 用例、管理通信 5 个 mock E2E、管理端 typecheck 通过。新增迁移 20261111000007 待数据库验收；execution_mode 保留待独立迁移 | 存量兼容核验 |
| S11 | 表/字段退役及保留治理 | 源码与 dev 只读盘点完成，见 DATABASE-GOVERNANCE-2026-11-11.md；新装 seed 已停止写入旧 permissions、role_permissions、system，静态基线与隔离数据库 schema 一致性 E2E 通过。旧表非空数据的逐行映射和授权核验未完成；未执行 DROP/清理 | S9 dev 完整验收、数据迁移核验 |
| S12 | 全链路验收、dev报告与人工使用指导 | 本地根 check、管理端构建、受影响 PHP lint、schema 一致性及升级 E2E 已通过。2026-11-12 本机隔离 SDK 集成 169/169，通过后管理端浏览器 smoke 2/2；管理端全量浏览器最终 178 首跑通过、1 项因 Nuxt 文档请求超时重试通过，0 最终失败（21 分钟）。导航 5 项解构错误已修；先前单独定向 18/18 通过。TEST_REPORT.md 的原有未提交内容曾被首次集成运行覆盖，已停止后续报告写入，原文尚未恢复，部署须排除该文件。dev 仍待实际部署与运行验收。 | 所有涉及切片 |

设计复审不等于缺陷已修复。每项完成时在本表补代码、测试和限制；长期行为移入对应主规范，避免积累相互矛盾的文档。
