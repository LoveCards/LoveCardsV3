# @lovecards/sdk 设计

最后核对：2026-08-20

## 定位

SDK 是 LoveCards 后端可执行 HTTP 契约的类型安全消费者，不拥有业务规则。Admin 与 SPA Theme 使用同一个 client；PHP Theme Gateway 只提供 HTML/Bootstrap/静态 Release，不在服务端调用 SDK。

```text
Admin / SPA Theme
       |
       v
LCClient -> generated Resource -> BaseResource -> Axios -> /api
                                                   |
                                                   v
                                        ApiResponse / ApiError
```

## 包结构

```text
packages/sdk/
├─ src/
│  ├─ client.ts                    # Axios、Token/role、Resource 装配、hooks
│  ├─ resources/base.ts            # transport、解包、query 序列化、retry/dedupe
│  ├─ generated/
│  │  ├─ api-contract.ts           # 171 operation 的请求/响应类型和运行时元数据
│  │  └─ resources/*.ts            # 25 个生成 Resource
│  ├─ types/*.ts                   # 可复用命名业务类型
│  ├─ errors.ts                    # ApiError
│  ├─ dedupe.ts                    # pending GET Promise map
│  └─ index.ts                     # 公共导出
├─ test/                           # transport、安全门禁、真实 integration
└─ dist/                           # ESM/CJS/UMD/browser + declarations
```

生成 Resource 不继承手写业务 Resource；所有 endpoint 方法都从 OpenAPI 产生。`BaseResource` 是唯一 transport 基类。

## Client 生命周期

`createClient(config)` 为每个 client 创建独立 Axios instance、Deduplicator、hook arrays 和 Resource 实例。TokenStore 默认为浏览器存储，也可注入；角色仅存在当前 client 闭包。

请求拦截器：

1. 从 TokenStore 读取 Token；
2. 同时写入 Bearer Authorization 和 `X-Token`；
3. 若设置当前角色，写入 `X-Role`。

响应错误先标准化为 ApiError。只有“请求真实携带 Token 且返回 401”才调用 `onAuthError`，公开登录失败不会清空另一个有效会话。

## Transport

`BaseResource` 提供 `_get/_post/_patch/_put/_delete`：

- GET/DELETE query 中的数组编码为 JSON 字符串，与后端 InputNormalizer 对齐；
- `order_desc` 编码为后端 stringBool 接受的 `true/false`；
- 相同 method/url/params 的并发 GET 复用 Promise，settle 后从 map 移除；
- 重试只发生在 `retry.retryOn` 明确列出的 HTTP status，次数为 `maxRetries`，线性增加 delay；
- 写请求不自动 dedupe，幂等由业务 key（如 `client_message_id`）保证；
- multipart 由生成方法传 `FormData` 和 content type。

GET 底层支持 AbortSignal，但当前生成 endpoint 方法未将 signal 作为公共参数；消费者不能假设所有方法都可取消。若要公开取消能力，应先扩展 OpenAPI/生成器并补 transport 测试。

## 响应解包

| HTTP/Envelope | SDK 返回 |
| --- | --- |
| 204 | `undefined` / `Promise<void>` |
| `{ data: T }` | `T` |
| `{ data: T[], pagination }` | `{ data, pagination }` |
| 201 且 data=null | `{ id: null }`（审核模式） |
| 非 JSON/raw | 原值 |

Cursor page 是后端 Resource 返回的普通 item，包含 items/next_cursor/has_more；它不会被转换成 offset pagination。

## 错误

`ApiError` 固定包含：

```typescript
class ApiError extends Error {
  code: number
  status: number
  details?: unknown
}
```

网络/超时/取消分别使用负内部 code 与 status 0。HTTP error 优先读取 `{ error: { code, message, details } }`，其次兼容旧 envelope，最后按 status 生成通用消息。

## Hooks

- `beforeRequest`：可添加 header；抛错中断请求。
- `afterResponse`：读取解包后的 data/status/elapsed；hook 错误被隔离。
- `onError`：读取 normalized error、reason、retryable/willRetry；hook 错误被隔离。

注册返回 unsubscribe。Hook 不得修改已经序列化的 params/data，也不得记录 Token 或完整个人数据。

## 生成契约

`GeneratedApiContract` 为每个 Operation ID 提供 request/response/status/shape/public/capabilities 类型，`generatedApiContract` 提供运行时 method/path/status/shape/public/capabilities。它们是完整 171 路由元数据。

Operation ID 第一段决定 client Resource，后续名称生成方法；历史嵌套名称可能规范化为下划线或兼容别名。调用者只使用生成 `.d.ts` 暴露的名字，不根据 Operation ID 自行拼方法。

## 构建目标

| 产物 | 用途 | axios |
| --- | --- | --- |
| `lovecards.es.js` | bundler ESM | external |
| `lovecards.cjs` | CommonJS | external |
| `lovecards.umd.js` | script/legacy integration | external（全局 axios） |
| `lovecards.browser.js` | 零构建 Theme Runtime | bundled/self-contained |
| `.d.ts` | TypeScript | n/a |

browser bundle 经 `sync:sdk-theme` 成为 `apps/backend/public/_lc/sdk-v2-lovecards.js`。Theme Runtime 不引用 workspace 源码或 Node 服务。

## 依赖与演进

SDK 仅依赖 axios。新增业务能力通常只改变 Route Contract 与生成结果；只有跨所有 Resource 的 transport、错误、Token 或 hook 行为才修改手写运行时。

破坏性判断：

- 新增可选字段/新方法通常兼容；
- 必填性、query/body 位置、返回 shape 或错误语义变化可能破坏；
- 删除/重命名方法是 major；
- 手写兼容 alias 必须有明确迁移期和测试，不能无限累积。

维护步骤见 `MAINTENANCE.md`，完整端点见 `API_ENDPOINT_REFERENCE.md`。
