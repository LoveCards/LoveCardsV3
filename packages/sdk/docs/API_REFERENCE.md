# @lovecards/sdk API Reference

> 覆盖后端 91 个命名路由，16 个模块。每个模块一个代表性示例，其余只列方法签名。

---

## 统一响应格式

```typescript
// 成功（列表，含分页）
interface ApiResponse<T> {
  data: T
  pagination?: PaginationInfo
}

interface PaginationInfo {
  currentPage: number
  totalPages: number
  totalItems: number
  itemsPerPage: number
}
```

错误通过 `LoveCardsError` 层次抛出：

```typescript
try {
  const { data } = await client.cards.list()
} catch (e) {
  if (e instanceof ValidationError) {
    console.log(e.code, e.message, e.details)
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
client.setToken(localStorage.getItem('token'))

// 需要 token 的接口
const { data: user } = await client.users.me()
```

---

## Cards — 卡片（14 端点）

### 列表

```typescript
const { data, pagination } = await client.cards.list({ page: 1, list_rows: 15 })
// data: Card[], pagination: { currentPage, totalPages, totalItems, itemsPerPage }
```

### 单条

```typescript
const { data: card } = await client.cards.get(1)
// card: Card
```

### 创建

```typescript
const { data: result } = await client.cards.create({ content: '...', tags: '[1,2]' })
// result: { id: string }
```

### 其余方法

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `cards.hot()` | `GET` | `/cards/hot` | `Card[]` |
| `cards.search(params)` | `GET` | `/cards/search` | `Card[]` |
| `cards.update(id, data)` | `PATCH` | `/cards/:id` | `void` |
| `cards.delete(id)` | `DELETE` | `/cards/:id` | `void` |
| `cards.like(id)` | `POST` | `/cards/:id/like` | `void` |
| `cards.listOwn()` | `GET` | `/users/me/cards` | `Card[]` |
| `cards.allList(params?)` | `GET` | `/all/cards` | `Card[]` |
| `cards.allGet(id)` | `GET` | `/all/cards/:id` | `Card` |
| `cards.allUpdate(id, data)` | `PATCH` | `/all/cards/:id` | `void` |
| `cards.allDelete(id)` | `DELETE` | `/all/cards/:id` | `void` |
| `cards.batch(data)` | `POST` | `/all/cards/batch` | `void` |

---

## Session — 认证（6 端点）

### 登录

```typescript
const { data } = await client.session.login({ account: 'admin@test.com', password: '123456' })
// data: { token: string, user: User }
client.setToken(data.token)
```

### 其余方法

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `session.register(data)` | `POST` | `/session/register` | `LoginResult` |
| `session.guest()` | `POST` | `/session/guest` | `LoginResult` |
| `session.captcha(params)` | `POST` | `/session/captcha` | `void` |
| `session.logout()` | `POST` | `/session/logout` | `void` |
| `session.check()` | `GET` | `/session/check` | `void` |

---

## Users — 用户（10 端点）

### 获取当前用户

```typescript
const { data: user } = await client.users.me()
// user: { id, username, email, avatar, ... }
```

### 更新资料

```typescript
await client.users.updateMe({ username: '新昵称' })
```

### 其余方法

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `users.updatePassword(data)` | `POST` | `/users/me/password` | `void` |
| `users.updateEmail(data)` | `POST` | `/users/me/email` | `void` |
| `users.emailCaptcha(data)` | `POST` | `/users/me/email-captcha` | `void` |
| `users.allList(params?)` | `GET` | `/all/users` | `User[]` |
| `users.allGet(id)` | `GET` | `/all/users/:id` | `User` |
| `users.allUpdate(id, data)` | `PATCH` | `/all/users/:id` | `void` |
| `users.allDelete(id)` | `DELETE` | `/all/users/:id` | `void` |
| `users.batch(data)` | `POST` | `/all/users/batch` | `void` |

---

## Comments — 评论（11 端点）

### 获取卡片评论

```typescript
const { data, pagination } = await client.comments.cardList(1)
// data: Comment[]
```

### 创建评论

