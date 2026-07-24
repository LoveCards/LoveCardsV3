# @lovecards/sdk API Reference

> 覆盖后端 55 个路由，16 个模块。所有方法返回解包后的业务数据。

---

## 统一响应格式

SDK 方法返回解包后的业务数据，不返回 HTTP 包装：

```typescript
// 列表
const { data, pagination } = await client.cards.list()
// data: Card[], pagination?: PaginationInfo

// 单条
const card = await client.cards.get(1)
// card: Card

// 创建
const { id } = await client.cards.create({ content: '...' })
// id: string | null（null = 审核模式）

// 更新/删除
await client.cards.update(1, { content: '...' })
await client.cards.delete(1)
```

错误通过 `ApiError` 抛出：

```typescript
import { isApiError } from '@lovecards/sdk'

try {
  await client.cards.create({ content: '...' })
} catch (e) {
  if (isApiError(e)) {
    console.log(e.code, e.message, e.status)
  }
}
```

---

## 快速入门

```typescript
import { createClient } from '@lovecards/sdk'

const client = createClient({ apiUrl: '/api' })

// 公开接口（无需 token）
const { data } = await client.cards.list()

// 登录后设置 token
const { token } = await client.session.login({ account: 'admin@test.com', password: '123456' })
client.setToken(token)

// 需要 token 的接口
const me = await client.users.me()
```

---

## Session — 认证（6 端点）

### 登录

```typescript
const { token } = await client.session.login({ account: 'admin@test.com', password: '123456' })
client.setToken(token)
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `session.login(data)` | `POST` | `/session/login` | `LoginResult` |
| `session.register(data)` | `POST` | `/session/register` | `LoginResult` |
| `session.guest()` | `POST` | `/session/guest` | `LoginResult` |
| `session.captcha(params)` | `POST` | `/session/captcha` | `void` |
| `session.logout()` | `POST` | `/session/logout` | `void` |
| `session.check()` | `GET` | `/session/check` | `void` |

---

## Cards — 卡片（10 端点）

### 列表

```typescript
const { data, pagination } = await client.cards.list({ page: 1, list_rows: 15 })
// data: Card[], pagination: { currentPage, totalPages, totalItems, itemsPerPage }
```

### 创建（审核模式感知）

```typescript
const { id } = await client.cards.create({ content: '...' })
if (id) {
  router.push(`/cards/${id}`)
} else {
  showToast('已提交，等待审核')
}
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `cards.list(params?)` | `GET` | `/cards` | `ListResult<Card>` |
| `cards.get(id)` | `GET` | `/cards/:id` | `Card` |
| `cards.hot()` | `GET` | `/cards/hot` | `Card[]` |
| `cards.search(params)` | `GET` | `/cards/search` | `ListResult<Card>` |
| `cards.create(data)` | `POST` | `/cards` | `CreateResult` |
| `cards.update(id, data)` | `PATCH` | `/cards/:id` | `void` |
| `cards.delete(id)` | `DELETE` | `/cards/:id` | `void` |
| `cards.like(id)` | `POST` | `/cards/:id/like` | `{ likes: number }` |
| `cards.listOwn(params?)` | `GET` | `/users/me/cards` | `ListResult<Card>` |
| `cards.batch(data)` | `POST` | `/cards/batch` | `void` |

---

## Users — 用户（10 端点）

### 获取当前用户

```typescript
const user = await client.users.me()
// user: User { id, username, email, avatar, roles_id, status, ... }
```

### 更新资料

```typescript
await client.users.updateMe({ username: '新昵称' })
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `users.me()` | `GET` | `/users/me` | `User` |
| `users.updateMe(data)` | `PATCH` | `/users/me` | `void` |
| `users.updatePassword(data)` | `POST` | `/users/me/password` | `void` |
| `users.updateEmail(data)` | `POST` | `/users/me/email` | `void` |
| `users.emailCaptcha(data)` | `POST` | `/users/me/email-captcha` | `void` |
| `users.list(params?)` | `GET` | `/users` | `ListResult<User>` |
| `users.get(id)` | `GET` | `/users/:id` | `User` |
| `users.update(id, data)` | `PATCH` | `/users/:id` | `void` |
| `users.delete(id)` | `DELETE` | `/users/:id` | `void` |
| `users.batch(data)` | `POST` | `/users/batch` | `void` |

---

## Comments — 评论（7 端点）

### 获取卡片评论

```typescript
const { data, pagination } = await client.comments.cardList(1)
// data: Comment[]
```

### 创建评论

```typescript
const { id } = await client.comments.create(1, { content: '好卡片！' })
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `comments.cardList(cardId, params?)` | `GET` | `/cards/:id/comments` | `ListResult<Comment>` |
| `comments.create(cardId, data)` | `POST` | `/cards/:id/comments` | `CreateResult` |
| `comments.get(id)` | `GET` | `/comments/:id` | `Comment` |
| `comments.update(id, data)` | `PATCH` | `/comments/:id` | `void` |
| `comments.delete(id)` | `DELETE` | `/comments/:id` | `void` |
| `comments.listOwn(params?)` | `GET` | `/users/me/comments` | `ListResult<Comment>` |
| `comments.batch(data)` | `POST` | `/comments/batch` | `void` |

