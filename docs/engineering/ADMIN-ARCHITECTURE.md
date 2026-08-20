# 管理端架构规范

## 边界

`apps/admin` 是 Nuxt SPA。它只通过 `@lovecards/sdk` 调用后端，不直接导入 Axios、fetch 或后端源码。

```text
Route/Page -> Pinia or local state -> @lovecards/sdk -> /api -> ThinkPHP
```

- SDK 维护 HTTP 方法、请求参数、响应类型和错误转换。
- Pinia 只保存跨页面共享的会话、缓存或全局 UI 状态。
- 页面负责当前路由的用例编排、列表、筛选和局部状态。
- 组件负责局部交互；不创建重复 SDK Service。
- `utils` 只放无状态纯函数；第三方 UI 通知必须经过 `useAdminNotifier`。

## 目录约定

```text
apps/admin/
├─ pages/              Nuxt 文件路由和页面入口
├─ layouts/default.vue 后台唯一布局
├─ middleware/         Token、角色和页面 capability
├─ config/navigation.ts 菜单、页面和 capability 唯一清单
├─ lib/api.ts          SDK 客户端唯一装配点
├─ stores/             共享状态
├─ components/shared/  公共表格和通知桥接
├─ components/<module>/模块专属组件
├─ composables/        有状态的前端组合能力
└─ utils/              纯函数和静态 UI 选项
```

页面允许直接调用 SDK。只有数据被多个页面或兄弟组件共享时才提取 Store；不要为了目录统一把每个请求都包进 Store。

## 生命周期

1. `app.vue` 挂载 `NuxtLayout` 和 `NuxtPage`。
2. 全局鉴权 middleware 检查 Token、当前用户、角色和 capability。
3. 初始化 middleware 启动必要的共享数据加载。
4. `layouts/default.vue` 渲染导航、通知和页面插槽。
5. 页面和组件通过 `useApi()` 调用 SDK Resource。

前端 capability 只负责导航和页面体验；后端 RBAC 始终是最终安全边界。

## 动态 schema

Storage、Sender、Captcha 和 Theme 的驱动配置使用 SDK 的 `FieldDefinition`、`JsonObject` 和 `JsonValue`。动态字段不能伪造固定 TypeScript 字段，也不能退化为显式 `any`。

数据库 JSON 的序列化和响应归一化属于后端 Resource/SDK 契约。页面不再为同一业务字段维护字符串和数组两套格式。

## 错误和通知

```text
SDK hook / Page -> useAdminNotifier -> notificationStore -> NotificationNotifier -> Vuetify
```

- 401：清除会话并跳转登录页。
- 403、网络错误、超时和 5xx：统一通知。
- 表单校验和可预期业务错误：由当前页面显示。
- 业务组件不得直接调用第三方 `useNotifier()`。

## 本轮六个管理域

管理端没有为社区成员的草稿、收藏、历史或私信正文提供任意浏览入口。后台只提供治理、账号安全、投递、审计、运行时和隐私安全的最小运维面：

| # | 管理域 | 页面 | SDK / 权限 | 行为边界 |
| --- | --- | --- | --- | --- |
| 1 | 举报治理 | `/apps/governance/reports`、`/:id` | `reports.listManaged/getManaged/transition`；`reports.read/manage` | 按状态分页、查看证据快照与事件、填写原因转换状态 |
| 2 | 账号恢复与 Session | `/apps/security/recovery`、`/apps/users/:id/security` | `users.recovery/security/sessions/revokeSession/revokeSessions`；`users.recovery.read`、`users.security.read`、`users.sessions.revoke` | 恢复挑战只读；敏感字段遮罩；单 Session/全部撤销必须确认原因 |
| 3 | 通知投递 | `/apps/operations/deliveries` | `operations.deliveries/retryDelivery/notificationStats`；`communications.read`、`operations.retry` | 查看 Outbox 状态、失败摘要与 attempts；只对允许状态手工重试 |
| 4 | 审计日志 | `/apps/operations/audit` | `operations.auditLogs`；`audit.read` | 以 resource、request/correlation id 追踪；payload 只显示后端白名单 |
| 5 | 运行时与升级 | `/apps/system/runtime`、`/apps/system/upgrade` | `operations.runtime/migrations/runMigrations/upgrades/...`；`operations.read/migrate`、`system.update/update.contract/recover` | Facility/Worker 状态；签名 ZIP、单步推进、Contract 独立批准、pointer 恢复 |
| 6 | 社区聚合 | `/apps/community/insights` | `community.insights`；`community.insights.read` | 仅显示草稿、收藏、历史的总量和参与人数，不返回成员私密明细 |

旧 `/apps/updata` 只作为兼容入口映射到系统升级权限，不继续承载另一套升级实现。

升级页必须按持久状态机渲染，而不是本地模拟进度：上传使用包含 `file` 和非空 `reason` 的 `FormData`；每次 `advanceUpgrade` 只代表一个 Step、一个 migration phase 单元或一个 backfill batch。页面根据 API 返回的 step state 显示继续推进、等待批准、失败或完成。Contract 批准使用独立 `system.update.contract`，恢复使用 `system.recover`。

当前 `recoverUpgrade` 的后端语义是回切旧 Release pointer、更新 Release 账本并执行可用的 reload/health-check；它不自动导入数据库 SQL，也不调用文件 ZIP 恢复原语。管理端文案不得承诺“整站数据自动回滚”。上传超过 PHP 限制时后端会返回 413 及 `post_max_size`/`upload_max_filesize` 诊断；若请求被 Nginx 更早拒绝，则展示网关响应和运维提示。

### 高风险操作

升级推进、Contract、恢复、投递重试、举报转换和 Session 撤销统一使用显式 action guard 与确认对话框。确认文本/原因由页面收集，真正的 capability、状态前置条件和审计由后端执行；隐藏按钮不能代替后端授权。

### 页面状态

上述页面使用 `AdminPageHeader`、`AdminAsyncState`、`AdminStatusChip`、`AdminActionGuard`、`AdminDangerActionDialog`、`AdminAuditTrail`、`AdminResourceLink` 等共享组件，保持 loading/error/empty/disabled 状态一致。固定导航唯一来源是 `config/navigation.ts`，页面 capability 必须同时出现在后端 Route access、`CapabilityCatalog`、系统角色种子和导航契约中。

## 验证门禁

管理端改动至少运行：

```bash
npm run typecheck:admin
npm run build:admin
```

跨后端契约或真实页面行为时，还要运行 `npm run check`、SDK 集成测试和隔离数据库管理端 E2E。架构检查必须阻止旧 API 目录、直接 transport、重复布局和重复插件重新出现。

`npm run check:admin-contracts` 会交叉检查 171 条路由中的管理授权、capability catalog、system role、迁移授权种子；2026-08-20 的结果为 217 个管理授权引用、23 个 navigation/fixed entries。浏览器 E2E 为 28 项通过（3 项认证场景、25 个固定/动态页面路径），并覆盖登录、未认证跳转和错误登录反馈；它不替代每项高风险动作的后端状态机测试。

同日根 `npm run check` exit 0，Admin typecheck 通过。系统升级的独立真实 HTTP E2E 在一次性数据库/实例根中通过：它在临时复制的 Release source 中加入 E2E-only Contract migration，签名 ZIP 上传后经 21 次单元推进，验证提前批准被拒绝、`contract_migrations` waiting、独立批准持久化、Contract SQL、激活、pointer、Release header 与 health。当前生产迁移目录仍没有 Contract phase；恢复正向约束由专项状态机测试覆盖，这次演练也不代表数据库或文件已经支持自动恢复。