```typescript
const { data: comment } = await client.comments.create(1, { content: '好卡片！' })
// comment: Comment
```

### 其余方法

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `comments.get(id)` | `GET` | `/comments/:id` | `Comment` |
| `comments.update(id, data)` | `PATCH` | `/comments/:id` | `void` |
| `comments.delete(id)` | `DELETE` | `/comments/:id` | `void` |
| `comments.listOwn()` | `GET` | `/users/me/comments` | `Comment[]` |
| `comments.allList(params?)` | `GET` | `/all/comments` | `Comment[]` |
| `comments.allGet(id)` | `GET` | `/all/comments/:id` | `Comment` |
| `comments.allUpdate(id, data)` | `PATCH` | `/all/comments/:id` | `void` |
| `comments.allDelete(id)` | `DELETE` | `/all/comments/:id` | `void` |
| `comments.batch(data)` | `POST` | `/all/comments/batch` | `void` |

---

## Tags — 标签（10 端点）

### 标签列表

```typescript
const { data: tags } = await client.tags.list({ list_rows: 100 })
// tags: Tag[]
```

### 其余方法

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `tags.get(id)` | `GET` | `/tags/:id` | `Tag` |
| `tags.create(data)` | `POST` | `/tags` | `void` |
| `tags.update(id, data)` | `PATCH` | `/tags/:id` | `void` |
| `tags.delete(id)` | `DELETE` | `/tags/:id` | `void` |
| `tags.allList(params?)` | `GET` | `/all/tags` | `Tag[]` |
| `tags.allCreate(data)` | `POST` | `/all/tags` | `void` |
| `tags.allUpdate(id, data)` | `PATCH` | `/all/tags/:id` | `void` |
| `tags.allDelete(id)` | `DELETE` | `/all/tags/:id` | `void` |
| `tags.batch(data)` | `POST` | `/all/tags/batch` | `void` |

---

## Likes — 点赞（2 端点）

```typescript
const { data: items } = await client.likes.list()
// items: LikeItem[]

await client.likes.unlike(1)  // 取消点赞
```

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `likes.list()` | `GET` | `/likes` | `LikeItem[]` |
| `likes.unlike(id)` | `DELETE` | `/likes/:id` | `void` |

---

## Files — 文件（8 端点）

### 文件上传

```typescript
const formData = new FormData()
formData.append('file', fileInput.files[0])
const { data: file } = await client.files.upload(formData)
// file: FileItem
```

### 其余方法

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `files.list(params?)` | `GET` | `/files` | `FileItem[]` |
| `files.get(id)` | `GET` | `/files/:id` | `FileItem` |
| `files.direct()` | `POST` | `/files/direct` | `DirectUploadResult` |
| `files.confirm(id)` | `PATCH` | `/files/:id/confirm` | `void` |
| `files.batch(data)` | `POST` | `/files/batch` | `void` |
| `files.cleanup()` | `DELETE` | `/files/expired` | `void` |
| `files.allDelete(id)` | `DELETE` | `/all/files/:id` | `void` |

---

## Theme — 主题（8 端点）

### 获取主题配置（公开）

```typescript
const { data: config } = await client.theme.publicConfig()
// config: { theme: string, config: Record<string, any> }
```

### 其余方法

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `theme.list()` | `GET` | `/all/theme/list` | `ThemeItem[]` |
| `theme.upload(formData)` | `POST` | `/all/theme/upload` | `void` |
| `theme.activate(data)` | `POST` | `/all/theme/activate` | `void` |
| `theme.config()` | `GET` | `/all/theme/config` | `ThemeConfigData` |
| `theme.updateConfig(data)` | `PUT` | `/all/theme/config` | `void` |
| `theme.freeze()` | `POST` | `/all/theme/freeze` | `void` |
| `theme.delete(data)` | `DELETE` | `/all/theme/delete` | `void` |

---

