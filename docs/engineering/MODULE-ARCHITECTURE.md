# LoveCards 模块架构

## 目标

后端按模块组织。模块目录是能力边界；模块内部再按职责分层。API、前台和安装器只是 Host，不承载业务实现。

```text
app/modules/
├─ Platform/       Config User Auth Rbac Storage Files Sender Captcha
├─ Content/        Cards Comments Tags Likes
├─ Theme/          管理与 SPA/SSR 运行时
└─ Operations/     Dashboard Version Installer
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

- Platform：Auth Contract/Public、Config/Public、Rbac/Public、Files/Public、Storage/Public、Sender/Public、Captcha/Public、User/Public；
- Content：Cards/Public、Comments/Public、Tags/Public、Likes/Public；
- Theme：Theme/Public（渲染）和 Theme/Public/ThemeDataProvider（SSR 数据）；
- Operations：Version/Public。

`app/common` 仅保留兼容别名或旧测试适配；`app/system` 只保留 Installer Host，不再承载实现。新增代码不得引用这些兼容命名空间。

不要求每个模块拥有所有目录。Auth、Files 使用 Application；简单 CRUD 使用 Service；Theme 使用 Runtime；驱动实现统一放在 Infrastructure/Driver。

## 依赖规则

- Controller/Transport 只能调用模块 Service、Application 或 Public 入口。
- 跨模块只能引用对方 `Public/`；禁止引用对方 Application、Service、Domain、Infrastructure、Model。
- Domain 不依赖 ThinkPHP、ORM、HTTP、缓存、JWT 或第三方 SDK。
- Contract 不依赖 Transport、ORM 或 Infrastructure。
- Files 依赖 Storage 的公开存储能力；Storage 不依赖 Files。
- Content 的跨 Cards/Comments/Tags/Likes 一致性由 Content 协调用例负责，禁止子模块互相直接写对方 Model。
- Theme SSR 数据通过 ThemeDataProvider 注册表获取，不直接调用 Content 内部类。
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
