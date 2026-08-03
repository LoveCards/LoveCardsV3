# Modules 开发规范与用法

`app/modules` 是后端能力的归属边界。一个模块代表一项完整能力，模块内部负责业务规则、用例编排、持久化适配和对外稳定入口；`app/api`、`app/frontend`、`app/system` 只是宿主，不承载模块业务实现。

## 1. 模块分层

模块按实际复杂度选择目录，不要求每个模块都具备全部层级。

```text
<Module>/
├─ Public/          跨模块稳定入口、公开 DTO、查询和命令
├─ Application/     复杂用例、事务和流程编排
├─ Service/         简单业务聚合或管理入口
├─ Domain/          状态、策略、值对象和纯业务规则
├─ Contract/        本模块需要的 Port/接口/数据契约
├─ Infrastructure/ ORM、缓存、文件、第三方 SDK、驱动
├─ Runtime/         运行时能力（主要用于 Theme）
└─ Transport/       API/Frontend/System 适配器和 Resource
```

### 简单模块

适用于配置、普通 CRUD、简单只读聚合：

```text
Module/
├─ Public/
├─ Service/
├─ Infrastructure/
└─ Transport/Api/
```

调用链：

```text
Controller → Service → Infrastructure/Model → Resource → ApiResponse
```

### 复杂模块

适用于存在状态机、事务、多驱动、外部副作用或可替换实现的模块：

```text
Module/
├─ Public/
├─ Application/
├─ Domain/
├─ Contract/
├─ Infrastructure/
└─ Transport/
```

调用链：

```text
Controller → Application
                    ├─ Contract
                    ├─ Domain
                    └─ Infrastructure
             → Resource → ApiResponse
```

不要为了目录对称而创建空的 `Application`、`Domain` 或 `Contract`。

## 2. 当前模块分类

```text
Platform/
├─ Auth       身份认证、Token、认证上下文
├─ User       用户资料和用户管理
├─ Rbac       角色、Capability、授权
├─ Files      文件业务、文件记录、上传用例
├─ Storage    存储抽象和存储驱动
├─ Sender     消息发送和发送驱动
├─ Captcha    验证码和验证码驱动
└─ Config     系统配置

Content/
├─ Cards      卡片和发布状态
├─ Comments   评论
├─ Tags       标签
└─ Likes      点赞

Theme/        主题管理、SSR 数据和运行时
Operations/   Dashboard、Installer、Version
```

## 3. 对外入口规则

跨模块调用只能使用目标模块的 `Public/` 或明确的 `Contract/`，不能直接引用目标模块的内部实现。

```php
// 正确：调用 Storage 的稳定公开能力
use app\modules\Platform\Storage\Public\Storage;

$result = Storage::put($path, $stream, $options);
```

```php
// 错误：绕过公开入口调用驱动或 ORM
use app\modules\Platform\Storage\Infrastructure\Driver\LocalDriver;
use app\modules\Platform\Storage\Infrastructure\Persistence\Model\Files;
```

`Public/` 的职责是隐藏内部实现变化，暴露稳定的命令、查询、DTO 或能力协议。Controller 可以调用模块的 `Public`、`Application` 或简单 `Service`，但不应调用 Model、Driver 或第三方 SDK。

## 4. 请求和响应流程

```text
Route Meta
  → Middleware（认证、权限、验证码、会话）
  → Controller
  → Validate（请求字段验证和归一化）
  → Public/Application/Service
  → Resource（响应数据映射）
  → ApiResponse（统一响应外壳）
```

职责边界：

- `Route Meta`：接口身份、公开性、权限能力和 HTTP 方法。
- `Validate`：请求字段、类型、场景和白名单；不编排业务。
- `Application/Service`：业务流程、事务和错误语义。
- `Resource`：内部对象到 API 数据结构的映射；不查询数据库、不执行副作用。
- `ApiResponse`：统一成功/失败外壳；不放模块字段定义。

## 5. 依赖方向

