# @lovecards/sdk API Endpoint Reference

> 完整 SDK API 端点手册。供 SDK 消费者查阅。
> 覆盖 16 个后端模块，93 条路由，17 个 SDK 资源类。

---

## 目录

- [快速开始](#快速开始)
- [认证模块 Session](#1-认证模块-session)
- [卡片模块 Cards](#2-卡片模块-cards)
- [用户模块 Users](#3-用户模块-users)
- [评论模块 Comments](#4-评论模块-comments)
- [标签模块 Tags](#5-标签模块-tags)
- [点赞模块 Likes](#6-点赞模块-likes)
- [文件模块 Files](#7-文件模块-files)
- [主题模块 Theme](#8-主题模块-theme)
- [角色模块 Roles](#9-角色模块-roles)
- [权限模块 Permissions](#10-权限模块-permissions)
- [配置模块 Config](#11-配置模块-config)
- [控制台模块 Dashboard](#12-控制台模块-dashboard)
- [存储模块 Storage](#13-存储模块-storage)
- [消息模块 Sender](#14-消息模块-sender)
- [验证码模块 Captcha](#15-验证码模块-captcha)
- [系统模块 System](#16-系统模块-system)

---

## 快速开始

```typescript
import { createClient } from '@lovecards/sdk'

const client = createClient({ apiUrl: '/api' })

// 1. 登录获取 token
const { token } = await client.session.login({
  account: 'admin@test.com',
  password: '123456',
})
client.setToken(token)

// 2. 之后所有需要 token 的请求自动携带
const cards = await client.cards.list()
```

### 统一响应格式

```typescript
// 列表（分页）→ { data: T[], pagination?: PaginationInfo }
const { data, pagination } = await client.cards.list()
// data: Card[]
// pagination: { currentPage, totalPages, totalItems, itemsPerPage }

// 单条 → T
const card = await client.cards.get(1)
// card: Card

// 创建 → CreateResult { id: string | null }
const { id } = await client.cards.create({ content: '...' })
// id: string（成功）/ null（审核模式）

// 更新/删除 → void
await client.cards.update(1, { content: '...' })
await client.cards.delete(1)
```

### 权限速查

| 修饰 | 含义 |
|------|------|
| **公开** | 无需 token，任何人都可以访问 |
| **Token** | 仅需有效 token（JwtAuthCheck） |
| **能力** | 需要 token + 特定能力（JwtAuthCheck + PermissionCheck） |
| **Batch** | 仅需 token，能力在 Service 层按 method 检查 |

---

## 1. 认证模块 Session

后端控制器：`User.Session`

### session.login()

```typescript
client.session.login(data: LoginParams): Promise<LoginResult>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /session/login` |
| 权限 | 公开（`public: true`）|
| 用途 | 账号密码登录，获取 token |

**参数：**

```typescript
interface LoginParams {
  account: string   // 邮箱/手机号/用户名
  password: string
  captcha?: string  // 验证码（如系统启用验证码）
}
```

**返回：**

```typescript
interface LoginResult {
  token: string
}
```

**示例：**

```typescript
const { token } = await client.session.login({
  account: 'admin@lovecards.cn',
  password: '123456',
})
client.setToken(token)
```

---

### session.register()

```typescript
client.session.register(data: RegisterParams): Promise<LoginResult>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /session/register` |
| 权限 | 公开（`public: true`）+ SessionDebounce（6s 冷却）+ CaptchaCheck |
| 用途 | 注册新用户 |

**参数：**

```typescript
interface RegisterParams {
  account: string          // 邮箱/手机号
  password: string
  password_confirm: string
  code?: string            // 验证码（后端读取 code 字段）
}
```

**注意：** 有 6 秒防抖冷却；后端验证码字段名为 `code`（非 `captcha`）。

---

### session.guest()

```typescript
client.session.guest(): Promise<LoginResult>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /session/guest` |
| 权限 | 公开（`public: true`）|
| 用途 | 获取访客 token（需开启访客模式）|

---

### session.logout()

```typescript
client.session.logout(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /session/logout` |
| 权限 | Token |
| 用途 | 登出（当前 token 失效）|

---

### session.captcha()

```typescript
client.session.captcha(params: CaptchaSendParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /session/captcha` |
| 权限 | 公开 + SessionDebounce（6s 冷却）|
| 用途 | 发送验证码到账号（邮箱/手机）|

**参数：**

```typescript
interface CaptchaSendParams {
  account: string    // 邮箱/手机号
  scene?: string     // 场景（如 'login', 'register'）
}
```

---

### session.check()

```typescript
client.session.check(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /session/check` |
| 权限 | Token |
| 用途 | 校验 token 是否有效 |

---

## 2. 卡片模块 Cards

后端控制器：`Content.Cards`

### cards.list()

```typescript
client.cards.list(params?: CardsListParams): Promise<ListResult<Card>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /cards` |
| 权限 | 公开（`public: true`）|
| 用途 | 获取卡片列表（分页）|

**参数：**

```typescript
interface CardsListParams {
  page?: number               // 页码，默认 1
  list_rows?: number          // 每页条数，默认 15
  tag?: string                // 按标签筛选
  status?: number             // 按状态筛选（需 cards.read.all 能力）
  search_value?: string       // 搜索关键词
  search_keys?: string[]      // 搜索字段（如 ['content']）
  order_key?: string          // 排序字段
  order_desc?: 'true' | 'false'  // 是否降序
}
```

**返回：**

```typescript
interface Card {
  id: number
  user_id: number
  status: number
  is_top: number
  content: string
  data: Record<string, any>    // 自定义数据（如 title 等）
  cover: string | null
  pictures: string[] | null
  tags: string[] | null
  goods: number
  views: number
  comments: number
  post_ip: string
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null
}
```

---

### cards.get()

```typescript
client.cards.get(id: number): Promise<Card>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /cards/:id` |
| 权限 | 公开（`public: true`）|
| 用途 | 获取单张卡片详情 |

---

### cards.hot()

```typescript
client.cards.hot(): Promise<Card[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /cards/hot` |
| 权限 | 公开（`public: true`）|
| 用途 | 获取热门卡片列表（不包含分页元数据）|

**返回：** `Card[]`（**不是** `ListResult`，直接是数组）

---

### cards.search()

```typescript
client.cards.search(params?: CardsListParams): Promise<ListResult<Card>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /cards/search` |
| 权限 | 公开（`public: true`）|
| 用途 | 搜索卡片。与 `list()` 调用同一后端方法，search_value 参数生效 |

---

### cards.create()

```typescript
client.cards.create(data: CreateCardParams): Promise<CreateResult>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /cards` |
| 权限 | `cards.create` + CaptchaCheck |
| 用途 | 创建卡片 |

**参数：**

```typescript
interface CreateCardParams {
  content: string               // 卡片内容
  data?: Record<string, any>    // 自定义数据（如 { title: 'xxx' }）
  tags?: string                 // 标签 JSON 字符串（如 '["tag1","tag2"]'）
  cover?: string                // 封面图 URL
  pictures?: string[]           // 图集 URL 数组
}
```

**返回：**

```typescript
interface CreateResult {
  id: string | null  // null = 审核模式，卡片已提交等待审核
}
```

---

### cards.update()

```typescript
client.cards.update(id: number, data: UpdateCardParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `PATCH /cards/:id` |
| 权限 | `cards.update`（只能更新自己的卡片）/ `cards.update.all`（可以更新任何卡片）|
| 用途 | 编辑卡片 |

**参数：**

```typescript
interface UpdateCardParams {
  content?: string
  data?: Record<string, any>
  tags?: string
  cover?: string
}
```

---

### cards.delete()

```typescript
client.cards.delete(id: number): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /cards/:id` |
| 权限 | `cards.delete`（只能删除自己的）/ `cards.delete.all`（可以删除任何）|
| 用途 | 删除卡片（软删除）|

---

### cards.like()

```typescript
client.cards.like(id: number): Promise<{ likes: number }>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /cards/:id/like` |
| 权限 | `likes.create` |
| 用途 | 点赞卡片 |

**返回：** `{ likes: number }`（最新的点赞数）

---

### cards.listOwn()

```typescript
client.cards.listOwn(params?: { page?: number; list_rows?: number }): Promise<ListResult<Card>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /users/me/cards` |
| 权限 | Token |
| 用途 | 获取当前用户的卡片列表 |

---

### cards.batch()

```typescript
client.cards.batch(data: BatchOperateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /cards/batch` |
| 权限 | Token（能力在 Service 层按 method 检查）|
| 用途 | 卡片批量操作 |

**支持的 method：**

| method | 对应能力 | 说明 |
|--------|---------|------|
| `top` | `cards.pin` | 置顶 |
| `unset_top` | `cards.pin` | 取消置顶 |
| `approve` | `cards.approve` | 审核通过 |
| `ban` | `cards.approve` | 封禁 |
| `hide` | `cards.update` | 隐藏 |
| `unhide` | `cards.update` | 取消隐藏 |
| `delete` | `cards.delete` | 删除 |

---

## 3. 用户模块 Users

后端控制器：`User.Profile`（`/users/me/*`），`User.Users`（`/users/*`）

### users.me()

```typescript
client.users.me(): Promise<User>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /users/me` |
| 权限 | Token |
| 用途 | 获取当前登录用户信息 |

**返回：**

```typescript
interface User {
  id: number
  number: string
  username: string
  email: string
  phone: string
  avatar: string
  roles_id: number[]
  roles?: RoleInfo[]           // 角色信息（仅在 me() 时返回）
  capabilities?: string[]      // 能力字符串列表（仅在 me() 时返回）
  status: number
  // 注意：不包含 password、deleted_at
}
```

---

### users.updateMe()

```typescript
client.users.updateMe(data: ProfileUpdateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `PATCH /users/me` |
| 权限 | Token |
| 用途 | 编辑自己的资料 |

**参数：**

```typescript
interface ProfileUpdateParams {
  username?: string
  avatar?: string
  password?: string
}
```

---

### users.updatePassword()

```typescript
client.users.updatePassword(data: PasswordParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /users/me/password` |
| 权限 | Token |
| 用途 | 修改密码 |

**参数：** `{ password: string }`

---

### users.updateEmail()

```typescript
client.users.updateEmail(data: EmailParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /users/me/email` |
| 权限 | Token |
| 用途 | 绑定邮箱 |

**参数：** `{ email: string, captcha: string }`

---

### users.emailCaptcha()

```typescript
client.users.emailCaptcha(data: EmailCaptchaParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /users/me/email-captcha` |
| 权限 | Token |
| 用途 | 发送邮箱验证码 |

**参数：** `{ email: string }`

---

### users.list()

```typescript
client.users.list(params?: {
  page?: number
  list_rows?: number
  search_value?: string
}): Promise<ListResult<User>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /users` |
| 权限 | `users.read` / `users.read.all` |
| 用途 | 用户管理列表 |

---

### users.get()

```typescript
client.users.get(id: number): Promise<User>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /users/:id` |
| 权限 | `users.read` / `users.read.all` |
| 用途 | 获取用户详情 |

---

### users.update()

```typescript
client.users.update(id: number, data: AdminUserUpdateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `PATCH /users/:id` |
| 权限 | `users.update`（只能编辑自己的）/ `users.update.all`（可以编辑任何用户）|
| 用途 | 管理员编辑用户 |

**参数：**

```typescript
interface AdminUserUpdateParams extends ProfileUpdateParams {
  username?: string
  avatar?: string
  password?: string
  roles_id?: number[]
  status?: number
  email?: string
  phone?: string
}
```

---

### users.delete()

```typescript
client.users.delete(id: number): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /users/:id` |
| 权限 | `users.delete` / `users.delete.all` |
| 用途 | 删除用户 |

---

### users.batch()

```typescript
client.users.batch(data: BatchOperateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /users/batch` |
| 权限 | Token（能力在 Service 层检查）|
| 用途 | 用户批量操作 |

**支持的 method：**

| method | 说明 |
|--------|------|
| `approve` | 审核通过 |
| `ban` | 封禁 |
| `hide` | 隐藏 |
| `delete` | 删除 |

---

## 4. 评论模块 Comments

后端控制器：`Content.Comments`

### comments.list()

```typescript
client.comments.list(params?: {
  page?: number
  list_rows?: number
  search_value?: string
}): Promise<ListResult<Comment>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /comments` |
| 权限 | `comments.read` / `comments.read.all` |
| 用途 | 全量评论列表（Admin 用）|

---

### comments.cardList()

```typescript
client.comments.cardList(cardId: number, params?: PaginationParams): Promise<ListResult<Comment>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /cards/:cardId/comments` |
| 权限 | 公开（`public: true`）|
| 用途 | 获取卡片的评论列表（扁平列表，树形由前端构建）|

**注意：** 此方法在 `cards.php` 路由中定义，但 SDK 归在 `comments` 资源下。

---

### comments.create()

```typescript
client.comments.create(cardId: number, data: CreateCommentParams): Promise<CreateResult>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /cards/:cardId/comments` |
| 权限 | `comments.create` + CaptchaCheck |
| 用途 | 创建评论 |

**参数：** `{ content: string, parent_id?: number }`

**返回：** `CreateResult { id: string | null }`（null = 审核模式）

---

### comments.get()

```typescript
client.comments.get(id: number): Promise<Comment>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /comments/:id` |
| 权限 | 公开（`public: true`）|
| 用途 | 获取评论详情 |

**返回：**

```typescript
interface Comment {
  id: number
  aid: number
  pid: number
  user_id: number
  parent_id: number | null
  content: string
  data: Record<string, any>
  is_top: number
  goods: number
  post_ip: string
  status: number
  created_at: string | null
  updated_at: string | null
  // 注意：children 不在 API 返回中，需前端 buildTree 构建
}
```

---

### comments.update()

```typescript
client.comments.update(id: number, data: { content: string }): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `PATCH /comments/:id` |
| 权限 | `comments.update`（只能编辑自己的）/ `comments.update.all` |
| 用途 | 编辑评论 |

---

### comments.delete()

```typescript
client.comments.delete(id: number): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /comments/:id` |
| 权限 | `comments.delete` / `comments.delete.all` |
| 用途 | 删除评论 |

---

### comments.listOwn()

```typescript
client.comments.listOwn(params?: PaginationParams): Promise<ListResult<Comment>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /users/me/comments` |
| 权限 | Token |
| 用途 | 获取当前用户的评论列表 |

---

### comments.batch()

```typescript
client.comments.batch(data: BatchOperateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /comments/batch` |
| 权限 | Token（能力在 Service 层检查）|
| 用途 | 评论批量操作 |

**支持的 method：** `approve`、`ban`、`hide`、`delete`

---

## 5. 标签模块 Tags

后端控制器：`Content.Tags`

### tags.list()

```typescript
client.tags.list(params?: PaginationParams): Promise<Tag[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /tags` |
| 权限 | 公开（`public: true`）|
| 用途 | 标签列表 |

**返回：** `Tag[]`（**不是** `ListResult`，直接是数组，无分页元数据）

```typescript
interface Tag {
  id: number
  aid: number
  user_id: number
  name: string
  status: number
  created_at: string | null
  updated_at: string | null
}
```

---

### tags.get()

```typescript
client.tags.get(id: number): Promise<Tag>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /tags/:id` |
| 权限 | 公开（`public: true`）|
| 用途 | 标签详情 |

---

### tags.listAll()

```typescript
client.tags.listAll(params?: ListParams): Promise<ListResult<Tag>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /tags/all` |
| 权限 | `tags.read` / `tags.read.all` |
| 用途 | 全量标签列表（Admin，含所有状态标签，分页）|

**返回：** `ListResult<Tag>`（分页数据，包含封禁/隐藏状态的标签）

```typescript
interface ListResult<T> {
  data: T[]
  pagination?: PaginationInfo
}
```

---

### tags.create()

```typescript
client.tags.create(data: { name: string }): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /tags` |
| 权限 | `tags.create` |
| 用途 | 创建标签 |

---

### tags.update()

```typescript
client.tags.update(id: number, data: { name: string }): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `PATCH /tags/:id` |
| 权限 | `tags.update` / `tags.update.all` |
| 用途 | 编辑标签 |

---

### tags.delete()

```typescript
client.tags.delete(id: number): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /tags/:id` |
| 权限 | `tags.delete` / `tags.delete.all` |
| 用途 | 删除标签 |

---

### tags.batch()

```typescript
client.tags.batch(data: BatchOperateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /tags/batch` |
| 权限 | Token（能力在 Service 层检查）|
| 用途 | 标签批量操作 |

**支持的 method：** `approve`、`ban`、`hide`、`delete`

---

## 6. 点赞模块 Likes

后端控制器：`Content.Likes`

### likes.list()

```typescript
client.likes.list(params?: PaginationParams): Promise<Like[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /likes` |
| 权限 | `likes.read` |
| 用途 | 获取当前用户的点赞列表 |

**返回：** `Like[]`（**不是** `ListResult`，直接是数组）

```typescript
interface Like {
  id: number
  aid: number
  pid: number
  ref_type: string | null
  ref_id: number | null
  uid: number
  ip: string
  created_at: string | null
}
```

---

### likes.unlike()

```typescript
client.likes.unlike(id: number): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /likes/:id` |
| 权限 | `likes.delete` |
| 用途 | 取消点赞 |

---

## 7. 文件模块 Files

后端控制器：`Storage.Upload`

### files.upload()

```typescript
client.files.upload(formData: FormData): Promise<UploadResult>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /files` |
| 权限 | `files.upload` |
| 用途 | 上传文件（multipart/form-data）|

**参数：** `FormData`，文件字段名 `file`

```typescript
// 示例
const formData = new FormData()
formData.append('file', fileInput.files[0])
const result = await client.files.upload(formData)
```

**返回：**

```typescript
interface UploadResult {
  id: number
  url: string
  path: string
  size: number
  mime_type: string
  original_name: string
  channel_slug: string
}
```

---

### files.list()

```typescript
client.files.list(params?: { page?: number; list_rows?: number }): Promise<ListResult<LCFile>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /files` |
| 权限 | `files.read` / `files.read.all` |
| 用途 | 文件列表（分页）|

**返回：**

```typescript
interface LCFile {
  id: number
  hash: string
  channel_slug: string
  user_id: number | null
  is_public: number
  scene: string | null
  ref_type: string | null
  ref_id: number | null
  original_name: string | null
  file_path: string
  file_url: string
  file_size: number
  file_ext: string
  mime_type: string | null
  metadata: Record<string, any> | null
  status: number
  upload_status: number
  expire_at: string | null
  created_at: string
  updated_at: string
}
```

---

### files.get()

```typescript
client.files.get(id: number): Promise<LCFile>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /files/:id` |
| 权限 | `files.read` / `files.read.all` |
| 用途 | 文件详情 |

---

### files.direct()

```typescript
client.files.direct(data?: {
  filename?: string
  size?: number
  mime?: string
}): Promise<DirectUploadResult>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /files/direct` |
| 权限 | `files.upload` |
| 用途 | 获取直传凭证（客户端直接上传到存储渠道）|

**返回：**

```typescript
interface DirectUploadResult {
  record_id: number
  upload_url: string
  method: string
  headers: Record<string, string>
  form_data: Record<string, string>
  expire: number
}
```

---

### files.confirm()

```typescript
client.files.confirm(id: number): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `PATCH /files/:id/confirm` |
| 权限 | `files.upload` |
| 用途 | 确认直传完成 |

---

### files.batch()

```typescript
client.files.batch(data: BatchOperateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /files/batch` |
| 权限 | Token（能力在 Service 层检查）|
| 用途 | 文件批量操作 |

**支持的 method：** `approve`、`ban`、`toggle_public`、`trash`、`restore`、`hard_delete`

---

### files.cleanup()

```typescript
client.files.cleanup(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /files/expired` |
| 权限 | `files.delete.all` |
| 用途 | 清理过期文件记录 |

---

### files.delete()

```typescript
client.files.delete(id: number): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /files/:id` |
| 权限 | `files.delete` / `files.delete.all` |
| 用途 | 删除文件 |

---

## 8. 主题模块 Theme

后端控制器：`Theme.ThemeManager`

### theme.list()

```typescript
client.theme.list(): Promise<ThemeItem[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /theme/list` |
| 权限 | `theme.read` |
| 用途 | 已安装主题列表 |

**返回：**

```typescript
interface ThemeItem {
  name: string
  title: string
  description: string
  version: string
  author: string
  active: boolean
}
```

---

### theme.upload()

```typescript
client.theme.upload(formData: FormData): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /theme/upload` |
| 权限 | `theme.upload` |
| 用途 | 上传安装主题（ZIP 文件，字段名 `file`）|

---

### theme.activate()

```typescript
client.theme.activate(data: ThemeActivateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /theme/activate` |
| 权限 | `theme.activate` |
| 用途 | 切换活跃主题 |

**参数：** `{ name: string }`（**不是** `theme`）

---

### theme.config()

```typescript
client.theme.config(): Promise<ThemeConfigData>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /theme/config` |
| 权限 | `theme.read`（在 auth group 内）|
| 用途 | 获取当前主题配置（含 schema + values）|

**返回：**

```typescript
interface ThemeConfigData {
  name: string
  mode: string          // 'spa' | 'ssr'
  config_schema: Record<string, any>
  config_values: Record<string, any>
}
```

---

### theme.publicConfig()

```typescript
client.theme.publicConfig(): Promise<ThemeConfigData>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /theme/config` |
| 权限 | **公开**（`public: true`，在 auth group 之外）|
| 用途 | 获取当前主题配置（公开版，无需 token）|

**注意：** 与 `theme.config()` 调用同一后端端点，但**不需要 token**。

---

### theme.updateConfig()

```typescript
client.theme.updateConfig(data: Record<string, any>): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `PUT /theme/config` |
| 权限 | `theme.update` |
| 用途 | 更新主题配置 |

**参数：** `{ key: value, ... }`（`config_values` 中的键值对）

---

### theme.freeze()

```typescript
client.theme.freeze(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /theme/freeze` |
| 权限 | `theme.freeze` |
| 用途 | 固化配置到 theme.json |

---

### theme.delete()

```typescript
client.theme.delete(data: ThemeDeleteParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /theme/delete` |
| 权限 | `theme.delete` |
| 用途 | 删除主题 |

**参数：** `{ name: string }`（**不是** `theme`）

---

## 9. 角色模块 Roles

后端控制器：`Rbac.Roles`

### roles.list()

```typescript
client.roles.list(params?: { page?: number; list_rows?: number }): Promise<ListResult<Role>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /roles` |
| 权限 | `roles.read` |
| 用途 | 角色列表 |

**返回：**

```typescript
interface Role {
  id: number
  name: string
  slug: string
  description: string | null
  is_system: number      // 0/1
  created_at: string | null
  updated_at: string | null
}
```

---

### roles.get()

```typescript
client.roles.get(id: number): Promise<Role>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /roles/:id` |
| 权限 | `roles.read` |
| 用途 | 角色详情 |

---

### roles.create()

```typescript
client.roles.create(data: CreateRoleParams): Promise<{ id: string }>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /roles` |
| 权限 | `roles.create` |
| 用途 | 创建角色 |

**参数：** `{ name: string, slug: string, description?: string }`

**注意：** `name` 验证规则 `chsDash`（汉字/字母/数字/下划线/破折号，**不含空格**）

---

### roles.update()

```typescript
client.roles.update(id: number, data: UpdateRoleParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `PATCH /roles/:id` |
| 权限 | `roles.update` |
| 用途 | 编辑角色 |

**参数：** `{ name?: string, slug?: string, description?: string }`

---

### roles.delete()

```typescript
client.roles.delete(id: number): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /roles/:id` |
| 权限 | `roles.delete` |
| 用途 | 删除角色 |

**注意：** 系统角色（`is_system = 1`）不可删除

---

### roles.getCapabilities()

```typescript
client.roles.getCapabilities(id: number): Promise<string[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /roles/:id/capabilities` |
| 权限 | `roles.read` |
| 用途 | 获取角色拥有的能力列表 |

**返回：** `string[]`（能力字符串数组，如 `['cards.read', 'cards.create']`）

---

### roles.assignCapabilities()

```typescript
client.roles.assignCapabilities(id: number, data: AssignCapabilitiesParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /roles/:id/capabilities` |
| 权限 | `roles.assign` |
| 用途 | 分配能力给角色 |

**参数：**

```typescript
interface AssignCapabilitiesParams {
  capabilities: string[]   // 能力字符串数组
}
```

**注意：** 后端会校验能力列表中的每一项是否存在于 `getAllCapabilities()` 中，不存在的能力会被拒绝。

---

### roles.reseed()

```typescript
client.roles.reseed(): Promise<ReseedResult>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /roles/reseed` |
| 权限 | `roles.assign` |
| 用途 | 重新 seed 所有角色的能力数据（清空 `role_capabilities` 表后重建）|

**返回：**

```typescript
interface ReseedResult {
  total: number    // 总分配记录数
  guest: number    // 访客角色分配数
  user: number     // 用户角色分配数
  admin: number    // 管理员角色分配数
  root: number     // 超级管理员角色分配数
}
```

---

## 10. 权限模块 Permissions

后端控制器：`Rbac.Permissions`

### permissions.list()

```typescript
client.permissions.list(params?: {
  page?: number
  list_rows?: number
  search_value?: string
}): Promise<ListResult<CapabilityItem>>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /permissions` |
| 权限 | `permissions.read` |
| 用途 | 搜索能力列表（分页）|

**返回：**

```typescript
interface CapabilityItem {
  capability: string     // 能力字符串（如 'cards.create'）
  description: string    // 能力描述
}
```

---

### permissions.all()

```typescript
client.permissions.all(): Promise<CapabilityItem[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /permissions/all` |
| 权限 | `permissions.read` |
| 用途 | 获取全部能力列表（不分页）|

**返回：** `CapabilityItem[]`

---

## 11. 配置模块 Config

后端控制器：`System.Config`

### config.list()

```typescript
client.config.list(): Promise<ConfigData>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /config` |
| 权限 | `config.read` |
| 用途 | 获取全部系统配置 |

**返回：**

```typescript
interface ConfigData {
  [group: string]: {
    [key: string]: any
  }
}
// 示例：{ core: { site_name: 'LoveCards' }, upload: { max_size: 10 } }
```

---

### config.update()

```typescript
client.config.update(data: ConfigUpdateParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /config` |
| 权限 | `config.update` |
| 用途 | 保存配置 |

**参数：**

```typescript
interface ConfigUpdateParams {
  [group: string]: {
    [key: string]: any
  }
}
// 示例：{ core: { site_name: 'MySite' } }
```

---

### config.groups()

```typescript
client.config.groups(): Promise<string[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /config/groups` |
| 权限 | `config.read` |
| 用途 | 获取配置组列表 |

**返回：** `string[]`（如 `['core', 'upload', 'cards']`）

---

### config.init()

```typescript
client.config.init(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /config/init` |
| 权限 | `config.init` |
| 用途 | 初始化系统配置 |

---

### config.register()

```typescript
client.config.register(data: ConfigRegisterParams): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /config/register` |
| 权限 | `config.register` |
| 用途 | 注册配置组 |

**参数：** `{ group: string, schema: Record<string, any> }`

---

### config.reload()

```typescript
client.config.reload(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /config/reload` |
| 权限 | `config.reload` |
| 用途 | 重载配置（从缓存/文件刷新）|

---

### config.deleteGroup()

```typescript
client.config.deleteGroup(group: string): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /config`（query params: `?group=xxx`）|
| 权限 | `config.deleteKey` |
| 用途 | 删除配置组 |

**参数：** `group: string`

---

### config.deleteKey()

```typescript
client.config.deleteKey(group: string, key: string): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `DELETE /config/key`（query params: `?group=xxx&key=yyy`）|
| 权限 | `config.deleteKey` |
| 用途 | 删除配置键 |

**参数：** `group: string`, `key: string`

---

## 12. 控制台模块 Dashboard

后端控制器：`System.Dashboard`

### dashboard.index()

```typescript
client.dashboard.index(): Promise<DashboardData>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /dashboard` |
| 权限 | `dashboard.read` |
| 用途 | 控制台首页数据 |

**返回：**

```typescript
interface DashboardData {
  count: {
    cards: number
    comments: number
    good: number
  }
  chart: ChartDataset[]
  ver: VersionInfo
  notice: any[]
}

interface ChartDataset {
  label: string     // '卡片' | '评论' | '点赞'
  data: {
    x: string[]     // 日期标签（如 ['昨日', '2天前', ...]）
    y: number[]     // 对应数量
  }
}

interface VersionInfo {
  app_name: string
  homepage: string
  version: string
  build: number
  github: string
  qgroup: string
}
```

---

## 13. 存储模块 Storage

后端控制器：`Storage.Storage`

### storage.types()

```typescript
client.storage.types(): Promise<StorageDriver[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /storage/types` |
| 权限 | `storage.read` |
| 用途 | 存储驱动类型列表 |

**返回：**

```typescript
interface StorageDriver {
  type: string
  name: string
  icon: string
}
```

---

### storage.meta()

```typescript
client.storage.meta(type: string): Promise<StorageMeta>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /storage/:type/meta` |
| 权限 | `storage.read` |
| 用途 | 获取驱动配置信息 |

**返回：**

```typescript
interface StorageMeta {
  type: string
  name: string
  icon: string
  schema: Record<string, any>
  group: string
}
```

---

### storage.install()

```typescript
client.storage.install(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /storage/install` |
| 权限 | `storage.install` |
| 用途 | 安装存储驱动（注册配置 schema）|

---

### storage.channels()

```typescript
client.storage.channels(): Promise<StorageChannel[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /storage/channels` |
| 权限 | `storage.read` |
| 用途 | 存储渠道列表 |

**返回：**

```typescript
interface StorageChannel {
  slug: string
  name: string
  icon: string
  fields: any[]
}
```

---

### storage.testChannel()

```typescript
client.storage.testChannel(data: { channel: string }): Promise<{ success: boolean; message?: string }>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /storage/test-channel` |
| 权限 | `storage.test` |
| 用途 | 测试存储渠道连通性 |

---

### storage.channelStats()

```typescript
client.storage.channelStats(): Promise<ChannelStats>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /storage/channel-stats` |
| 权限 | `storage.read` |
| 用途 | 存储渠道文件统计 |

---

## 14. 消息模块 Sender

后端控制器：`Sender.Sender`

### sender.types()

```typescript
client.sender.types(): Promise<SenderType[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /sender/types` |
| 权限 | `sender.read` |
| 用途 | 消息驱动类型列表 |

**返回：**

```typescript
interface SenderType {
  type: string
  channelType: string
  name: string
  icon: string
  supportedTypes: string[]
}
```

---

### sender.meta()

```typescript
client.sender.meta(type: string): Promise<SenderMeta>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /sender/:type/meta` |
| 权限 | `sender.read` |
| 用途 | 获取驱动配置信息 |

**返回：**

```typescript
interface SenderMeta {
  type: string
  name: string
  icon: string
  schema: Record<string, any>
  group: string
}
```

---

### sender.install()

```typescript
client.sender.install(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /sender/install` |
| 权限 | `sender.install` |
| 用途 | 安装消息驱动 |

---

### sender.channels()

```typescript
client.sender.channels(): Promise<SenderChannel[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /sender/channels` |
| 权限 | `sender.read` |
| 用途 | 消息渠道列表 |

---

### sender.templates()

```typescript
client.sender.templates(): Promise<SenderTemplate[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /sender/templates` |
| 权限 | `sender.read` |
| 用途 | 消息模板列表 |

---

### sender.testChannel()

```typescript
client.sender.testChannel(data: { channel: string; to?: string }): Promise<{ success: boolean; message?: string }>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /sender/test-channel` |
| 权限 | `sender.test` |
| 用途 | 测试发送渠道 |

---

## 15. 验证码模块 Captcha

后端控制器：`Captcha.Captcha`

### captcha.types()

```typescript
client.captcha.types(): Promise<CaptchaDriver[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /captcha/types` |
| 权限 | `captcha.read` |
| 用途 | 验证驱动类型列表 |

**返回：**

```typescript
interface CaptchaDriver {
  slug: string
  type: string
  name: string
  icon: string
}
```

---

### captcha.drivers()

```typescript
client.captcha.drivers(): Promise<CaptchaDriver[]>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /captcha/drivers` |
| 权限 | `captcha.read` |
| 用途 | 验证驱动详情列表（比 types 更详细）|

---

### captcha.meta()

```typescript
client.captcha.meta(slug: string): Promise<CaptchaMeta>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /captcha/:slug/meta` |
| 权限 | `captcha.read` |
| 用途 | 获取驱动配置信息 |

---

### captcha.install()

```typescript
client.captcha.install(): Promise<void>
```

| 属性 | 值 |
|------|------|
| HTTP | `POST /captcha/install` |
| 权限 | `captcha.install` |
| 用途 | 安装验证驱动 |

---

### captcha.config()

```typescript
client.captcha.config(): Promise<CaptchaConfig>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /captcha/config` |
| 权限 | **公开**（`public: true`）|
| 用途 | 获取当前验证配置（公开）|

---

## 16. 系统模块 System

后端控制器：`System.System`

### system.update()

```typescript
client.system.update(): Promise<SystemUpdateInfo>
```

| 属性 | 值 |
|------|------|
| HTTP | `GET /system/update` |
| 权限 | `system.update` |
| 用途 | 检查系统更新 |

**返回：**

```typescript
interface SystemUpdateInfo {
  ver: string       // 当前版本号（JSON 字符串）
  latest: any       // GitHub API 最新 release 数据
  verlog: string    // 更新日志 Markdown
}
```

---

## 附录：错误码

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

### ApiError 结构

```typescript
class ApiError extends Error {
  code: number      // 业务码（如 9002）
  message: string   // 可读错误信息
  status: number    // HTTP 状态码（如 404）
  details?: any     // 验证错误详情（字段级错误）
}
```

---

## 附录：模块能力矩阵

| 能力 | 所属模块 | 对应端点 |
|------|---------|---------|
| `cards.create` | 卡片 | `POST /cards` |
| `cards.read` | 卡片 | `GET /cards`（获取已发布卡片）|
| `cards.read.all` | 卡片 | `GET /cards`（获取全部卡片）|
| `cards.update` | 卡片 | `PATCH /cards/:id`（编辑自己的）|
| `cards.update.all` | 卡片 | `PATCH /cards/:id`（编辑任何）|
| `cards.delete` | 卡片 | `DELETE /cards/:id` |
| `cards.delete.all` | 卡片 | `DELETE /cards/:id` |
| `cards.pin` | 卡片 | `POST /cards/batch`（置顶/取消置顶）|
| `cards.approve` | 卡片 | `POST /cards/batch`（审核/封禁）|
| `comments.create` | 评论 | `POST /cards/:id/comments` |
| `comments.read` | 评论 | `GET /comments/:id` |
| `comments.update` | 评论 | `PATCH /comments/:id` |
| `comments.update.all` | 评论 | `PATCH /comments/:id` |
| `comments.delete` | 评论 | `DELETE /comments/:id` |
| `comments.delete.all` | 评论 | `DELETE /comments/:id` |
| `tags.create` | 标签 | `POST /tags` |
| `tags.read` | 标签 | `GET /tags` |
| `tags.update` | 标签 | `PATCH /tags/:id` |
| `tags.update.all` | 标签 | `PATCH /tags/:id` |
| `tags.delete` | 标签 | `DELETE /tags/:id` |
| `tags.delete.all` | 标签 | `DELETE /tags/:id` |
| `users.create` | 用户 | — |
| `users.read` | 用户 | `GET /users`（获取可查看用户）|
| `users.read.all` | 用户 | `GET /users`（获取全部用户）|
| `users.update` | 用户 | `PATCH /users/:id` |
| `users.update.all` | 用户 | `PATCH /users/:id` |
| `users.delete` | 用户 | `DELETE /users/:id` |
| `users.delete.all` | 用户 | `DELETE /users/:id` |
| `likes.create` | 点赞 | `POST /cards/:id/like` |
| `likes.read` | 点赞 | `GET /likes` |
| `likes.delete` | 点赞 | `DELETE /likes/:id` |
| `files.upload` | 文件 | `POST /files`、`POST /files/direct`、`PATCH /files/:id/confirm` |
| `files.read` | 文件 | `GET /files`、`GET /files/:id` |
| `files.read.all` | 文件 | `GET /files`、`GET /files/:id` |
| `files.delete` | 文件 | `DELETE /files/:id` |
| `files.delete.all` | 文件 | `DELETE /files/:id`、`DELETE /files/expired` |
| `roles.read` | 角色 | `GET /roles`、`GET /roles/:id`、`GET /roles/:id/capabilities` |
| `roles.create` | 角色 | `POST /roles` |
| `roles.update` | 角色 | `PATCH /roles/:id` |
| `roles.delete` | 角色 | `DELETE /roles/:id` |
| `roles.assign` | 角色 | `POST /roles/:id/capabilities`、`POST /roles/reseed` |
| `permissions.read` | 权限 | `GET /permissions`、`GET /permissions/all` |
| `config.read` | 配置 | `GET /config`、`GET /config/groups` |
| `config.update` | 配置 | `POST /config` |
| `config.init` | 配置 | `POST /config/init` |
| `config.register` | 配置 | `POST /config/register` |
| `config.reload` | 配置 | `POST /config/reload` |
| `config.deleteKey` | 配置 | `DELETE /config`、`DELETE /config/key` |
| `dashboard.read` | 控制台 | `GET /dashboard` |
| `theme.read` | 主题 | `GET /theme/list`、`GET /theme/config` |
| `theme.update` | 主题 | `PUT /theme/config` |
| `theme.upload` | 主题 | `POST /theme/upload` |
| `theme.activate` | 主题 | `POST /theme/activate` |
| `theme.freeze` | 主题 | `POST /theme/freeze` |
| `theme.delete` | 主题 | `DELETE /theme/delete` |
| `storage.read` | 存储 | `GET /storage/types`、`GET /storage/:type/meta`、`GET /storage/channels`、`GET /storage/channel-stats` |
| `storage.install` | 存储 | `POST /storage/install` |
| `storage.test` | 存储 | `POST /storage/test-channel` |
| `sender.read` | 消息 | `GET /sender/types`、`GET /sender/:type/meta`、`GET /sender/channels`、`GET /sender/templates` |
| `sender.install` | 消息 | `POST /sender/install` |
| `sender.test` | 消息 | `POST /sender/test-channel` |
| `captcha.read` | 验证码 | `GET /captcha/types`、`GET /captcha/drivers`、`GET /captcha/:slug/meta` |
| `captcha.install` | 验证码 | `POST /captcha/install` |
| `system.update` | 系统 | `GET /system/update` |
