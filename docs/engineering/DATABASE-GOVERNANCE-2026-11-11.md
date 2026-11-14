# 数据库权威来源、退役与保留治理

本次先对源码、历史迁移和 dev 数据做只读审计，再对四张非空旧表做逐行核验。数据量本身不能代替语义映射。新安装的 `seed.sql` 已停止向 `permissions`、`role_permissions`、`system` 播种；当前开发分支新增受审 S11 候选迁移，仅允许四张表与已核对的历史种子完全一致时进入 Contract。它尚未在 dev 执行 DROP。当前设计边界见 [平台收敛方案](PLATFORM-CONVERGENCE-2026-11-11.md)；破坏性变更只能经现有 UpgradeCoordinator、Backup Gate、停消费者和独立 Contract 批准推进。

2026-11-11 dev 预检：分支 `dev-sse-preview`、HEAD `cc8b36fc`，已有未提交 `packages/sdk/test/TEST_REPORT.md`；`system_upgrade_steps` 44 条均 complete，44 个迁移源文件 checksum 与账本一致。候选表行数：`good=0`、`images=0`、`system=11`、`permissions=66`、`role_permissions=117`、`role_capabilities=152`、`card_comment_pins=0`。另有 `users=31`、`files=8`、`cards=31`、`event_deliveries=90`，其中 `users.roles_id` 非 NULL 2 条、`files.ref_id` 非 NULL 3 条。此结果明确阻断旧表/字段的直接删除；尚未核验非空记录的逐行转换及授权等价性。

2026-11-12 补充只读核验：dev 的 `system` 11 行、`permissions` 66 行、`role_permissions` 117 行与原始 `data.sql` 种子逐字段 SHA-256 指纹完全一致；`role_capabilities` 的 152 个 `(role_id, capability)` 与 `20260727000001.sql`、`20260728000001.sql` 生成集合一致。比较时 `system.value` 只计算哈希，未打印明文；`role_capabilities.id` 不参与集合比较，因为 `INSERT ... SELECT` 的自增顺序不是业务语义。`role_permissions` 中 14 条关联不到 `permissions`，其 `permission_id` 为原始种子中的 67–80，属于已存在的孤儿记录。四张表都无运行时读写；当前授权以 `role_grants` 为准。`role_capabilities` 只在历史迁移时一次性回填到 `role_grants`，此后不是双写投影，旧行不得重新灌回以免复活已撤销授权。对 dev 这些确认为原始种子的旧行，退役不改变当前授权；其他实例的自定义旧数据由 S11 前置校验明确阻断，必须先另行审定转换或归档。该结论不推广到 `good`、`images`、`card_comment_pins` 和兼容字段。

## 1. 七张旧表的退役判定

对 `app` 中直接 Db 引用、模型与模块入口的审阅未发现以下七表的当前运行时读写。该结论不能证明部署侧脚本或未迁移数据不存在，不能仅凭代码无引用删除。

| 候选表 | 所属与当前权威来源 | 历史迁移引用 | 退役前必须验证 | 当前处理 |
|---|---|---|---|---|
| `good` | Content/Likes；当前点赞事实在 `likes` | `20260727000001` 显式转换 good→likes，校验重复与旧字段冲突 | 旧迁移 complete/checksum 一致；每条源记录按 card/ref_id/uid 与 likes 对账，aid/ip/created_at 冲突不能吞掉；需考虑后续业务删除，不能重新全量补写已删点赞 | 保留；经升级前备份、差异处理和 Contract 批准后另写退役迁移 |
| `images` | 旧内容媒体关联；当前 Files + `file_references`，卡片还存在旧 URL 表达 | 无已登记的 images 转换 SQL | 记录 aid/pid、URL、用户、软删除状态；确认文件对象可达、归属和公开性映射。仅有 URL 无法自动猜出存储驱动或文件身份 | 映射未明确或数据未核实即阻断 DROP |
| `system` | 旧站点键值；当前 Platform/Config 的 `configs` 与部署配置 | 无已登记的 system 转换 SQL | dev 11 行均为原始种子；其他实例存在非种子值时须人工审定配置归属、类型和秘密语义，不能自动覆盖新配置 | S11 候选 Contract 只允许精确原始种子；未知值阻断 |
| `permissions` | 旧路由权限；当前 Platform/Rbac 的 capability + scope 模型 | 无已登记的逐路由权限转换 SQL | dev 66 行均为原始种子；自定义 slug/path/method 不能猜测为现 capability/scope | S11 候选 Contract 只允许精确原始种子；未知值阻断 |
| `role_permissions` | 旧角色→路由权限；当前 `role_grants` | 无已登记转换 SQL；当前有到 roles 的外键 | dev 117 行均为原始种子，其中 14 行原本即指向不存在的权限 67–80；自定义关联须先审定 | S11 候选 Contract 在同一受审多表 DROP 中退役关联与 permissions；未知值阻断 |
| `role_capabilities` | 旧无 scope 授权；当前 `role_grants`，目录在 `capabilities` | `20260727000001`、`20260728000001` 建表/种子，`20260804000001` 规范 capability 后转 role_grants | dev 152 个角色/能力对均为历史迁移原始集合；旧表此后不再同步，不能重灌以免复活已撤销权限 | S11 候选 Contract 只允许历史集合；未知值阻断 |
| `card_comment_pins` | 旧卡片置顶关系；当前 `cards.pinned_comment_id` | `20260824000002` 从 comments.is_top 汇入；`20260824000003` 汇入 cards 指针 | 迁移只处理可见、未删除卡片/评论；现时指针可能已被用户修改。核验历史转换及异常记录，不能盲目要求两表当前完全相等或覆盖新选择；pinned_by/pinned_at 如需追溯须归档 | 保留；旧数据处理决定与备份验收后另行退役 |

