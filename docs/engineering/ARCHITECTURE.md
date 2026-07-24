# 架构约束与目标

## 总体判断

当前代码已具备 Controller、Service、Model、Middleware、SDK 等分工，但部分目录名承担了多种概念，依赖方向主要靠约定，尚未形成稳定架构。目标不是一次重写，而是以垂直切片逐步把“概念、依赖方向、验证”对齐。

```text
Admin / Theme
      |
      v
@lovecards/sdk  --->  HTTP API
                         |
                         v
Transport -> Application -> Domain
                |             |
                v             v
          Infrastructure adapters
```

## 架构约束表

| 区域 | 职责 | 允许依赖 | 禁止依赖 | 自动化状态 |
| --- | --- | --- | --- | --- |
| `apps/admin` | 管理界面与交互 | SDK、UI 库 | 后端文件、SDK 源码相对路径 | 已检查 workspace 依赖 |
| `packages/sdk` | HTTP 契约、类型、客户端 | 通用第三方库 | 任一 app | 已检查包级副作用 |
| API Controller | 请求解析、校验、响应映射 | Application/Service、DTO | Model、基础设施细节 | 已检查 Controller -> Model |
| Application/Service | 编排用例与事务 | Domain、Port | Controller | 已检查 Service -> Controller |
| Domain | 实体、值对象、业务策略 | 领域内部 | HTTP、ORM、JWT、缓存、框架 | 迁移切片中逐步建立 |
| Infrastructure | ORM、JWT、缓存、邮件等适配 | 第三方库、Port | 业务流程编排 | 迁移切片中逐步建立 |
| `common` | 真正跨模块且稳定的能力 | common 内部、框架适配 | api/frontend/system | 已建立债务上限 |

约束原则：外层可以依赖内层定义的抽象，内层不能知道外层实现。模块之间“不依赖”不是互不调用，而是调用稳定契约，不引用对方内部结构。

Auth 的 `UserRepository` 返回只读 `AuthUser`，不会把 ThinkORM Model 带回 Application。按 ID
加载认证上下文时不读取密码哈希；只有登录按账号查找时才加载密码哈希。

## 已知债务基线

- `app/common/support/OwnershipGuard.php` 仍依赖 `app/api/ApiException`。
- 自动检查只允许这一条既有债务；新增同类依赖会失败。迁移该能力时删除白名单，实现债务只减不增。
- 后端领域层尚未形成统一目录。未完成第一个切片前，不批量搬目录。

## Auth 垂直切片

Auth 是第一个架构样板，但不是“把所有用户代码放进 Auth”。概念边界如下：

| 概念 | 负责什么 | 不负责什么 | 当前实现 |
| --- | --- | --- | --- |
| Token codec | 签发、验证、续期、失效 | 查用户、判角色、访客策略 | `common/contract/TokenService.php`、`common/infra/JwtTokenService.php` |
| Authentication | 从凭证得到当前身份 | 业务权限判断 | `api/middleware/JwtAuthCheck.php` |
| Identity/User | 用户读取、状态、密码规则 | JWT 算法 | `api/service/User/*`、`api/model/Users.php` |
| Authorization | 角色与 capability 判断 | 登录、Token 编解码 | `api/service/Rbac/*`、Permission middleware |
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
3. ~~引入只承载认证结果的 `AuthContext`，替代向 Request 动态散落字段。~~ 已建立上下文；
   旧字段在各垂直模块迁移到 `request()->auth` 后删除。
4. ~~提取 `UserRepository`，让认证用例不直接依赖 ThinkORM Model。~~ 已完成。
5. ~~将访客策略和 RBAC 能力装配移入认证用例，Middleware 只做 HTTP 适配。~~ 已完成。
6. 登录/注册分别成为用例；Controller 只解析输入并映射响应。
7. 删除旧入口和临时适配，更新自动依赖检查，以该切片作为其他模块模板。

每一步都应是可独立回退的提交。不要同时更改 Token 语义、返回结构和目录结构。
