# 后端测试与 API 契约工程规范

本文定义 LoveCards 后端改动如何被验证，以及验证结果如何沿着 API 契约传递到 SDK 和管理端。目标不是追求“测试文件数量”，而是保证每次接口变更都能回答三件事：业务行为是否正确、公开契约是否稳定、SDK 是否仍可安全生成和使用。

## 1. 唯一质量链路

```text
后端实现
  -> Route Meta + executable contract
  -> Validate 请求 schema / Resource 响应 schema
  -> route-manifest.json
  -> API audit
  -> openapi.json
  -> generated SDK
  -> SDK typecheck / live integration
  -> Admin typecheck / browser E2E
```

源码与生成物的所有权如下：

| 内容 | 唯一维护位置 | 是否手工编辑 |
| --- | --- | --- |
| 接口身份、HTTP 方法、公开性、能力要求 | `apps/backend/app/api/route/*.php` | 是 |
| 请求字段、类型、场景、白名单 | 模块 `Transport/Api` 下的 Validate | 是 |
| 响应字段和数据映射 | 模块 `Transport/Api` 下的 Resource | 是 |
| 成功、分页和错误外壳 | `ApiResponse` / `ApiException` | 是 |
| 实际路由清单 | `contracts/route-manifest.json` | 否，命令生成 |
| OpenAPI 契约 | `contracts/openapi.json` | 否，命令生成 |
| SDK 资源和契约类型 | `packages/sdk/src/generated/**` | 否，命令生成 |

生成文件落后于源码即为失败，禁止直接修改生成物来让检查通过。

## 2. 测试分层

### L0：架构边界

`npm run check:architecture` 检查模块依赖方向、Host 与模块边界、`shared` 使用规则以及禁止恢复的旧目录。它回答“代码是否放在正确位置”，不证明业务行为正确。

### L1：模块行为与 Resource 契约

`npm run test:backend` 递归发现并执行 `apps/backend/tests/**/*.php`。每个文件都是可独立执行的测试程序，成功返回 0，失败返回非 0；新增测试文件不需要手工注册。

当前基线覆盖：

- Auth 登录、注册、访客策略、权限和 JWT 续期；
- Cards、Comments、Tags、Files 的行为与所有权边界；
- Resource 字段白名单、动态 schema 外壳和敏感字段隐藏；
- 安装脚本、数据库 schema 与迁移安全；
- Theme 路由唯一性和 ZIP 安装安全。

这层优先使用内存替身或最小框架替身，测试 Domain、Application、Service 和 Resource 的确定性行为。涉及真实 HTTP、真实数据库和 SDK 序列化的场景不能只停留在本层。

### L2：路由和 API 契约

每条 API 路由必须同时具备稳定 `name`、`meta` 和 `contract`：

```php
Route::post('', 'Content.Cards/create')
    ->name('cards.create')
    ->setOption('contract', [
        'request' => ['validate' => CardsValidate::class, 'scene' => 'create'],
        'response' => ['resource' => CardCreatedResource::class, 'shape' => 'item', 'status' => 201],
    ])
    ->setOption('meta', ['name' => '创建卡片', 'group' => '卡片', 'caps' => ['cards.create']]);
```

允许的响应 `shape` 由契约导出器维护，常用值为 `item`、`collection`、`paginated` 和 `void`。动态配置或驱动 schema 使用固定响应外壳加运行时 schema，不得虚构固定字段，也不得无声明退化为 `any`。

相关门禁：

- `npm run check:routes:strict`：真实加载 ThinkPHP 路由，确认清单未漂移，且每条路由都有可执行契约；
- `npm run check:api-contract`：审计 Controller、Validate、Resource 和响应形状，P0/P1 问题阻止导出；
- `npm run check:generated:sdk`：确认 SDK 生成物与 OpenAPI 完全一致。

### L3：SDK 真实集成

`npm run test:sdk:integration` 通过真实 HTTP 调用构建后的 `@lovecards/sdk`，覆盖公开接口、认证、CRUD、权限、批量操作、错误状态、请求去重和生命周期 hook。这层验证的是“后端返回值能否被真实 SDK 正确消费”，不能由静态 OpenAPI 检查替代。

集成测试会写入数据，只允许指向一次性本地环境。运行器强制要求：

```powershell
$env:LOVECARDS_SDK_TEST_ISOLATED='1'
$env:LOVECARDS_SDK_TEST_BASE_URL='http://127.0.0.1:6001/api'
$env:LOVECARDS_SDK_TEST_ADMIN_ACCOUNT='本地测试管理员'
$env:LOVECARDS_SDK_TEST_ADMIN_PASSWORD='本地测试密码'
npm.cmd run test:sdk:integration
```

