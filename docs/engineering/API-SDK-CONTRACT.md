# API、OpenAPI 与 SDK 契约

本文定义从后端路由到前端消费者的唯一契约链。目标是让 Controller 的真实输入、运行时校验、OpenAPI、SDK 源码、浏览器产物始终表达同一件事。

## 1. 唯一契约链

```text
Route Meta / Contract
  -> Controller
  -> Validate scene
  -> Application / Service
  -> Resource / ApiResponse
  -> route-manifest + API audit + OpenAPI
  -> generated SDK
  -> SDK dist
  -> 主题公开 SDK
  -> Admin / Theme 消费者
```

Route Meta 声明接口身份、认证、capability 和 scope；Route Contract 声明请求校验场景与响应形状。目录名、Controller 注释或手写 SDK 方法都不能替代这份可执行契约。

截至 2026-08-20，真源导出 171 条命名路由（19 public、152 required）和 25 个 SDK Resource。生成目录共 26 个 TypeScript 文件：1 个 `api-contract.ts` 加 25 个 Resource；文件数不能误写成 Resource 数。`contracts/route-manifest.json`、`api-inventory.json`、`openapi.json` 与 `packages/sdk/src/generated/**` 必须由同一次生成保持一致。

## 2. 请求契约

每个会读取 query、body 或上传文件的路由，都必须声明 `request.validate` 和 `request.scene`。Validator 的 `$all_scene` 是运行时字段白名单，`requestSchemas()` 是 OpenAPI/SDK 的公开 schema，两者的字段与必填性必须一致。

ThinkPHP `$rule` 是所有 scene 的共享规则集合。可选字段不能在共享规则中保留无条件 `require`，否则即使 `scene()` 只选了字段也会在无参列表请求中变成必填。requiredness 必须由 `$all_scene`/scene 对该接口明确声明；契约工作流对此有反例门禁。

Controller 只能通过路由拥有的 `$this->validated()` 取得这些输入。以下入口会绕过契约，禁止在 API Controller 中使用：

- `Request::param()` 或其他 `Request::*` 输入方法；
- `request()->param()`、`request()->input()` 等直接读取；
- 旧的 `$this->param()`、`$this->paramIndex()`；
- 声明了 Validator scene，却从原始请求补读未进入 scene 的字段。

上传接口可以先取得文件对象，再通过 `$this->validated(null, ['file' => $file])` 合并到同一校验入口；文件字段仍必须出现在 scene、rule 和 request schema 中。

已移除的高风险开关可以在 Route Contract 中用内部 `rejectTruthy` 规则继续 fail-closed，例如旧 `allow_contract=true`。它只用于拒绝废弃输入，不会把字段交给 Controller，也不会进入 OpenAPI/SDK；不得借此维护“公开 schema 之外仍可使用”的隐藏参数。契约工作流必须同时断言公开字段集合和内部拒绝规则。

### `request: null`

`request => null` 表示接口没有 query、body 或上传输入。除路由路径占位符由框架注入外，Controller 不得读取请求数据，OpenAPI 也不得生成空 query 或任意 body。它不是“暂时没有写 schema”的兼容写法。

### 动态请求

动态配置等接口仍然必须有显式 Validator scene 和 `requestSchemas()`。确需允许动态键时，运行时 scene 要显式选择 passthrough，schema 也要使用受约束的 `additionalProperties` 描述值结构。动态请求不应退化为 `request: null`、裸 `Request::param()` 或无边界的 `any`。

### HTTP 参数位置

- 路径占位符生成独立方法参数；
- GET、DELETE 的非路径字段生成 query 参数对象；
- POST、PUT、PATCH 的非路径字段生成 JSON body 或声明过的 multipart body；
- schema 存在必填字段时，对应 `params`/`data` 对象本身也必须必填。

因此，Controller 输入、OpenAPI 参数位置与生成 SDK 的调用签名不能分别维护。

## 3. 响应契约

Resource 是公开数据字段与映射的唯一入口，不能暴露存储内部路径、凭证、原始权限实现或其他敏感字段。常规响应使用 `item`、`collection`、`paginated`、`void` 等明确 shape；SSE、文件流等原始响应必须显式声明 `shape=raw`、返回类型和 content type，不能伪装为 JSON item。

深链接口应返回稳定公开标识和可选 anchor。前端首次加载 Bootstrap 后读取当前 URL，通过 SDK 请求资源；权限失败应先于自动定位处理。