S11 已在候选分支把 `system`、`permissions`、`role_permissions`、`role_capabilities` 从完整 `schema.sql` 移除，并新增 `20261112000001.sql` 的受控退役 Contract 与 `20261112000002.sql` 的独立过程清理 Contract；`good`、`images`、`card_comment_pins` 仍在目标 schema 中。其他候选获准退役时，也须在同一切片修改新装 schema 与新增迁移，保持干净安装和历史升级结果一致；历史 SQL、checksum 和迁移账本不改写。

干净安装与旧版本升级的**表、列、索引和外键**须一致；结构一致性测试不等于业务数据一致。`SchemaSeedBaseline` 阻止遗留种子回流，隔离数据库 `test:schema:e2e` 从旧版数据和 49 条已完成迁移出发，经真实签名包 UpgradeCoordinator、Backup Gate、Contract 批准执行 S11，再与干净安装比较终态。测试还覆盖同名自定义过程、未知旧行及批准后数据变化的阻断与显式修复重试。其他安装实例的自定义配置、授权或权限若不能精确证明可退役，前置校验必须失败，不能让表结构相同掩盖数据丢失。

## 2. 不能误删的兼容字段与派生值

| 字段 | 实际上下游 | 权威与收敛建议 |
|---|---|---|
| `users.roles_id` | Auth `ThinkOrmUserRepository::create/map` 写/读 `user_roles`；User `updateAny` 先移除 roles_id 再更新角色关系；响应由 user_roles 派生 roles_id。旧 DB 列仍在 Users 模型 schema；`20260804000001` 依赖它回填 | 权限真源为 `user_roles`。数据库列可作为后续候选，但必须先验证旧非空值已转换、测试去列后的 ORM；公开 API 的 roles_id 是当前有效字段，不能随 DB 列一起删除 |
| `files.ref_type/ref_id` | `UploadFile::execute` 仍直接写；`ThinkDbFileReferenceRepository::replace` 将首引用同步到该列；查询筛选读取该列；Files Public 与 Cards 媒体查询在无 ledger 时读取旧关联 | 属于正在使用的兼容投影与旧记录入口，尚未彻底单一来源。`20260819000002` 已回填旧关联，但之后上传仍可产生仅旧列关联。先统一登记写入、消除读回退并对账，再决定是否保留高效投影；目前不删 |
| `cards.tags` | Cards 创建/更新同时写 JSON ID 和 `tags_map`；hydrate 直接用 JSON 输出 tags，topics 从 tags_map/标签表读取 | 当前是实际持久投影，不是无引用残留。两种查询需求可并存，但需同事务维护与不一致检测；以后若单真源转 tags_map，需保留 API tags 形态、批量加载、审核可见性和编辑语义 |

代码证据主要位于 `Platform/Auth/Infrastructure/ThinkOrmUserRepository.php`、`Platform/User/Service/Users.php`、`Platform/Files/Application/UploadFile.php`、`Platform/Files/Infrastructure/ThinkDbFileReferenceRepository.php`、`Content/Cards/Service/Cards.php` 与 `Content/Comments/Service/Comments.php`。

## 3. 留存按数据用途制定，不共用统一 TTL