运行器会拒绝非 localhost 地址或缺少隔离标记的环境。测试结果写入 `packages/sdk/test/TEST_REPORT.md`。

### L4：管理端浏览器 E2E

`npm run test:admin:e2e` 创建名称受限的临时数据库，启动后端与管理端，使用 Playwright 验证登录、鉴权跳转和固定管理路由，结束后删除临时数据库。必须显式设置 `LOVECARDS_E2E_ISOLATED=1`，且数据库名必须以 `lovecards_e2e_` 开头。

这层属于跨系统发布验证，不替代后端模块测试。页面上的搜索、清空搜索、保存、删除等关键交互，应当同时在 SDK 集成层验证接口语义，在浏览器层验证用户操作结果。

## 3. 日常质量门禁

在仓库根目录执行：

```powershell
npm.cmd run check
```

该命令必须保持无外部副作用，并串行执行：

1. 后端行为与 Resource 测试；
2. 架构边界检查；
3. 严格路由契约检查；
4. API 审计和 OpenAPI 一致性检查；
5. SDK 生成一致性检查；
6. SDK 与管理端类型检查。

接口或契约发生预期变化时，先生成再检查：

```powershell
npm.cmd run generate:sdk
npm.cmd run check
```

发版候选还必须在一次性本地环境运行 SDK 集成测试和管理端 E2E。未运行的外部环境测试必须在交付记录中明确说明，不能用静态检查结果代替。

## 4. 新增或修改接口的标准流程

以 `Content/Cards` 为例：

1. 在 `app/modules/Content/Cards` 内完成业务入口，不让 Controller 直接访问 Model、Driver 或第三方 SDK。
2. 在模块 `Transport/Api` 的 Validate 中维护请求规则和场景；请求字段只维护一份。
3. 在模块 `Transport/Api` 的 Resource 中维护响应映射与 `schema()`；敏感字段采用白名单输出。
4. 在 `app/api/route/cards.php` 声明唯一 route name、meta、request contract、response resource、shape 和 HTTP status。
5. 在 `apps/backend/tests/Cards` 增加正常、非法输入、未认证/无权限、所有权、事务或副作用失败测试；Resource 变化同步补 `tests/Api/ResourceContractBaseline.php`。
6. 运行 `npm run generate:sdk`，只检查生成差异，不手改 `packages/sdk/src/generated`。
7. 在 `packages/sdk/test/integration-suite.mjs` 增加真实调用场景，至少断言请求参数、响应 shape、错误状态以及空值/清空条件。
8. 运行 `npm run check`；进入发版候选时再运行真实 SDK 集成和管理端 E2E。

一个接口只有“请求能返回 200”不算完成。至少要覆盖成功路径、输入边界、认证/授权、响应结构和一个真实消费者场景。

## 5. 测试文件规范

- 测试名称使用英文，失败信息可使用中文；输出必须能从 CI 日志直接定位场景。
- 一个测试文件聚焦一个模块或一类契约，文件可以独立运行，不依赖其他测试的执行顺序。
- 默认不连接生产或共享数据库，不调用真实短信、邮件、对象存储等外部服务。
- 事务和外部副作用使用可控替身验证成功、失败、回滚或补偿；真实驱动另做隔离集成测试。
- 不把已知失败吞成成功。只有环境明确不具备可选能力时才允许 `SKIP`，并记录原因。
- 回归缺陷先增加能复现问题的测试，再修复实现。比如列表搜索必须同时验证“有关键词能命中”和“清空关键词恢复默认列表”。

## 6. 环境与安全

- PHP 8.1–8.x。无法从 `PATH` 找到 PHP 时，在当前终端设置 `PHP_BINARY` 环境变量；根 `.env.example` 只作为变量清单，不会被测试脚本自动加载。
- Node.js 22+，npm 11+；统一使用根 `package-lock.json`。
- SDK 集成和 E2E 只运行在 disposable localhost 环境，禁止指向线上、预发布或多人共享数据库。
- 账号、密码、Token 和私钥不得写入测试源码、报告或 Git；只通过环境变量注入。
- 测试创建的数据必须使用唯一前缀并在 `finally`/清理阶段删除；清理失败视为测试基础设施问题并显式报告。

## 7. 覆盖边界

自动化检查可以证明当前声明的行为和契约一致，但不能证明所有业务需求都已被声明。新增功能时，开发者仍须根据业务风险补充场景。以下情况必须增加独立测试，而不能只依赖生成器：

- 新的状态流转、事务或补偿逻辑；
- 新的 capability、所有权或公开性规则；
- 新的存储、发送器、验证码驱动；
- 响应字段白名单、动态 schema 或敏感字段变化；
- 会改变 SDK 方法语义、后台页面状态或搜索/筛选行为的接口变化。
