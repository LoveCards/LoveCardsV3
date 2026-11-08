# 通知策略目录收敛方案（2026-11-07）

## 结论

保持现有显式事件注册、数据库版本化策略和管理端 SDK 边界。只把管理端的中文展示映射移到已有的管理 API Resource，给现有 `GET/PUT /admin/communications/policies` 响应增加中文名称和影响说明；用跨层契约测试校验事件、当前策略和展示说明。此改造不改变策略执行、历史快照或表结构；策略目录 GET 使用 `communications.read`，策略 PUT 继续使用 `communications.configure`，两项能力正交，配置角色必须同时授予 read；管理端按读写权限分层展示。

## 三种真相各有边界

| 问题 | 当前权威来源 | 保持不变的原因 |
| --- | --- | --- |
| 哪些事件由谁处理 | `Platform/Eventing/Public/HandlerRegistry` | 显式注册参与 Release Handler Catalog 签名；不能扫描代码或数据库动态装载处理器。 |
| 当前哪些提醒开启 | `notification_policies` 与 `notification_policy_versions` | 管理员修改需要事务、CAS、冻结版本与审计；不能由静态代码替代。 |
| 管理员怎样理解一条规则 | 管理 API 的展示映射 | 中文和影响说明是管理界面文案，不参与事件投递和 Release Handler Catalog hash。 |

业务事件的 `event_type` 是连接这三者的稳定键。当前策略判断按 `event_type` 和冻结的 `policy_revision` 执行，**不按 `event_version` 分别开关**；现行目录必须保持每个 `event_type` 恰好一条规则。数据库虽允许不同版本或 `rule_key` 共存，当前快照与页面不支持把它们作为独立开关。本次用测试锁住现行不变量，不宣称已有版本级策略能力。

## 现状问题

`notification_policies` 当前七行来自迁移与后续管理员修改；`HandlerRegistry` 独立注册七个通知处理器；`apps/admin/pages/apps/operations/notification-policies.vue` 又以 `policyText` 保存七份中文名称和影响说明。前端映射不影响真实执行，却可能在新规则上线后漏配。此前三个旧事件名和实际发布名不一致，已通过向前迁移修正，说明仅靠页面文案不能证明策略命中了事件。

## 最小改造

通知处理列表与详情也显示业务事件名。两页不得再各自维护 `event_type -> 中文名` 映射；`AdminDeliveryResource` 复用本节 `AdminNotificationPoliciesResource` 的展示目录，仅在投递响应增加 `display_name: string|null`。未知事件返回 `null`，管理端回退显示原始 `event_type`；投递记录中的事件键、策略版本和执行结果保持原样。策略目录仍是这几个管理页面唯一的中文事件名维护点。

1. 在现有 `AdminNotificationPoliciesResource` 中维护唯一一份只读展示映射：`event_type -> {display_name, impact_text}`。`AdminNotificationPoliciesApplyResource` 复用同一映射，避免 GET/PUT 返回不同文案。映射不含 `enabled`、`locked`、默认值或处理器类名；这些继续由数据库/Registry 决定。Resource 只丰富响应，不改变审计日志中的策略 before/after 数据。
2. 两个响应的每条策略规则新增 `display_name: string|null`、`impact_text: string|null`。未知事件名返回 `null`，不丢弃规则，不让展示映射改变策略执行。两个 Resource 的 `policies.items` schema 和 SDK `NotificationPolicyRule` 同步声明字段，并按现有生成链更新 OpenAPI/SDK 契约。
3. 管理端删除 `policyText` 与旧事件名硬编码集合，直接使用 API 文案。字段为空时显示原始 `event_type` 和“此规则缺少影响说明，请检查策略目录”，并禁用该行开关；优先判断缺少说明，再显示锁定状态。锁定规则的文案必须依据真实 `enabled` 值，不能把异常的关闭状态称为“始终开启”。其他行和整页仍可读取。英文事件名与版本仍保留在“技术信息”折叠区。
   若保存遇到 `409/9016`，读取最新版本并显示发生变化的规则；在规则身份未变化时把用户草稿保留到最新基线上，要求再次审阅。若规则被删除、锁定或身份变化，保留原草稿并要求用户显式放弃后刷新，不能自动丢弃修改。
4. 增加可执行的契约测试：`test:backend` 使用真实 `HandlerRegistry` 和七条策略样本检查中文说明、未知行空值、GET/PUT 同形状、每个 `event_type` 唯一及 Resource 不改写锁定状态；隔离 MySQL 的集成/E2E 流程核对迁移后的当前策略行，不能把前者说成数据库验收。对当前七个内置通知事件比较 Registry、展示映射与现行数据库规则的双向集合；将来若保留只处理历史在途事件的 Handler，须显式列出例外并说明退役条件。管理端 E2E 覆盖 API 文案显示、未知行禁用和保存时只提交事件键及布尔状态。部署到 dev 后只读检查当前策略目录，不通过验收改动真实开关。
5. 将“新增可管理通知事件”的维护步骤写入 `NOTIFICATIONS.md`：发布事件和注册 Handler、添加前向迁移种子、补管理展示说明、更新契约测试。若事件只供内部处理且不生成站内通知，不进入本目录。

## 为什么不采用其他做法

- 不给 `HandlerRegistration` 加中文：Eventing 是所有业务事件的通用基础设施；文案变化不应改变签名 Handler Catalog，也不应让 Eventing 依赖通知管理界面。
- 不给策略表加中文列：增加迁移和历史文案治理成本，管理员也未要求编辑展示文案；策略快照只应冻结执行语义。
- 不自动扫描处理器或根据 Registry 自动写策略表：会绕过显式迁移、CAS、审计和 Release 校验，掩盖数据库与代码的部署偏差。
- 不新增通用事件目录服务、配置中心、后台文案编辑器或多语言框架：当前只有七条内置策略，新增抽象尚无真实复用点。

## 验收与发布顺序

1. 后端 Resource 与 SDK 契约先发布，旧前端可忽略新增字段；验证七行中文说明、策略版本和开关状态来自真实 dev API。两个字段只承载管理文案，不能进入策略快照或投递决策。
2. 管理端改为读取 API 字段，删除本地映射；验证桌面/手机列表、保存确认、未知规则回退、锁定规则不可改。
3. 执行 `npm run check`、`npm run build:admin`、隔离库 API/管理端 E2E；在原 dev 的 `6001` API 和 `20089` 管理端检查 `GET` 返回与当前策略版本一致，再做一次无需写入的页面验收。Nuxt dev 依赖的 SDK 构建产物也必须同步更新。

此方案无需新迁移。现有策略事件名修复迁移仍须保留，已应用迁移与历史策略快照不得改写。

## 最终验收记录（2026-11-07）

原 dev API 与管理端已验证当前策略版本 13 的七条内置事件均有中文名称和影响说明；通知处理列表复用同一目录，未知事件仍回退原始事件名。`communications.read` 可只读查看目录，`communications.configure` 仅控制修改操作；策略开关在验收中保持七条全启用。
