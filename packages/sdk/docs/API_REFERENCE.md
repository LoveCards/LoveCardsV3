# @lovecards/sdk 使用参考

本文件提供调用模式和高风险接口示例。174 条路由的完整清单见 `API_ENDPOINT_REFERENCE.md`，精确类型以生成 `.d.ts` 为准。

## Client

```typescript
import { createClient } from '@lovecards/sdk'

const client = createClient({
  apiUrl: '/api',
  timeout: 15_000,
  retry: { maxRetries: 2 },
  onAuthError: () => location.assign('/auth/login'),
})
```

`setToken/getToken/clearToken` 管理当前身份，`setRole/getRole` 管理显式管理角色。自定义 `tokenStore` 可接入 SSR-safe 或应用自己的存储；不要在模块中直接读写 SDK 默认 localStorage key。

## 返回 shape

```typescript
const page = await client.cards.list()
page.data
page.pagination

const card = await client.cards.get(24)
const result = await client.cards.create({ content: '正文' })
await client.cards.delete(24) // void
```

cursor 接口返回包含 `items/next_cursor/has_more` 的业务对象；不要把它当 `ListResult`。实时连接先调用 `notifications.streamTicket()` 获取短期 Gateway ticket，再由主题端建立可取消的 EventSource；通知事实仍通过 cursor API 回源。

## 认证与恢复

```typescript
const auth = await client.session.policy()
const login = await client.session.login({ account, password })
client.setToken(login.token)
await client.session.check()
await client.session.logout()

const challenge = await client.session.recovery_request({ account })
await client.session.recovery_reset({ challenge_id, code, password })
```

Visitor Mode 关闭时 `session.guest()` 会返回明确 401；调用方应按 `session.policy()` 展示入口，不把它当系统故障。密码变更或恢复后旧 Token 会立即失效。

## 内容、评论与文件

```typescript
const created = await client.cards.create({
  content,
  picture_file_ids: uploadedIds,
  cover_file_id: uploadedIds[0],
})

const comments = await client.comments.cardList(cardId, { page: 1 })
const target = await client.comments.get(commentId)
await client.comments.create(cardId, { content, parent_id })

const formData = new FormData()
formData.append('file', file)
formData.append('scene', 'card')
const uploaded = await client.files.upload(formData)
```

公开文件深链使用 `/media/:hash`，不能从 driver path 或对象存储内部 key 自行拼接。文件永久 purge 与引用检查是管理 API，不是用户删除别名。

## 草稿、收藏与历史

```typescript
const drafts = await client.drafts.list()
const draft = await client.drafts.create({ title, content, payload })
await client.drafts.update(Number(draft.id), { content, payload })

await client.library.save({ resource_type: 'card', resource_id: '24' })
await client.library.remove({ resource_type: 'card', resource_id: '24' })
const saved = await client.library.list()

await client.library.history_record({ resource_type: 'card', resource_id: '24' })
const history = await client.library.history()
await client.library.history_clear()
```

`library.remove` 参数是必填 DELETE query。SDK transport 已覆盖这类参数；不要将它改回无参方法。

## 社交、私信与通知

```typescript
const profile = await client.social.profile(handle)
const relation = await client.social.relation(Number(profile.id))
await client.social.follow(Number(profile.id))
await client.social.unfollow(Number(profile.id))

const conversations = await client.messages.conversations()
const messages = await client.messages.list(conversationId)
await client.messages.send({ recipient_id, body, client_message_id })

const notifications = await client.notifications.list({ unread_only: true, category: 'engagement' })
const engagementUnread = await client.notifications.unread({ category: 'engagement' })
await client.notifications.read({ ids: [1, 2] })
await client.notifications.readAll({ category: 'engagement' })
```

消息发送的网络重试应复用同一个 `client_message_id`。SSE 不可用时继续调用 list/unread，数据正确性不依赖实时连接。

## 隐私、安全与举报

```typescript
const preferences = await client.safety.preferences()
await client.safety.preferences_update({
  profile_visibility: 'public',
  messages_from: 'following',
  show_relations: true,
})
await client.safety.block(userId)
await client.safety.unblock(userId)
await client.safety.report({
  resource_type: 'card',
  resource_id: '24',
  reason: 'spam',
  details: '补充说明',
})
```

reason 必须来自公开 schema；非法字段由该 scene 单独返回错误，不应在前端增加别的 scene 字段。

## 管理端 Operations

```typescript
const runtime = await client.operations.runtime()
const deliveries = await client.operations.deliveries({ status: 'failed' })
await client.operations.retryDelivery(deliveryId, { reason })

const plans = await client.operations.upgrades({ limit: 50 })
const formData = new FormData()
formData.append('file', releaseZip)
formData.append('reason', reason)
const plan = await client.operations.createUpgrade(formData)
await client.operations.advanceUpgrade(plan.plan_id, { reason })
await client.operations.approveUpgradeContract(plan.plan_id, { reason })
await client.operations.recoverUpgrade(plan.plan_id, { reason })
```

Contract 批准和恢复是独立 capability。前端按钮 guard 只改善体验，后端 Route access 与状态机才是安全边界。

## 错误、去重与 hooks

```typescript
import { isApiError } from '@lovecards/sdk'

const stop = client.hooks.onError((context) => {
  // 统一观测；不要记录 Token 或完整隐私数据
})

try {
  await client.messages.send({ recipient_id, body: '' })
} catch (error) {
  if (isApiError(error)) console.log(error.details)
}

stop()
```

相同 GET 会自动去重；写请求不会自动幂等，业务必须使用 API 提供的唯一键。重试只适用于允许的错误/方法，不要把所有 POST 都无条件重放。

## 契约元数据与维护

```typescript
import { generatedApiContract } from '@lovecards/sdk'

generatedApiContract['messages.send']
generatedApiContract['operations.createUpgrade']
```

新增或修改 API 的完整顺序见 `MAINTENANCE.md`。任何签名与本文冲突时，以当前生成源码、OpenAPI 和 Route Contract 为准，并修正文档，不维护兼容假象。