---

## Tags — 标签（6 端点）

```typescript
const tags = await client.tags.list({ list_rows: 100 })
// tags: Tag[]
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `tags.list(params?)` | `GET` | `/tags` | `Tag[]` |
| `tags.get(id)` | `GET` | `/tags/:id` | `Tag` |
| `tags.create(data)` | `POST` | `/tags` | `void` |
| `tags.update(id, data)` | `PATCH` | `/tags/:id` | `void` |
| `tags.delete(id)` | `DELETE` | `/tags/:id` | `void` |
| `tags.batch(data)` | `POST` | `/tags/batch` | `void` |

---

## Likes — 点赞（2 端点）

```typescript
const likes = await client.likes.list()
// likes: Like[]

await client.likes.unlike(1)
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `likes.list(params?)` | `GET` | `/likes` | `Like[]` |
| `likes.unlike(id)` | `DELETE` | `/likes/:id` | `void` |

---

## Files — 文件（10 端点）

### 文件上传

```typescript
const formData = new FormData()
formData.append('file', fileInput.files[0])
const result = await client.files.upload(formData)
// result: UploadResult { id, url, original_name, ... }
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `files.upload(formData)` | `POST` | `/files` | `UploadResult` |
| `files.list(params?)` | `GET` | `/files` | `ListResult<LCFile>` |
| `files.listOwn(params?)` | `GET` | `/users/me/files` | `ListResult<LCFile>` |
| `files.listMe(params?)` | `GET` | `/users/me/files` | `ListResult<LCFile>`（兼容别名） |
| `files.get(id)` | `GET` | `/files/:id` | `LCFile` |
| `files.direct(data?)` | `POST` | `/files/direct` | `DirectUploadResult` |
| `files.confirm(id)` | `PATCH` | `/files/:id/confirm` | `void` |
| `files.batch(data)` | `POST` | `/files/batch` | `void` |
| `files.cleanup()` | `DELETE` | `/files/expired` | `void` |
| `files.delete(id)` | `DELETE` | `/files/:id` | `void` |

---

## Theme — 主题（8 端点）

### 获取主题配置（公开）

```typescript
const config = await client.theme.publicConfig()
// config: ThemeConfigData { name, mode, config_schema, config_values }
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `theme.list()` | `GET` | `/theme/list` | `ThemeItem[]` |
| `theme.upload(formData)` | `POST` | `/theme/upload` | `void` |
| `theme.activate(data)` | `POST` | `/theme/activate` | `void` |
| `theme.config()` | `GET` | `/theme/config` | `ThemeConfigData` |
| `theme.updateConfig(data)` | `PUT` | `/theme/config` | `void` |
| `theme.freeze()` | `POST` | `/theme/freeze` | `void` |
| `theme.delete(data)` | `DELETE` | `/theme/delete` | `void` |
| `theme.publicConfig()` | `GET` | `/theme/config` | `ThemeConfigData` |

---

## Roles — 角色（8 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `roles.list(params?)` | `GET` | `/roles` | `ListResult<Role>` |
| `roles.get(id)` | `GET` | `/roles/:id` | `Role` |
| `roles.create(data)` | `POST` | `/roles` | `{ id: string }` |
| `roles.update(id, data)` | `PATCH` | `/roles/:id` | `void` |
| `roles.delete(id)` | `DELETE` | `/roles/:id` | `void` |
| `roles.getCapabilities(id)` | `GET` | `/roles/:id/capabilities` | `string[]` |
| `roles.assignCapabilities(id, data)` | `POST` | `/roles/:id/capabilities` | `void` |
| `roles.reseed()` | `POST` | `/roles/reseed` | `ReseedResult` |

---

## Permissions — 权限（2 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `permissions.list(params?)` | `GET` | `/permissions` | `ListResult<CapabilityItem>` |
| `permissions.all()` | `GET` | `/permissions/all` | `CapabilityItem[]` |

---

