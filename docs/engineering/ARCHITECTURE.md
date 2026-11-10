# 架构约束与目标

## 总体判断

当前代码以模块化单体为边界：Route/Transport 适配 HTTP，简单业务使用 Service，复杂状态与副作用使用 Application/Domain/Contract/Infrastructure。目标不是把所有模块套进同一层级模板，而是让依赖方向、事务边界和可执行契约保持一致。

```text
Admin / Theme
      |
      v
@lovecards/sdk  --->  HTTP API
                         |
                         v
Route Meta -> Controller -> Validate -> Business -> Resource -> ApiResponse
                                      |
                                      v
                          Model / Infrastructure adapters
```

## 架构约束表

| 区域 | 职责 | 允许依赖 | 禁止依赖 | 自动化状态 |
| --- | --- | --- | --- | --- |
| `apps/admin` | 管理界面与交互 | SDK、UI 库 | 后端文件、SDK 源码相对路径 | 已检查 workspace 依赖 |
| `apps/default-theme` | 用户前端与默认视觉实现 | SDK、UI 库、目录内运行资产 | 后端文件、SDK 源码相对路径、`ui/` 设计参考 | workspace 检查 + 前端 check |
| `packages/sdk` | HTTP 契约、类型、客户端 | 通用第三方库 | 任一 app | 已检查包级副作用 |
| API Controller | 请求解析、校验、响应映射 | Validate、Service、Resource、DTO | Model、基础设施细节 | 已检查 Controller -> Model |
| Validate | 请求字段验证 | ThinkPHP Validate、场景规则 | 响应结构、业务流程 | 人工维护，逐步补测试 |
| Resource | 响应内部数据契约 | 数组映射、只读 DTO | 请求验证、业务流程 | 人工维护，逐步补测试 |
| Simple Service | 简单业务聚合与事务 | Model/Db、模块内帮助类 | Controller、Middleware、Request | 已检查 Service -> Controller/Middleware |
| Application | 复杂模块单个用例编排 | Domain、Contract | Controller、Middleware、Model、Db、Infrastructure | 迁移切片中逐步建立 |
| Domain | 实体、值对象、业务策略 | 领域内部 | HTTP、ORM、JWT、缓存、框架 | 迁移切片中逐步建立 |
| Contract | 业务内部 Port | 值对象、接口定义 | HTTP 请求/响应契约、框架实现 | 迁移切片中逐步建立 |
| Infrastructure | ORM、JWT、缓存、邮件等适配 | 第三方库、Contract | 业务流程编排 | 迁移切片中逐步建立 |
| Installer Host (`app/system`) | 首次安装的 HTTP 适配 | Installer、SystemLifecycle 入口 | 独立迁移/升级状态机、运行时 schema diff | 已检查 Host 仅含 Controller 与安装输入；JWT/RBAC 直接调用是已知债务 |
| SystemLifecycle | 迁移账本与 Release 编排 | 本模块 Contract/Infrastructure、其他模块 Public/Contract | 其他业务模块 Service/Model/Infrastructure | 已检查跨模块 import 边界 |
| Eventing | Outbox、Handler Catalog、领取租约和事件消费策略 | 本模块 Application/Contract/Infrastructure、其他模块 Public | 业务 Controller、通信模式细节、第二套队列账本 | 事务内策略门禁与双领取冲突需专项测试 |
| Operations/Runtime | 通信模式、依赖探测和配置编排 | Eventing Public、Config Public、运行时探测 Port | 业务模块内部实现、假进程健康、自动切换策略 | 组合门禁与状态 API 需专项测试 |
| `shared` | 无业务归属的跨模块技术能力 | Infrastructure、Transport | modules、api/frontend/system、common | 架构检查覆盖 |

约束原则：外层可以依赖内层定义的抽象，内层不能知道外层实现。模块之间“不依赖”不是互不调用，而是调用稳定契约，不引用对方内部结构。

## Business 两档结构

默认使用简单档：

```text
Controller -> modules/<Module>/Service/<Service>.php -> Model/Db
```

简单档适合配置、只读统计、普通 CRUD 和没有替换实现的业务聚合。它不是迁移遗漏，而是明确选择更扁平的结构。

只有出现下面任一硬触发器时，才升级为复杂档：

- 第三方 SDK、多驱动或可替换实现。
- 明确状态流转或跨用例复用的不变量。
- 数据库与外部副作用需要一致性、补偿或可独立测试。
- 多个入口共享流程且已经重复或语义漂移。
- ORM、缓存、HTTP 耦合导致核心规则无法独立测试。

复杂档目录放在模块内部，不创建顶层 `api/application`：

```text
modules/<Module>/
├─ Application/
├─ Domain/
└─ Contract/

modules/<Module>/Infrastructure/
```

不要为了“看起来统一”给每个模块都创建四件套；新抽象必须移除真实耦合点。

Auth 的 `UserRepository` 返回只读 `AuthUser`，不会把 ThinkORM Model 带回 Service。按 ID
加载认证上下文时不读取密码哈希；只有登录按账号查找时才加载密码哈希。

## 当前架构基线

