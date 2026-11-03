# @lovecards/sdk

LoveCardsV3 的框架无关 TypeScript/JavaScript 客户端。当前版本 `2.0.0` 由后端 OpenAPI 生成 25 个 Resource，覆盖 174 条命名路由；请求方法、路径、参数位置、必填性、返回 shape 和公开性以生成契约为准。

## 安装与创建客户端

```bash
npm install @lovecards/sdk
```

```typescript
import { createClient, isApiError } from '@lovecards/sdk'

const client = createClient({
  apiUrl: '/api',
  onAuthError: () => location.assign('/auth/login'),
})

const result = await client.session.login({ account, password })
client.setToken(result.token)

const cards = await client.cards.list({ page: 1 })
console.log(cards.data, cards.pagination)
```

客户端自动把 Token 添加到 `Authorization: Bearer` 与 `X-Token`，并支持可注入 TokenStore、当前角色、GET 去重、受配置状态限制的重试和 before/after/error hooks。

## Resource

```text
session       cards         users          comments       tags
likes         files         theme          roles          permissions
config        dashboard     storage        sender         captcha
system        drafts        library        messages       notifications
safety        social        operations     reports        community
```

完整路由分组与 HTTP 契约见 [`docs/API_ENDPOINT_REFERENCE.md`](docs/API_ENDPOINT_REFERENCE.md)。准确调用签名直接查看构建生成的 `.d.ts` 或 `src/generated/resources/*.ts`；不要在文档和源码中手写第二套方法签名。

## 新社区能力示例

```typescript
// 收藏与取消收藏：DELETE 参数是真实 query，不会被 SDK 丢弃
await client.library.save({ resource_type: 'card', resource_id: '24' })
await client.library.remove({ resource_type: 'card', resource_id: '24' })

// 无参列表不需要发送无关占位字段
const notifications = await client.notifications.list()
const drafts = await client.drafts.list()

// 私信重试复用 client_message_id
await client.messages.send({
  recipient_id: 42,
  body: '你好',
  client_message_id: crypto.randomUUID(),
})

// 账号恢复
const challenge = await client.session.recovery_request({ account })
await client.session.recovery_reset({
  challenge_id: Number(challenge.id),
  code,
  password,
})
```

社区与前端路由接手说明见 [`../../docs/engineering/FRONTEND-AGENT-HANDOFF.md`](../../docs/engineering/FRONTEND-AGENT-HANDOFF.md)。

## 响应与错误

SDK 解包统一 API envelope：

```typescript
const { data, pagination } = await client.cards.list()
const card = await client.cards.get(24)
const created = await client.cards.create({ content: '...' })
await client.cards.delete(24) // Promise<void>
```

```typescript
try {
  await client.notifications.read({ ids: [] })
} catch (error) {
  if (isApiError(error)) {
    console.log(error.status, error.code, error.message, error.details)
  }
}
```

401 只有在请求实际携带 Token 时才触发 `onAuthError`。密码修改或账号恢复会使旧 Session 的 security version 失效，调用方应清除旧 Token 并重新登录。

## 契约元数据

`generatedApiContract` 是 174 条路由的生成元数据：

```typescript
import { generatedApiContract } from '@lovecards/sdk'

console.log(generatedApiContract['library.remove'])
// { method: 'DELETE', path: '/me/library', status: 204, ... }
```

`PUBLIC_API` 只保留历史兼容用途，不是完整路由清单；新代码应使用 `generatedApiContract`。

## 构建与同步

在 monorepo 根目录执行：

```bash
npm run generate:sdk       # Route/Validate/Resource -> OpenAPI -> generated source
npm run build:sdk          # 同时构建 ESM/CJS/UMD 与自包含 browser bundle
npm run sync:sdk-theme     # 同步 browser bundle 到后端主题公开资产
npm run check              # 契约、transport、同步、类型和消费者门禁
```

`packages/sdk/src/generated/**`、`contracts/*.json`、`dist/*` 与 `apps/backend/public/_lc/sdk-v2-lovecards.js` 都是生成物，不手工修改。源头与完整维护流程见 [`../../docs/engineering/API-SDK-CONTRACT.md`](../../docs/engineering/API-SDK-CONTRACT.md)。

## 集成测试安全门禁

完整套件会修改账号、内容、配置和 RBAC，只能对一次性 localhost 数据库运行：

```powershell
$env:LOVECARDS_SDK_TEST_ISOLATED='1'
$env:LOVECARDS_SDK_TEST_BASE_URL='http://127.0.0.1:8001/api'
$env:LOVECARDS_SDK_TEST_ADMIN_ACCOUNT='本地测试账号'
$env:LOVECARDS_SDK_TEST_ADMIN_PASSWORD='本地测试密码'
npm run test:sdk:integration
```

运行脚本先重新构建 SDK、同步主题 browser bundle，再执行真实 HTTP。它会拒绝非 localhost、缺少隔离标记或不安全配置。最近结果见 [`test/TEST_REPORT.md`](test/TEST_REPORT.md)。

## 版本策略

| 变更 | SemVer |
| --- | --- |
| 向后兼容的新端点/类型 | minor |
| 修复生成或 transport，不改变公开签名 | patch |
| 删除端点、改变必填性/参数位置/返回 shape | major |

后端 Route Contract 变更是否破坏兼容性，应以生成 `.d.ts` 和 OpenAPI diff 判断，不能只看 URL 是否变化。