## Config — 系统配置（8 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `config.list()` | `GET` | `/config` | `ConfigData` |
| `config.update(data)` | `POST` | `/config` | `void` |
| `config.groups()` | `GET` | `/config/groups` | `string[]` |
| `config.init()` | `POST` | `/config/init` | `void` |
| `config.register(data)` | `POST` | `/config/register` | `void` |
| `config.reload()` | `POST` | `/config/reload` | `void` |
| `config.deleteGroup(group)` | `DELETE` | `/config` | `void` |
| `config.deleteKey(group, key)` | `DELETE` | `/config/key` | `void` |

---

## Dashboard — 控制台（1 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `dashboard.index()` | `GET` | `/dashboard` | `DashboardData` |

---

## System — 系统（1 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `system.update()` | `GET` | `/system/update` | `SystemUpdateInfo` |

---

## Storage — 存储管理（6 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `storage.types()` | `GET` | `/storage/types` | `StorageDriver[]` |
| `storage.meta(type)` | `GET` | `/storage/:type/meta` | `StorageMeta` |
| `storage.install()` | `POST` | `/storage/install` | `void` |
| `storage.channels()` | `GET` | `/storage/channels` | `StorageChannel[]` |
| `storage.testChannel(data)` | `POST` | `/storage/test-channel` | `{ success, message? }` |
| `storage.channelStats()` | `GET` | `/storage/channel-stats` | `ChannelStats` |

---

## Sender — 消息发送器（6 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `sender.types()` | `GET` | `/sender/types` | `SenderType[]` |
| `sender.meta(type)` | `GET` | `/sender/:type/meta` | `SenderMeta` |
| `sender.install()` | `POST` | `/sender/install` | `void` |
| `sender.channels()` | `GET` | `/sender/channels` | `SenderChannel[]` |
| `sender.templates()` | `GET` | `/sender/templates` | `SenderTemplate[]` |
| `sender.testChannel(data)` | `POST` | `/sender/test-channel` | `{ success, message? }` |

---

## Captcha — 验证码（5 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `captcha.types()` | `GET` | `/captcha/types` | `CaptchaDriver[]` |
| `captcha.drivers()` | `GET` | `/captcha/drivers` | `CaptchaDriver[]` |
| `captcha.meta(slug)` | `GET` | `/captcha/:slug/meta` | `CaptchaMeta` |
| `captcha.install()` | `POST` | `/captcha/install` | `void` |
| `captcha.config()` | `GET` | `/captcha/config` | `CaptchaConfig`（公开） |

---

## 错误码参考

### HTTP 状态码

| 状态码 | 说明 |
|--------|------|
| 400 | 请求参数错误 |
| 401 | token 无效/过期 |
| 403 | 权限不足 |
| 404 | 资源不存在 |
| 422 | 参数验证失败 |
| 429 | 请求过于频繁 |
| 500 | 服务器内部错误 |

### 业务码

| 业务码 | 消息 | 说明 |
|--------|------|------|
| 1001 | 用户不存在 | — |
| 1005 | 登录失败 | 账号或密码错误 |
| 1101 | 权限不足 | — |
| 2001 | 卡片不存在 | — |
| 3001 | 评论不存在 | — |
| 4001 | 标签不存在 | — |
| 9001 | 系统异常 | 服务器内部错误 |
| 9002 | 参数错误 | 请求参数验证失败 |
| 9003 | 资源不存在 | — |

---

## 生命周期钩子

SDK 提供 3 个生命周期钩子，用于监控和干预请求/响应流程。

### 构造时注册

```typescript
const client = createClient({
  apiUrl: '/api',
  hooks: {
    beforeRequest: (ctx) => {
      ctx.config.headers['X-Trace-Id'] = crypto.randomUUID()
    },
    afterResponse: (ctx) => {
      console.log(`${ctx.method} ${ctx.url} → ${ctx.status} (${ctx.elapsedMs}ms)`)
    },
    onError: (ctx) => {
      console.error(`[${ctx.reason}] ${ctx.status}: ${ctx.message}`)
    },
  },
})
```

### 运行时注册

```typescript
// 注册
const unsub = client.hooks.afterResponse((ctx) => {
  logToAnalytics(ctx)
})

// 取消注册
unsub()
```

### Hook 类型参考

| 钩子 | 回调参数 | 可修改请求 |
|------|---------|-----------|
| `beforeRequest` | `RequestContext` | `config.headers` |
| `afterResponse` | `ResponseContext` | 只读 |
| `onError` | `ErrorContext` | 只读 |

详见 `SDK_DESIGN.md` 生命周期钩子章节。