## Roles — 角色（8 端点，管理端）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `roles.list(params?)` | `GET` | `/all/roles` | `Role[]` |
| `roles.get(id)` | `GET` | `/all/roles/:id` | `Role` |
| `roles.create(data)` | `POST` | `/all/roles` | `void` |
| `roles.reseed()` | `POST` | `/all/roles/reseed` | `void` |
| `roles.assignPermissions(id, data)` | `POST` | `/all/roles/:id/permissions` | `void` |
| `roles.getRolePermissions(id)` | `GET` | `/all/roles/:id/permissions` | `number[]` |
| `roles.update(id, data)` | `PATCH` | `/all/roles/:id` | `void` |
| `roles.delete(id)` | `DELETE` | `/all/roles/:id` | `void` |

---

## Permissions — 权限（2 端点，管理端）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `permissions.list(params?)` | `GET` | `/all/permissions` | `PermissionItem[]` |
| `permissions.all()` | `GET` | `/all/permissions/all` | `PermissionItem[]` |

---

## Config — 系统配置（8 端点，管理端）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `config.list(params?)` | `GET` | `/all/config` | `ConfigItem[]` |
| `config.update(data)` | `POST` | `/all/config` | `void` |
| `config.groups()` | `GET` | `/all/config/groups` | `ConfigGroup[]` |
| `config.init()` | `POST` | `/all/config/init` | `void` |
| `config.register(data)` | `POST` | `/all/config/register` | `void` |
| `config.reload()` | `POST` | `/all/config/reload` | `void` |
| `config.delete(data)` | `DELETE` | `/all/config` | `void` |
| `config.deleteKey(data)` | `DELETE` | `/all/config/key` | `void` |

---

## Dashboard — 控制台（1 端点，管理端）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `dashboard.index()` | `GET` | `/all/dashboard` | `DashboardData` |

---

## System — 系统（1 端点，管理端）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `system.update()` | `GET` | `/all/system/update` | `SystemUpdateInfo` |

---

## Storage — 存储管理（6 端点，管理端）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `storage.types()` | `GET` | `/all/storage/types` | `StorageDriver[]` |
| `storage.meta(type)` | `GET` | `/all/storage/:type/meta` | `Record<string, any>` |
| `storage.install()` | `POST` | `/all/storage/install` | `void` |
| `storage.channels()` | `GET` | `/all/storage/channels` | `StorageChannel[]` |
| `storage.testChannel(data)` | `POST` | `/all/storage/test-channel` | `{ success, message }` |
| `storage.channelStats()` | `GET` | `/all/storage/channel-stats` | `ChannelStats[]` |

---

## Captcha — 验证码（5 端点）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `captcha.types()` | `GET` | `/all/captcha/types` | `CaptchaDriver[]` |
| `captcha.drivers()` | `GET` | `/all/captcha/drivers` | `CaptchaDriver[]` |
| `captcha.meta(slug)` | `GET` | `/all/captcha/:slug/meta` | `CaptchaMeta` |
| `captcha.install()` | `POST` | `/all/captcha/install` | `void` |
| `captcha.config()` | `GET` | `/captcha/config` | `CaptchaConfig`（公开） |

---

## Sender — 消息发送器（6 端点，管理端）

| 方法 | HTTP | 路径 | 返回 |
|------|------|------|------|
| `sender.types()` | `GET` | `/all/sender/types` | `string[]` |
| `sender.meta(type)` | `GET` | `/all/sender/:type/meta` | `SenderMeta` |
| `sender.install()` | `POST` | `/all/sender/install` | `void` |
| `sender.channels()` | `GET` | `/all/sender/channels` | `SenderChannel[]` |
| `sender.templates()` | `GET` | `/all/sender/templates` | `SenderTemplate[]` |
| `sender.testChannel(data)` | `POST` | `/all/sender/test-channel` | `{ success, message }` |

---

## 错误码参考

### HTTP 状态码 → 错误类

| 状态码 | 错误类 | 说明 |
|--------|--------|------|
| 400/422 | `ValidationError` | 参数错误 |
| 401 | `AuthenticationError` | token 无效/过期 |
| 403 | `PermissionError` | 权限不足 |
| 404 | `NotFoundError` | 资源不存在 |
| 429 | `RateLimitError` | 请求过于频繁 |
| 500/502/503/504 | `ServerError` | 服务器错误 |

### 业务码 → 错误消息

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
