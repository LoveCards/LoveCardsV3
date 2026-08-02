# 架构约束与目标

## 当前结论

后端 API 已统一采用 TP 友好的业务分层，不再并列维护 `application` 和 `service` 两套业务入口。

```text
Admin / Theme
      |
      v
@lovecards/sdk  --->  HTTP API
                         |
                         v
Route -> Controller -> Validate -> Service -> Resource -> ApiResponse
                                      |
                                      +-> Model（简单持久化）
                                      +-> Contract -> Infrastructure（复杂/外部能力）
```

核心原则：代码按业务模块内聚，层数按实际复杂度裁剪。简单 CRUD 不强制创建 Repository、Adapter 或空壳 UseCase；出现多驱动、第三方 SDK、权限边界、事务或需要隔离测试时，再增加 Contract 和 Infrastructure。

## 目录职责

| 区域 | 职责 | 可以依赖 | 不应依赖 |
| --- | --- | --- | --- |
| `app/api/route` | 路由、公开性、权限元数据 | Controller、Middleware | 业务实现细节 |
| `app/api/controller` | 读取 HTTP 参数、调用 Validate/Service、映射 Resource | Validate、Service、Resource | Model、Driver、Factory、第三方 SDK |
| `app/api/validate` | 唯一请求字段验证和归一化 | Config 等稳定配置能力 | Controller 业务编排 |
| `app/api/service` | 业务规则、用例编排、事务边界 | Model、Service Contract、稳定 common 能力 | Controller、HTTP Request 状态 |
| `app/api/resource` | 响应数据契约和字段裁剪 | 输入数组/实体数据 | 数据库查询和业务判断 |
| `app/api/model` | ThinkORM 模型和查询 Scope | ThinkORM | Controller 业务流程 |
| `app/api/infrastructure` | ORM 适配器、驱动、缓存、第三方 SDK、配置读取 | Service Contract、common、第三方库 | Controller、完整业务流程 |
| `app/api/middleware` | HTTP 认证、权限、验证码等请求管道 | Service/Auth、ApiResponse | 业务数据库编排 |
| `app/common` | 真正跨 API、前端、系统稳定复用的能力 | common 内部、框架适配 | `api`、`frontend`、`system` 具体实现 |
| `packages/sdk` | HTTP 契约、类型和客户端 | 通用第三方库 | 任意应用私有文件 |

依赖方向是“外层依赖内层定义的契约”，而不是互相引用内部实现。模块之间可以调用稳定的 Service 或 Contract，但不能引用对方的 Controller、Model 或 Driver 内部细节。

## API 模块约定

当前 API 业务统一位于 `app/api/service/<Module>`：

- `Auth`：登录、注册、认证上下文和认证 Port。
- `Files`：文件记录、可见范围、上传、直传、批量操作。
- `Storage`：存储渠道管理；实际 Local/OSS/COS/Qiniu 驱动位于 `infrastructure/Storage`。
- `Sender`：消息发送编排；SMTP、短信等驱动位于 `infrastructure/Sender`。
- `Captcha`：验证码流程；图片、极验、代码驱动位于 `infrastructure/Captcha`。
- `Content`、`Rbac`、`User`、`System`、`Theme`：按需要使用轻量 Service，不为简单 CRUD 强行拆分。

## Files 与 Storage 边界

`Files` 和 `Storage` 是同一套文件架构中的两个职责，不是两套文件系统：

```text
Files Service
  ├─ 文件记录、归属、权限、状态、软删除、引用关系
  └─ Storage Contract
       └─ Storage Infrastructure
            ├─ Local
            ├─ OSS
            ├─ COS
            └─ Qiniu
```

Files 不关心物理驱动细节；Storage 驱动不反向管理文件业务记录。

## Validate 与 Resource

- `Validate` 是唯一维护请求字段规则的层。
- `Resource` 是唯一维护响应内部数据结构的层。
- `ApiResponse` 只负责统一响应外壳、状态码和错误格式。
- Config、Theme、Captcha、Sender、Storage 等动态接口使用“固定外壳 + 动态内部 schema/value”，不对动态内容做静态实体式白名单裁剪。
- 不再新增 `RequestSchema` 层。

## Auth 约定

认证中间件将结果统一放入 `request()->auth`：

```php
$auth = request()->auth;
$uid = $auth->uid();
$roles = $auth->roleIds();
$capabilities = $auth->capabilities();
```

业务代码不得重新读取 `request()->uid`、`request()->user`、`request()->rolesId`、`request()->caps` 或 JWT 具体实现。登录、注册和认证续期由 `service/Auth` 负责；JWT 和 ORM 用户适配位于 `infrastructure/Auth` 或 `common`。

## 事务规则

事务属于拥有完整业务动作的 Service 方法：

- Controller 不管理事务。
- Model 不负责跨实体业务流程。
- 简单操作可以直接由 Service 调用 Model。
- 上传、批量删除、角色分配等跨步骤动作由对应 Service 统一包住事务和异常语义。

## 发布前检查

```text
npm run check
cd apps/backend
composer validate --no-check-publish
composer test
php -l <所有受影响 PHP 文件>
真实 HTTP 回归公开路由、鉴权路由和错误响应
```

迁移过程中保持路由、请求字段、响应契约、权限和数据库结构不变；旧入口只有在所有调用方和测试切换完成后才能删除。