- `app/common` 已删除；公共技术能力统一归入 `app/shared`，业务能力归入所属模块。
- 安装的结构变更与升级共用 `Operations/SystemLifecycle`。`app/system` 只做 Installer Host；新安装在空库导入 `database/schema.sql` 和 `database/seed.sql`，随后由 SystemLifecycle 的 `CleanInstallReceipt` 绑定 schema 文件与迁移目录身份、核对账本，再允许写安装锁。不维护第二套迁移执行器或升级状态机，运行时不比较 schema diff。首次配置、默认数据和锁仍由现有 Installer 代码处理。
- 后端业务层采用两档结构。Auth、Files、Drafts、Library、Social、Safety、Messaging、Notifications、Eventing 和 SystemLifecycle 已按复杂度使用 Application/Domain/Contract；普通模块保持简单 Service。
- MySQL 是最小运行时的可靠状态源；Redis、Worker、定时触发和 SSE 不进入业务模块依赖。事件处理策略（`scheduled`/`resident`）与前端通信模式（`polling`/`realtime`）独立选择；只有定时+轮询、常驻+轮询、常驻+实时合法。定时策略的异步完整性依赖外部 cron 或安全定时触发。
- 系统迁移、不可变 Release、签名校验、备份、激活与恢复由 SystemLifecycle 统一。首次安装导入经发行版审核的完整 schema 和默认数据；Host 不得自行覆盖 Release 文件或执行动态生成的 SQL。

## 已完成样板

- Auth：认证用例、认证上下文、Token Port 和用户持久化 Port 位于 `modules/Platform/Auth`。
- Files：用例位于 `modules/Platform/Files/Application`，Port 位于 `modules/Platform/Files/Contract`，ORM 和存储适配位于模块 Infrastructure。
- Storage/Sender/Captcha：驱动、Factory 和第三方技术实现位于模块 `Infrastructure`，Service 保留管理入口与业务调用语义。
- RBAC：capability 清单和系统角色策略位于 `modules/Platform/Rbac/Domain`，Service 负责事务、ORM 写入和 API 错误映射。
- Content/Cards：发布状态和批处理状态转换位于 `modules/Content/Cards/Domain`，Cards Service 保留当前 CRUD 与事务编排。
- Community：草稿、收藏/历史、社交图、隐私/举报、私信和通知按数据归属拆分模块，HTTP 聚合 Controller 不持有业务状态。
- Eventing：EventEnvelope、Handler Registry 与 MySQL Outbox 提供至少一次投递；定时一次性消费者与常驻 Worker 共用处理器协议，站点同一时刻只允许选定策略在领取事务中 claim。普通请求自动消费已从当前代码移除；`request_safe/daemon_required` 是 Handler 执行特征，不是当前消费模式。
- SystemLifecycle：签名 Release、持久升级计划、迁移 phase/cursor、Lease、备份 Gate、Contract 批准和恢复形成完整状态机。
- Observability：业务依赖 PSR Logger，ThinkPHP Adapter 统一 request/correlation context 与脱敏；高风险审计进入独立表。

Likes 支持 `card` 与 `comment` 两类内容；多目标点赞以 `ref_type`、`ref_id` 和 `uid` 作为唯一关系，计数分别维护在对应内容的 `goods` 字段。

## Auth 垂直切片

Auth 是第一个架构样板，但不是“把所有用户代码放进 Auth”。概念边界如下：

| 概念 | 负责什么 | 不负责什么 | 当前实现 |
| --- | --- | --- | --- |
| Token codec | 签发、验证、续期、失效 | 查用户、判角色、访客策略 | `modules/Platform/Auth/Contract/TokenService.php`、`modules/Platform/Auth/Infrastructure/JwtTokenService.php` |
| Authentication | 从凭证得到当前身份 | 业务权限判断 | `api/middleware/JwtAuthCheck.php` |
| Identity/User | 用户读取、状态、密码规则 | JWT 算法 | `modules/Platform/Auth`、`modules/Platform/User` |
| Authorization | 角色与 capability 判断 | 登录、Token 编解码 | `modules/Platform/Rbac`、Permission middleware |
| Transport | Header/Cookie、HTTP 状态和响应 | 业务规则、ORM | Route、Controller、Middleware |

目标调用链：

```text
Route/Middleware
  -> AuthenticateRequest use case
      -> TokenService port
      -> UserRepository port
      -> AuthPolicy / VisitorPolicy
  <- AuthContext(uid, user, roles, capabilities, renewedToken)
```

建议迁移顺序，每一步保持接口行为不变：

1. 固定登录、注册、访客、续期、无效 Token、禁用用户和 capability 的行为测试。
2. ~~从 `Jwt` 提取 `TokenService` 契约；现有 Firebase JWT + Cache 成为适配器。~~ 已完成。
3. ~~引入只承载认证结果的 `AuthContext`，替代向 Request 动态散落字段。~~ 已完成；
   调用者统一通过 `request()->auth` 读取认证结果。
4. ~~提取 `UserRepository`，让认证用例不直接依赖 ThinkORM Model。~~ 已完成。
5. ~~将访客策略和 RBAC 能力装配移入认证用例，Middleware 只做 HTTP 适配。~~ 已完成。
6. ~~登录/注册分别成为用例；Controller 只解析输入并映射响应。~~ 已完成。
7. ~~删除旧入口和临时适配，更新自动依赖检查，以该切片作为其他模块模板。~~ 已完成；
   API 层执行约束见 `apps/backend/app/api/AGENTS.md`，任务交接清单见
   `docs/engineering/MODULE-MIGRATION.md`。

每一步都应是可独立回退的提交。不要同时更改 Token 语义、返回结构和目录结构。
