# LoveCards 模块架构

## 目标

后端按模块组织。模块目录是能力边界；模块内部再按职责分层。API、前台和安装器只是 Host，不承载业务实现。

```text
app/modules/
├─ Platform/       Auth User Rbac Config Captcha Sender Storage Files
│                  Drafts Library Social Safety Messaging Notifications Eventing
├─ Content/        Cards Comments Tags Likes
├─ Theme/          主题 Release 管理与 SPA 运行时
└─ Operations/     Dashboard Version Installer Runtime SystemLifecycle Administration
```

## 模块内部

```text
Module/
├─ Public/          允许其他模块引用的入口、DTO、稳定协议
├─ Application/     复杂用例、事务和流程编排
├─ Service/         简单业务聚合或管理入口
├─ Domain/          状态、策略、值对象
├─ Contract/        本模块需要的 Port
├─ Infrastructure/ ORM、缓存、文件系统、第三方 SDK、驱动
├─ Runtime/         仅 Theme 等运行时模块使用
├─ Transport/       Api/Frontend/System 适配器
└─ Tests/
```

### 公开入口约定

跨模块调用统一使用目标模块的 `Public/`，不直接引用目标模块的 Service、Application、Domain、Infrastructure 或 Model。`Public/` 只暴露稳定 DTO、查询、命令和能力协议；HTTP Controller 仍属于 Host，只负责请求验证、调用 Public/Application 并交给 ApiResponse。

当前已提供的稳定入口包括：

- Platform：Auth、Config、Rbac、Files、Storage、Sender、Captcha、User，以及 Drafts、Library、Social、Safety、Messaging、Notifications、Eventing 的 Public/Contract；
- Content：Cards/Public、Comments/Public、Tags/Public、Likes/Public；
- Theme：Theme/Public 负责主题入口，Runtime/Infrastructure 负责主题协议、文件目录和 Host 绑定；
- Operations：Version、Administration、Runtime 与 SystemLifecycle 的稳定入口/Port。

`app/common` 已删除；`app/system` 只保留 Installer Host，不再承载实现。新增代码不得创建新的顶层公共目录。

不要求每个模块拥有所有目录。Auth、Files、社区状态模块、Eventing 与 SystemLifecycle 使用 Application；简单 CRUD 使用 Service；Theme 使用 Runtime；驱动实现统一放在 Infrastructure/Driver。

## 依赖规则

- Controller/Transport 只能调用模块 Service、Application 或 Public 入口。
- 跨模块只能引用对方 `Public/`；禁止引用对方 Application、Service、Domain、Infrastructure、Model。
- Domain 不依赖 ThinkPHP、ORM、HTTP、缓存、JWT 或第三方 SDK。
- Contract 不依赖 Transport、ORM 或 Infrastructure。
- Files 依赖 Storage 的公开存储能力；Storage 不依赖 Files。
- Messaging 通过 Notifications 的稳定通知入口产生收件提醒；Social 通过 Eventing 传播 `social.followed`，不能直接写通知表。
- Eventing 只认识 EventEnvelope、Registry 与 Port；业务 Handler 位于拥有副作用的目标模块 Infrastructure。
- SystemLifecycle 通过 Contract 操作 Release、Migration、Backup、Lease、Pointer 和 Runtime，Domain 不依赖 ZIP、PDO、文件系统或 ThinkPHP。
- Content 的跨 Cards/Comments/Tags/Likes 一致性由 Content 协调用例负责，禁止子模块互相直接写对方 Model。
- 前台主题只返回 Shell 和启动上下文，页面数据统一由浏览器 SDK 请求 API。
- Dashboard 是跨模块只读组合，不直接读取其他模块 Model。
- Installer 不依赖正常运行期 Auth/Rbac。
- `shared/` 只能放无业务语义的 HTTP、缓存、分页、事务和基础技术能力。

## 驱动规范

Storage、Sender、Captcha 使用同一套注册约定：

```text
Contract/        业务可消费的稳定协议
Infrastructure/Driver/ 具体实现
Registry/        显式注册、校验和查询元数据
```

内置驱动显式注册；第三方驱动通过 `register()` 扩展。注册时校验接口、唯一 type、meta 和配置 Schema。

## Host

- `app/api`：路由、HTTP Controller、中间件、异常适配；不放业务 Service/Model/Driver。
- `app/frontend`：Theme 前台路由和渲染 Host。
- `app/system`：Installer Host。
- `app/provider.php`：依赖绑定、模块注册、驱动注册的组合根。

## 验收

迁移必须保持现有 URL、HTTP 方法、Route meta、权限、SDK、数据库和响应契约不变，并通过：

```text
npm run check:architecture
所有 PHP 语法检查
后端行为/数据库/Theme/安装基线
真实 API 与 Theme HTTP 检查
```
