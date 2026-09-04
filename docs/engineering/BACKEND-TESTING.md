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
  -> localhost SDK transport / SDK typecheck / live integration
  -> SDK dist / 主题公开 SDK
  -> Admin typecheck / browser E2E
```

源码与生成物的所有权如下：

| 内容 | 唯一维护位置 | 是否手工编辑 |
| --- | --- | --- |
| 接口身份、HTTP 方法、公开性、能力要求 | `apps/backend/app/api/route/*.php` | 是 |
| Controller 请求输入 | 路由对应的 API Controller，经 `$this->validated()` 读取 | 是 |
| 请求字段、类型、场景、白名单 | 模块 `Transport/Api` 下的 Validate | 是 |
| 响应字段和数据映射 | 模块 `Transport/Api` 下的 Resource | 是 |
| 成功、分页和错误外壳 | `ApiResponse` / `ApiException` | 是 |
| 实际路由清单 | `contracts/route-manifest.json` | 否，命令生成 |
| OpenAPI 契约 | `contracts/openapi.json` | 否，命令生成 |
| SDK 资源和契约类型 | `packages/sdk/src/generated/**` | 否，命令生成 |
| SDK 浏览器 bundle | `packages/sdk/dist/lovecards.browser.js` | 否，命令构建 |
| 主题公开 SDK | `apps/backend/public/_lc/sdk-v2-lovecards.js` | 否，从 SDK dist 同步 |

生成文件落后于源码即为失败，禁止直接修改生成物来让检查通过。SDK 的完整所有权和生成顺序见 `API-SDK-CONTRACT.md`。

## 2. 测试分层

### L0：架构边界

`npm run check:architecture` 检查模块依赖方向、Host 与模块边界、`shared` 使用规则以及禁止恢复的旧目录。它回答“代码是否放在正确位置”，不证明业务行为正确。

### L1：模块行为与 Resource 契约

`npm run test:backend` 递归发现并执行 `apps/backend/tests/**/*.php`。每个文件都是可独立执行的测试程序，成功返回 0，失败返回非 0；新增测试文件不需要手工注册。根运行器是本仓库和 CI 的完整入口，Composer 中的历史手工测试清单不作为覆盖证明。

当前基线覆盖：

- Auth 登录、注册、访客策略、权限和 JWT 续期；
- 账号恢复、security version、Session rotation/revoke；
- Cards、Comments、Tags、Files 的行为与所有权边界；
- 卡片媒体整页批量装配、稳定 URL/公开性撤权，以及本地媒体 HEAD/Range/416；
- Drafts、Library、Social、Safety、Messaging、Notifications 与 Eventing 的状态、幂等、事务和资源范围；
- Resource 字段白名单、动态 schema 外壳和敏感字段隐藏；
- 安装脚本、数据库 schema、升级账本、Release 签名与迁移安全；
- Theme 路由唯一性、ZIP 安装安全、Host Binding、Bootstrap 与 History fallback。

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
- `npm run check:admin-contracts`：交叉检查 Route access、`CapabilityCatalog`、`SystemRolePolicy`、数据库迁移种子、管理端 navigation 和 Nuxt 页面，阻止能力或入口漂移；
- `npm run test:contract-workflow`：用最小故障夹具确认 Controller/scene/schema 漂移、P1 阻断、metadata fail-closed 和 SDK 参数位置不会再次漏检；
- `npm run check:api-contract`：审计 Controller、Validate、Resource 和响应形状，P0/P1 问题阻止导出或检查，P2 仅记录；
- `npm run check:generated:sdk`：确认 SDK 生成物与 OpenAPI 完全一致。

Controller 不得通过 `Request::param()`、`request()->param()`、`$this->param()` 或 `$this->paramIndex()` 绕过路由契约；声明请求契约后只能经 `$this->validated()` 读取字段。`request => null` 明确表示没有 query、body 或上传输入，不得用于掩盖缺失 schema。动态请求也要声明 Validator scene 和 `requestSchemas()`，并在运行时显式允许受 schema 约束的动态键。

`rejectTruthy` 仅允许在 Route Contract 内拒绝已经移除的危险兼容开关；它不能成为隐藏输入通道。测试必须证明该字段未进入 OpenAPI/SDK，同时旧真值请求会被拒绝。

### L3：SDK transport 与真实集成

`npm run test:sdk:transport` 从当前 SDK source 临时构建 client，并在随机 localhost HTTP server 上验证 DELETE query、path + query、GET 布尔值、POST/PATCH body 和数组 query JSON 序列化。它不连接业务数据库，属于默认 `check` 门禁，用来证明生成签名不仅“看起来正确”，而且真实 transport 会把参数发出去。

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

### L5：Theme Gateway 浏览器 E2E

`npm run test:theme:e2e` 对已运行的本地 Theme Gateway 验证 SPA entry/History fallback、公开 API、深层路由、Bootstrap 与同一 Release 资源；提供本地测试账号时继续验证登录、发布和评论。测试目标必须是 localhost，活动主题绑定和测试数据在隔离实例中准备并清理。

管理端 runner 设置 `LOVECARDS_E2E_RUN_SDK_INTEGRATION=1` 与 `LOVECARDS_E2E_RUN_THEME=1` 后，会在同一个一次性数据库中依次执行 SDK integration、Admin Playwright、默认主题安装/激活和 Theme E2E。运行前应执行 `npm run package:default-theme`；该命令会先构建当前 SDK，再生成与 `theme.json` 版本一致的 ZIP。runner 只删除由本次 bootstrap 确认创建的数据库，并在同名库已经存在时拒绝复用。

### L6：签名升级与 CI 集成

`npm run test:upgrade:e2e` 在独立的 `lovecards_e2e_*` 数据库和临时实例根中生成真实签名 Release。它会把后端复制到系统临时目录，仅向该 Release 副本注入 E2E-only Contract migration，再通过 JWT/multipart 创建持久升级计划；测试必须验证提前批准被拒绝、计划进入 waiting、独立批准持久化、Contract SQL probe、激活、active pointer、Release header 与健康检查。工作树和生产 migration 不会被测试修改，临时数据库与 Release source 必须在 `finally` 清理。`npm run test:e2e:safety` 另覆盖 localhost fence、数据库命名限制、同名库碰撞及哨兵数据保留。

GitHub Actions 的 `integration` 作业使用 MySQL 8 和 Playwright Chromium，按“构建 SDK/打包主题 → 碰撞安全 → 组合 SDK/Admin/Theme E2E → 签名 Upgrade E2E → 全仓 build”的顺序运行。失败时上传 Playwright 报告、trace、截图、视频和 SDK 测试报告；长驻测试进程使用可回收的进程树，并有超时强制清理。

## 3. 日常质量门禁

在仓库根目录执行：

```powershell
npm.cmd run check
```

该命令必须保持无外部副作用，并串行执行：

1. PHP 运行时、上传边界和 E2E 隔离安全夹具；
2. 后端行为与 Resource 测试；
3. 契约工作流故障夹具；
4. 架构边界检查；
5. 严格路由契约检查；
6. 管理路由、capability、系统角色、迁移授权种子和 navigation 一致性检查；
7. API 审计、OpenAPI 与 SDK 生成一致性检查；
8. SDK package、localhost transport 和 safety 检查；
9. SDK source、dist 与主题公开资产一致性；
10. SDK、管理端与默认主题类型/行为检查。

接口或契约发生预期变化时，先生成再检查：

```powershell
npm.cmd run build:sdk
npm.cmd run sync:sdk-theme
npm.cmd run check
```

发版候选还必须在一次性本地环境运行 SDK 集成测试和管理端 E2E。未运行的外部环境测试必须在交付记录中明确说明，不能用静态检查结果代替。

## 4. 新增或修改接口的标准流程

以 `Content/Cards` 为例：

1. 在 `app/modules/Content/Cards` 内完成业务入口，不让 Controller 直接访问 Model、Driver 或第三方 SDK。
2. 在模块 `Transport/Api` 的 Validate 中维护请求规则、场景和 `requestSchemas()`；运行时字段和公开 schema 必须一致。
3. 在模块 `Transport/Api` 的 Resource 中维护响应映射与 `schema()`；敏感字段采用白名单输出。
4. 在 `app/api/route/cards.php` 声明唯一 route name、meta、request contract、response resource、shape 和 HTTP status；Controller 只通过 `$this->validated()` 取得 query、body 或文件输入。
5. 在 `apps/backend/tests/Cards` 增加正常、非法输入、未认证/无权限、所有权、事务或副作用失败测试；Resource 变化同步补 `tests/Api/ResourceContractBaseline.php`。
6. 运行 `npm run build:sdk` 和 `npm run sync:sdk-theme`，生成 OpenAPI/SDK、构建 dist 并同步主题公开 bundle；不手改 `packages/sdk/src/generated` 或构建产物。
7. 在 `packages/sdk/test/integration-suite.mjs` 增加真实调用场景，至少断言请求参数、响应 shape、错误状态以及空值/清空条件。
8. 运行 `npm run check`，其中 localhost transport 必须实际观察请求；进入发版候选时再运行真实 SDK 集成和管理端 E2E。

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
- CI 的根 `check` job 必须同时安装 Node、PHP、Composer 和后端依赖；PHP 版本矩阵必须调用根 `test:backend` 递归运行器，不能退回 Composer 的手工文件清单。
- SDK 集成和 E2E 只运行在 disposable localhost 环境，禁止指向线上、预发布或多人共享数据库。
- 账号、密码、Token 和私钥不得写入测试源码、报告或 Git；只通过环境变量注入。
- 测试创建的数据必须使用唯一前缀并在 `finally`/清理阶段删除；清理失败视为测试基础设施问题并显式报告。

Ubuntu CI 同时承担可移植性门禁：generated resource 文件名与 import 大小写必须一致，route manifest 的 source 路径统一为 `/`，任何检查都不能依赖 Windows 大小写不敏感或反斜杠路径行为。

## 7. 覆盖边界

自动化检查可以证明当前声明的行为和契约一致，但不能证明所有业务需求都已被声明。新增功能时，开发者仍须根据业务风险补充场景。以下情况必须增加独立测试，而不能只依赖生成器：

- 新的状态流转、事务或补偿逻辑；
- 新的 capability、所有权或公开性规则；
- 新的存储、发送器、验证码驱动；
- 响应字段白名单、动态 schema 或敏感字段变化；
- 会改变 SDK 方法语义、后台页面状态或搜索/筛选行为的接口变化。

通知触发还必须按 [NOTIFICATIONS.md](NOTIFICATIONS.md) 覆盖真实状态转换、no-op、重复消费、事务回滚、自通知、收件人状态、资源失效与安全链接；只有列表接口返回 200 不能证明通知链路正确。
