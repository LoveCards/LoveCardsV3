# API Agent 约束

本文件适用于 `apps/backend/app/api/**`。开始修改前同时读取仓库根 `AGENTS.md` 和
`docs/engineering/ARCHITECTURE.md`；涉及认证时再读取 `docs/engineering/AUTH-BASELINE.md`。

## 新业务默认调用链

```text
Route -> Controller -> Application use case / Service -> Port -> Infrastructure adapter
```

- Controller 只解析和校验 HTTP 输入、调用用例、映射响应，不直接访问 Model。
- Application 用例编排业务规则，通过构造函数依赖 Port，不读取 `request()`，不引用
  Controller、Middleware、Model 或 Infrastructure。
- Port 放在拥有业务语义的 Application 目录；适配器放在 Infrastructure，并在
  `app/provider.php` 绑定。
- Service 不读取认证请求字段。调用者显式传入 `uid`、角色或 capability。
- 新增抽象前先确认它消除了真实的框架、持久化或外部服务耦合。

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

- 登录：`application/Auth/LoginUser.php`
- 注册：`application/Auth/RegisterUser.php`
- 请求认证：`application/Auth/AuthenticateRequest.php`
- 当前身份：`application/Auth/AuthContext.php`
- 用户持久化 Port：`application/Auth/UserRepository.php`
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
