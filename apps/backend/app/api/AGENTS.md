# API 目录约束

本文档适用于 `apps/backend/app/api/**`。

## 默认调用链

```text
Route -> Controller -> Validate -> Service -> Resource -> ApiResponse
```

- Controller 只解析 HTTP 输入、调用 Validate、调用 Service、映射 Resource。
- Validate 是唯一请求字段验证层；不新增 `RequestSchema`。
- Service 负责业务规则、用例编排和事务边界，不读取 `request()`，不依赖 Controller 或 Middleware。
- Resource 负责响应内部结构和字段裁剪。
- ApiResponse 只负责统一响应外壳、HTTP 状态码和错误格式。
- 简单 CRUD 可以由 Service 直接调用 Model；只有复杂或外部能力才增加 Contract。
- Driver、Factory、第三方 SDK、缓存、文件系统和 ORM 适配器放在 `infrastructure`。

## 目录归属

- `service/Auth`：登录、注册、认证上下文、认证 Port。
- `service/Files`：文件业务用例和文件 Contract。
- `service/Storage`：存储渠道管理用例；物理驱动在 `infrastructure/Storage`。
- `service/Sender`：发送业务；SMTP、短信驱动在 `infrastructure/Sender`。
- `service/Captcha`：验证码业务；验证码驱动在 `infrastructure/Captcha`。
- `service/Content`、`Rbac`、`User`、`System`、`Theme`：按实际复杂度提供业务 Service。
- `infrastructure`：技术适配和外部系统实现，不编排完整业务流程。

## Controller 规则

禁止 Controller 直接依赖：

- `app\api\model\*`
- Storage/Sender/Captcha Driver
- `*Factory`
- 第三方 SDK
- 数据库 Facade
- 前端主题引擎或文件系统实现

动态配置、主题、验证码、Sender、Storage 接口使用“固定响应外壳 + 动态内部 schema/value”，不能用实体资源的固定白名单裁剪动态内容。

## AuthContext

经过 `JwtAuthCheck` 的请求统一从 `request()->auth` 读取认证结果：

```php
$auth = request()->auth;
$uid = $auth->uid();
$roles = $auth->roleIds();
$capabilities = $auth->capabilities();
```

公开路由必须允许没有上下文：

```php
$auth = request()->auth ?? null;
$capabilities = $auth ? $auth->capabilities() : [];
```

禁止重新引入 `request()->uid`、`request()->user`、`request()->rolesId`、`request()->caps`、`request()->newToken` 或对应的 `$request` 动态字段。业务代码不得直接调用 JWT 具体实现。

## Files/Storage 规则

```text
Files Service
  -> Storage Contract
  -> Storage Infrastructure Driver
```

Files 维护文件记录、归属、权限、状态、软删除和引用关系；Storage 维护上传、删除、URL、直传凭证和渠道配置。Storage Driver 不创建文件业务记录，Files Service 不直接扫描或实例化驱动。

## 代码规范

- PHP 类、方法、变量、DTO 字段、Route name、capability 使用英文。
- PHPDoc 和架构说明使用中文；具体类名、方法名和协议术语保留英文。
- 面向用户的业务错误通过 `ApiException`，HTTP 状态码和业务码保持稳定。
- 新增或本次修改区域保持统一编码和格式，不批量翻译历史注释。

## 最低验证

在仓库根目录执行：

```text
npm run check
cd apps/backend
composer validate --no-check-publish
composer test
```

对所有受影响 PHP 文件执行 `php -l`。涉及路由时，至少补充一条公开路由、一条鉴权路由和一条真实 HTTP 请求验证；无法运行的检查必须写入交接说明。