## 4. 审计级别

`audit-api-contract` 对每条路由交叉检查 Controller、Validator scene、request schema、Resource 和响应方式：

- P0：契约无法解析或关键实现缺失，阻断；
- P1：实现与公开契约可能不一致，阻断；
- P2：完整性或维护性提示，记录但不阻断。

生成和 `--check` 模式都以 P0/P1 为失败。已有 route contract 不会跳过 Controller 检查；审计输出本身也必须与当前源码一致。

## 5. 源码与生成物所有权

| 层级 | 路径 | 维护方式 |
| --- | --- | --- |
| 后端契约真源 | `apps/backend/app/api/route/**`、Controller、模块 `Transport/Api` 下的 Validate/Resource | 手工维护 |
| 契约报告 | `contracts/route-manifest.json`、`api-inventory.json`、`API-AUDIT.md`、`openapi.json` | 根脚本生成，不手改 |
| SDK 手写运行时 | `packages/sdk/src` 中 generated 目录之外的 client、transport、类型工具 | 手工维护 |
| SDK 契约源码 | `packages/sdk/src/generated/**` | 从 OpenAPI 生成，不手改 |
| 浏览器 SDK | `packages/sdk/dist/lovecards.browser.js` | 从当前 SDK source 构建，不手改 |
| 主题公开 SDK | `apps/backend/public/_lc/sdk-v2-lovecards.js` | 从 SDK dist 同步，不手改 |

`check:sdk-theme` 必须以当前 SDK source 的临时新构建为基准，同时核对 dist 与主题公开资产；只比较两个同样过期的 bundle 不构成有效门禁。

预期变更使用：

```powershell
npm.cmd run generate:sdk
npm.cmd run build:sdk
npm.cmd run sync:sdk-theme
```

随后运行 `npm.cmd run check`。修复生成物时不能直接编辑 OpenAPI、generated resource、dist 或公开资产。

真实 SDK 集成入口先重新生成/构建 SDK，再同步主题公开 bundle，最后执行 HTTP 套件；这是 `test:sdk:integration` 根脚本的固定顺序，不能在集成测试后留下旧的浏览器资产。

## 6. 回归门禁

根 `check` 至少覆盖：

- `test:contract-workflow`：用故障夹具固定 Controller/scene/schema 漂移、P1 阻断、metadata fail-closed、HTTP 参数位置与必填签名；
- `check:routes:strict` 与 `check:api-contract`：加载真实后端契约并检查报告新鲜度；
- `check:generated:sdk`：检查缺失、内容漂移以及多余 generated 文件；
- `test:sdk:transport`：在随机 localhost HTTP server 上调用从当前 SDK source 构建的真实 client，验证 query、path+query、JSON body 和数组序列化；
- `check:sdk-theme`：检查 source、dist、主题公开资产没有断层；
- SDK、Admin 与默认主题的类型或源码门禁。

localhost transport 测试无业务数据库副作用，应进入默认检查；需要真实数据库和认证数据的 SDK integration 仍只在 disposable localhost 环境运行。

2026-08-20 的真实 SDK 回归为 133 passed、0 failed、24 skipped（157 total）。Skip 来自隔离环境关闭 Visitor Mode、没有活动主题，或没有可用卡片/标签/评论/文件 fixture；它们不是失败项。社区请求 scene、密码变更导致旧 JWT 失效、配置无参读取、管理运维读取、Contract 直跑拒绝及 transport 参数位置均通过。详细逐项结果以 `packages/sdk/test/TEST_REPORT.md` 为准。

同日根质量门禁以 exit 0 完成：171 routes，API audit P0/P1/P2 均为 0，生成物检查覆盖 26 个文件，SDK transport 覆盖 26 个真实请求。这个结果证明当前源码与生成链一致，不代表被 Skip 的主题绑定或内容 fixture 场景已经由该 SDK 套件执行；这些场景由各自 Theme/E2E 套件补充。

## 7. 跨平台约束

CI 在 Ubuntu 上运行，用于暴露 Windows 文件系统会掩盖的问题：

- generated resource 文件名和 import 必须使用一致的小写形式；
- route manifest 中持久化的 source 路径统一使用 `/`；
- 检查 generated 目录时必须同时发现内容漂移和多余文件；
- 不依赖大小写不敏感路径、Windows 路径分隔符或开发机现存 bundle。

新增或修改接口的完整测试步骤见 `BACKEND-TESTING.md`。
