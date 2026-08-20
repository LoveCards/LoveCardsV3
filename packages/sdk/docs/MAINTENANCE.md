# @lovecards/sdk 维护指南

SDK 不是手写 API 清单。后端 Route Contract 是唯一输入，生成流程负责把 Controller 的真实输入/输出投影为 OpenAPI、TypeScript Resource、构建产物和主题公开 browser bundle。

## 当前规模

- 171 条命名路由；
- 25 个生成 Resource；
- 19 条 public、152 条 authenticated；
- HTTP 方法：81 GET、55 POST、22 DELETE、12 PATCH、1 PUT。

这些数字来自 `contracts/route-manifest.json`，不是维护者手工计数。

## 唯一链路

```text
apps/backend/app/api/route/**
  + Controller::$this->validated()
  + Transport/Api Validate scene + requestSchemas()
  + Resource::schema()/transform
        |
        v
contracts/route-manifest.json
contracts/api-inventory.json
contracts/API-AUDIT.md
contracts/openapi.json
        |
        v
packages/sdk/src/generated/api-contract.ts
packages/sdk/src/generated/resources/*.ts
        |
        v
packages/sdk/dist/*
apps/backend/public/_lc/sdk-v2-lovecards.js
```

## 新增或修改接口

1. 在后端 Route 声明唯一 operation name、method/path、access 和 executable contract。
2. Controller 只通过 `$this->validated()` 读取 query/body/file；路径参数由方法参数接收。
3. Validator scene 的字段白名单、requiredness 和 `requestSchemas()` 保持一致。可选字段不能在共享 `$rule` 中残留无条件 `require`。
4. Resource 白名单映射响应，声明 `item/collection/paginated/void/raw` 与 status/content type。
5. 增加后端行为/授权/Resource 测试和契约工作流 fixture。
6. 在仓库根运行：

```bash
npm run generate:sdk
npm run build:sdk
npm run sync:sdk-theme
npm run check
```

7. 在 disposable localhost 环境补真实 SDK integration，确认 SDK 不会丢 query/body/path 参数。

生成差异是接口变更的产物；不允许直接编辑 OpenAPI 或 generated Resource 让类型“看起来正确”。

## 参数位置规则

| Route 输入 | 生成调用 |
| --- | --- |
| `:id` 等路径占位符 | 独立位置参数 |
| GET/DELETE 的非路径字段 | `params` query 对象 |
| POST/PUT/PATCH 的非路径字段 | `data` JSON body |
| 声明为 file/multipart | `FormData` |
| `request => null` | 不生成 params/data |

请求 schema 有必填字段时，params/data 本身也必须必填。例如：

```typescript
client.library.remove({ resource_type, resource_id })
client.messages.send({ recipient_id, body, client_message_id? })
client.notifications.read({ ids })
client.operations.advanceUpgrade(planId, { reason })
```

这条规则由 contract workflow 和 localhost SDK transport 检查保护。

## Resource 与 client 装配

生成器按 operation name 第一段创建 Resource 文件，`client.ts` 装配实例。新增新前缀时，生成结果应同时出现：

- `src/generated/resources/<prefix>.ts`；
- `LCClient` interface property；
- `LCClientImpl` property、constructor 初始化；
- `generatedApiContract` operation/type；
- OpenAPI/route manifest 记录。

`check:generated:sdk` 同时检查缺失、内容漂移和 generated 目录中的多余文件，并在大小写敏感 CI 上验证文件名/import 一致。

## 手写运行时边界

允许手工维护：

- `client.ts`：Axios 装配、Token/role、Resource 实例、hooks；
- `resources/base.ts`：HTTP、解包、query 序列化、retry、dedupe、Abort；
- `errors.ts`：统一 ApiError；
- `types/*`：跨接口复用的命名业务类型；
- 构建配置与安全测试。

不允许手工维护第二套 endpoint path 或重复的业务 Service。`generatedApiContract` 是完整 operation 元数据；历史 `PUBLIC_API` 只用于兼容，不能作为路由真源。

## 构建产物

`build:sdk` 产生：

- ESM：`dist/lovecards.es.js`；
- CJS：`dist/lovecards.cjs`；
- UMD（axios external）：`dist/lovecards.umd.js`；
- 自包含 IIFE：`dist/lovecards.browser.js`；
- `.d.ts` 与 source map。

`sync:sdk-theme` 只复制自包含 browser bundle 到 `apps/backend/public/_lc/sdk-v2-lovecards.js`。`check:sdk-theme` 从当前 source 做临时干净构建，同时比较 dist 和公开资产，避免两个旧 bundle 相互“验证通过”。

## 测试层次

| 门禁 | 保护内容 |
| --- | --- |
| `test:contract-workflow` | scene/schema/controller 漂移、metadata fail-closed、必填对象和参数位置 |
| `check:routes:strict` | 真实 ThinkPHP 路由与 manifest |
| `check:api-contract` | Controller、Validate、Resource、shape；P0/P1 阻断 |
| `check:generated:sdk` | OpenAPI -> generated 新鲜度与文件集合 |
| `test:sdk:transport` | 本地随机 HTTP server 实际观察 query/body/path/数组序列化 |
| `check:sdk-theme` | source -> dist -> Theme public bundle |
| `typecheck:sdk/admin` | SDK 本身与主要消费者编译契约 |
| `test:sdk:integration` | disposable DB + 真实 JWT/HTTP/SDK 的业务语义 |

2026-08-20 的 integration 结果是 133 passed / 0 failed / 24 skipped（157 total），逐项报告在 `../test/TEST_REPORT.md`。Skip 只能用于环境明确关闭的可选能力或空 fixture，不能吞掉接口失败。

## 安全

- integration Base URL 必须为 localhost/127.0.0.1，并显式 `LOVECARDS_SDK_TEST_ISOLATED=1`；
- 账号/密码/Token/私钥只从环境变量注入，不写入测试报告；
- 测试修改密码时必须恢复原密码并刷新全局 client Token；
- 测试创建的数据使用唯一标记并在 finally 删除；
- Release/Theme ZIP、RBAC、config 和批量接口属于高副作用范围，只能在 disposable 环境运行。

后端契约规则详见 `../../../docs/engineering/API-SDK-CONTRACT.md`，前端接手说明见 `../../../docs/engineering/FRONTEND-AGENT-HANDOFF.md`。