```text
Host（api/frontend/system）
        ↓
Public / Application / Service
        ↓
Domain / Contract
        ↓
Infrastructure / ORM / Driver / SDK
```

约束：

1. `Domain` 不依赖 ThinkPHP、ORM、HTTP、JWT、缓存或第三方 SDK。
2. `Contract` 不依赖 Transport、ORM 或 Infrastructure。
3. `Infrastructure` 实现 `Contract`，不能反向编排业务流程。
4. 模块之间不直接访问对方的 Model、Service、Application 或 Driver。
5. `shared` 只放无业务归属的跨模块技术能力；禁止重新创建 `common`。
6. `Files` 使用 `Storage` 的公开存储能力；`Storage` 不依赖 `Files`。
7. `Theme` 通过 `ThemeDataProvider` 等公开注册能力获取 SSR 数据，不直接读取 Content 内部类。

## 6. 驱动模块规范

Storage、Sender、Captcha 等多驱动模块使用统一结构：

```text
Module/
├─ Contract/                  稳定能力协议和结果对象
├─ Infrastructure/
│  ├─ Driver/                 每个驱动一个实现
│  ├─ Factory/                驱动创建和生命周期
│  └─ Registry/               驱动注册、校验和元数据
└─ Service/                   管理入口和业务语义
```

新增驱动必须：

1. 实现模块的 Driver 接口；
2. 通过 Factory/Registry 注册，不在 Controller 中 `new`；
3. 声明唯一 `type`、配置 schema 和能力 metadata；
4. 对配置错误、驱动不可用和外部失败返回稳定异常；
5. 增加驱动契约测试，并至少保留一个本地替身用于行为测试。

## 7. 事务和副作用

以下操作放在 `Application` 或模块 `Service`，不能放在 Controller：

- 多表写入；
- 状态机转换；
- 文件写入后创建文件记录；
- 发送消息、调用第三方 SDK；
- 需要补偿、回滚或独立测试的流程。

简单单表 CRUD 可以保留在 `Service`。事务边界应覆盖业务用例，而不是只包住某一次 Model 调用。

## 8. 新增模块步骤

1. 先确定模块所有权：它是 Platform、Content、Theme 还是 Operations 能力。
2. 定义公开入口和输入/输出契约。
3. 按复杂度选择简单结构或复杂结构。
4. 把请求校验放入 API Validate，把响应映射放入 Resource。
5. 把 ORM、缓存、驱动和第三方 SDK 放入 Infrastructure。
6. 通过 `Public/` 或 `Contract/` 注册跨模块依赖。
7. 为业务规则、权限边界、事务和失败路径增加测试。
8. 运行架构检查、PHP 语法检查和模块行为测试。

## 9. 验证命令

在仓库根目录运行唯一提交门禁：

```powershell
npm.cmd run check
```

只需快速重跑后端行为测试时使用：

```powershell
npm.cmd run test:backend
```

该入口会自动发现 `apps/backend/tests/**/*.php`，新增测试文件不需要在 Composer 中手工注册。完整分层、契约生成和隔离集成测试要求见 `docs/engineering/BACKEND-TESTING.md`。

新增模块至少应验证：

- 正常业务流程；
- 无效输入；
- 未认证和无权限访问；
- 所有权边界；
- 事务失败或外部驱动失败；
- Resource 响应契约。

## 10. 判断代码应该放在哪里

```text
这是 HTTP 解析吗？             → api/Transport
这是请求字段验证吗？           → Validate
这是复杂业务流程吗？           → Application
这是简单业务聚合吗？           → Service
这是纯业务规则或状态吗？       → Domain
这是模块间稳定接口吗？         → Public/Contract
这是 ORM、缓存或第三方实现吗？ → Infrastructure
这是响应字段映射吗？           → Resource
这是无业务归属的通用技术能力吗？→ shared
```

文件所在边界应与功能所有权一致。调用方只依赖稳定入口，不感知目标模块的内部目录。
