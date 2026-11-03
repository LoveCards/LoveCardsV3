# API Agent 约束

模块迁移后，`app/api` 只保留 Route、Controller、Middleware 等 HTTP Host 适配代码。
业务实现、Model、Driver、Validate 和 Resource 按模块位于 `app/modules`；详细规则见
`docs/engineering/MODULE-ARCHITECTURE.md`。Controller 不得引用模块 Infrastructure 或 Model。

本文件适用于 `apps/backend/app/api/**`。开始修改前同时读取仓库根 `AGENTS.md` 和
`docs/engineering/ARCHITECTURE.md`；涉及认证时再读取 `docs/engineering/AUTH-BASELINE.md`。

## API 协议链

```text
Route Meta -> Controller -> Validate -> Business -> Resource -> ApiResponse
```

- Route meta 维护接口身份、公开性、权限和 SDK 可见元信息。
- Validate 是唯一请求字段验证层；不要重新引入 `RequestSchema` 作为第二套验证。
- Controller 只解析 HTTP 输入、调用业务入口、选择 Resource，并返回 `ApiResponse`。
- Resource 是唯一响应内部数据契约层；响应外壳由 `ApiResponse`/`ApiException` 维护。

## Business 两档结构

默认保持简单业务层：

```text
Controller -> service/<Module>/<Service>.php -> Model/Db
```

当模块出现多驱动、第三方 SDK、明确状态流转、跨用例复用规则、外部副作用一致性或需要独立测试的核心规则时，再升级为复杂业务层：

```text
Controller -> service/<Module>/Application/<UseCase>.php
              -> service/<Module>/Domain/*
              -> service/<Module>/Contract/* <- infrastructure/<Module>/*
```

- 简单 Service 可以依赖 Model/Db，但不能读取 `request()`，不能引用 Controller/Middleware。
- Application 负责编排单个用例，不直接依赖 Model/Db/Infrastructure。
- Domain 只放业务规则、状态和值对象，不依赖 ThinkPHP、ORM、缓存、SDK 或 `ApiException`。
- Contract 是业务内部 Port，不是 HTTP 请求/响应契约；只有真实需要替换实现时才新增。
- Infrastructure 实现 Contract 或封装第三方技术，不编排完整业务流程。
- Service 不读取认证请求字段。调用者显式传入 `uid`、角色或 capability。
- 新增抽象前先确认它消除了真实的框架、持久化或外部服务耦合。

## API 语言约定

- PHP 类、方法、变量、DTO 字段、Route name 和 capability 使用英文。
- PHPDoc、行内注释及架构说明使用中文，具体类名、方法名和协议术语保留英文。
- API 用户可见消息与 `ApiException` 业务错误使用中文；业务码和 HTTP 状态保持稳定。
- 测试 case 名称使用英文，测试失败说明可以使用中文。
- 不批量翻译历史注释；只要求新增和本次修改区域保持一致。

## AuthContext

经过 `JwtAuthCheck` 的请求统一从 `request()->auth` 读取认证结果：

```php
$auth = request()->auth;
$uid = $auth->uid();
$roles = $auth->roleIds();
$capabilities = $auth->capabilities();
```

公开路由没有认证中间件，需要能力列表时显式处理无上下文情况：

```php
$auth = request()->auth ?? null;
$capabilities = $auth ? $auth->capabilities() : [];
```

禁止重新引入 `request()->uid`、`user`、`rolesId`、`caps`、`newToken` 或对应的
`$request` 动态字段。不要在业务代码中直接调用具体 JWT 实现。

## Auth 用例与契约

- 登录：`service/Auth/LoginUser.php`
- 注册：`service/Auth/RegisterUser.php`
- 请求认证：`service/Auth/AuthenticateRequest.php`
- 当前身份：`service/Auth/AuthContext.php`
- 用户持久化 Port：`service/Auth/UserRepository.php`
- Token Port：`common/contract/TokenService.php`

变更上述行为前，先扩展 `tests/Auth/BehaviorBaseline.php` 或
`tests/Auth/JwtRenewal.php` 固定预期，再修改实现。不要在结构重构中同时更改业务码、
HTTP 状态、Token payload 或响应 Header。

## 最低验证

在仓库根执行：

```bash
npm run check:architecture
cd apps/backend
composer test:auth
composer validate --no-check-publish
```

并对所有受影响 PHP 文件执行 `php -l`。涉及路由行为时，补充一个公开路由和一个鉴权
路由的真实 HTTP 验证；无法运行的检查必须写入交付说明。