| 数据 | 必须保留的边界 | 可清理的前置条件 |
|---|---|---|
| `outbox_events` / `event_deliveries` | pending、processing、可恢复 dead 及它们的父事件不可删；保留事件/Handler 去重和重试诊断依据 | 确定成功/永久跳过的保留窗口和业务重放窗口；先结算或清理子投递，再确认父事件无剩余引用。delivery→outbox 有 ON DELETE CASCADE，禁止先删父事件让数据库隐式删除积压 |
| `notification_policy_versions` | 当前 revision、pending/processing/可恢复 dead 引用的冻结 revision 必须保留 | 同时核验所有引用与审计要求；不能按创建时间独立 TTL 清理。冻结快照缺失会导致处理失败 |
| `notification_policies` / `signal_policy_state` | 当前规则、每键信号资格代次是控制真源 | 不因低使用率/旧时间清除；与不可变版本快照用途不同 |
| `notifications` / `messages` | 用户可见业务事实，已读不等于可删除 | 由通知/私信产品留存与用户删除规则处理，不能套用事件队列完成 TTL；去重记录清除需先排除仍可重放的来源 |
| `stream_tickets` | 未过期有效票据不可清除；已消费票据在安全追溯窗口内保留 | 到期/消费终态、固定 UTC 时间与并发消费测试通过后批量清理，不能影响正在校验票据 |
| `runtime_workers` / `scheduler_runs` | 当前活跃/最近的运行身份和升级健康所需证据必须可查询 | 按进程诊断历史需求保留；心跳过期只表示不健康，不自动代表记录可删除；运行未结束记录不能按开始时间盲删 |
| `auth_sessions` / `security_challenges` | 可续期会话、撤销窗口、挑战消费/防重放与安全审计证据 | 由 Auth 制定终态和保留时间，不能仅按 token_expires_at 删除仍可续期会话 |
| `audit_logs` / `system_*` / 迁移账本 | 操作追溯、恢复、备份、当前和可恢复计划以及历史 checksum | 按审计/备份政策独立制定；迁移账本不按时间删除；实际备份文件与台账必须协调，不宣称 Release pointer 回切能恢复数据库 |

当前尚无覆盖上述账本的统一留存实现，本次没有新增清理进程或隐含自动删除。后续清理按现有消费入口调度，各模块提供自己的保留判定；未确认时间窗口、重放语义和数据规模之前只做只读统计。

## 4. 必要验收与阻断条件

1. 在隔离数据库重放受支持旧版本及有数据的迁移样本，验证 schema 与新装一致，并验证数据内容、权限和关联，而不只比较 CREATE TABLE。
2. 覆盖空表、非空未转换、重复、权限冲突、媒体 URL 无法映射、软删除记录、置顶已变更、角色已撤销和投影不一致；不能用 INSERT IGNORE 把语义冲突伪装为迁移成功。
3. Contract 前验证 Backup Gate、旧消费者停止/隔离和活动任务可恢复性；每个待删来源必须有完整转换证据或经批准可恢复归档。
4. 留存测试用固定时间，覆盖领取与清理并发、dead 人工重试、冻结策略引用、当前规则、续期会话和父事件级联删除；重跑同批应幂等，失败不前移 cursor。
5. 使用现有手动重试、迁移账本与 UpgradeCoordinator，不增加另一套归档或迁移状态机。dev 只读数据盘点、实际备份恢复和升级 E2E 完成前不得声称已具备删除资格。

## 5. 历史 SQL 与跨平台 checksum

本次检查 HEAD 中 44 份历史迁移：规范换行后的内容均未修改，`git diff --name-only -- apps/backend/database/migrations` 没有已跟踪 SQL 变更；本轮只新增迁移。

但当前 Windows checkout 的 `core.autocrlf=true`，41 份历史 SQL 的工作树原始字节与 Git LF blob 不同。`MigrationDescriptor::fromSource` 对原始源码做 SHA-256，因此“Git 内容未修改”不等于“本机包 checksum 与 Linux 已部署账本相同”。发布必须使用确定的源码字节，并对照现网已应用 checksum。建议单独约束迁移 SQL 的发布换行和来源；不得直接改 checksum 算法、更新旧账本或忽略冲突以掩盖差异。本次新增 `.gitattributes`，将 `database/migrations/*.sql` 固定为 LF checkout，并把当前 Windows 工作树中的 41 份旧迁移从 CRLF 恢复为 Git blob 的 LF 字节；未更改旧迁移的 Git 内容或账本。跨平台一致性仍需用目标部署的实际账本 checksum 验证，不能仅凭本地规范化推断现网匹配。
