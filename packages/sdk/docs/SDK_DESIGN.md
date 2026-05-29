# LoveCards3 JSSDK 设计文档

> 版本：1.0.0 | 最后更新：2026-05-30

---

## 一、架构概述

`@lovecards/sdk` 是 LoveCards3 的 JavaScript SDK，封装 REST API 调用，提供类型安全的请求/响应处理。

### 设计原则

- **框架无关**：不依赖 Vue、React、jQuery 等任何前端框架
- **纯数据层**：只负责 API 请求和响应处理，不涉及 UI 和路由
- **TypeScript 优先**：完整的类型定义，IDE 智能提示
- **Axios 驱动**：强大的拦截器、错误处理、token 刷新
- **可迭代**：API 端点新增 = minor 版本，签名变更 = major 版本

### 架构图

```
┌─────────────────────────────────────────┐
│          @lovecards/sdk                  │
│                                         │
│  ┌──────────────────────────────────┐   │
│  │  client.ts（Axios 实例工厂）      │   │
│  │  ├─ baseURL: window.__LC__.apiUrl│   │
│  │  ├─ auth 拦截器（自动附加 token） │   │
│  │  ├─ 响应拦截器（统一错误处理）    │   │
│  │  └─ token 刷新逻辑               │   │
│  └──────────┬───────────────────────┘   │
│             │                           │
│  ┌──────────┴───────────────────────┐   │
│  │  api/（API 端点模块）             │   │
│  │  ├─ cards.ts                     │   │
│  │  ├─ users.ts                     │   │
│  │  ├─ comments.ts                  │   │
│  │  ├─ tags.ts                      │   │
│  │  ├─ captcha.ts                   │   │
│  │  └─ system.ts                    │   │
│  └──────────┬───────────────────────┘   │
│             │                           │
│  ┌──────────┴───────────────────────┐   │
│  │  types/（TypeScript 类型）        │   │
│  │  constants.ts（PUBLIC_API 常量表）│   │
│  └──────────────────────────────────┘   │
└─────────────────────────────────────────┘
         │
         │ 使用
         │
┌────────┴────────────────────────────────┐
│  主题层（不在 SDK 内）                    │
│                                         │
│  SPA 主题：React hooks 调 SDK            │
│  SSR 主题：CDN 版 JS 调 SDK              │
│  自定义主题：任意框架调 SDK               │
└─────────────────────────────────────────┘
```

---

## 二、目录结构

```
SDK/
├── src/
│   ├── index.ts               # 统一入口
│   ├── client.ts              # Axios 实例工厂
│   ├── constants.ts           # PUBLIC_API 常量表
│   ├── api/
│   │   ├── index.ts           # API 模块统一导出
│   │   ├── cards.ts           # CardsAPI
│   │   ├── users.ts           # UsersAPI
│   │   ├── comments.ts        # CommentsAPI
│   │   ├── tags.ts            # TagsAPI
│   │   ├── captcha.ts         # CaptchaAPI
│   │   └── system.ts          # SystemAPI
│   └── types/
│       ├── index.ts           # 类型统一导出
│       ├── api.ts             # 通用响应类型
│       ├── cards.ts
│       ├── users.ts
│       ├── comments.ts
│       └── tags.ts
├── docs/
│   └── SDK_DESIGN.md          # 本文件
├── package.json
├── tsconfig.json
├── vite.config.ts             # 库模式构建配置
└── README.md
```

---

## 三、client.ts — Axios 实例工厂

### 3.1 配置接口

```ts
export interface LCClientConfig {
  apiUrl: string              // API 基础路径，如 "/api" 或 "https://api.example.com"
  token?: string              // JWT token（可选，也可通过 setToken() 后续设置）
  timeout?: number            // 请求超时（ms），默认 10000
  onAuthError?: () => void    // 401 回调（token 过期且刷新失败）
}
```

### 3.2 创建客户端

```ts
import { createClient } from '@lovecards/sdk'

const client = createClient({
  apiUrl: window.__LC__?.apiUrl || '/api',
  onAuthError: () => {
    localStorage.removeItem('token')
    location.href = '/login'
  }
})
```

### 3.3 Auth 拦截器

```ts
// 请求拦截器：自动附加 Authorization header
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// 响应拦截器：统一处理 401
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // 尝试刷新 token
      // 刷新失败 → 调用 onAuthError 回调
    }
    return Promise.reject(error)
  }
)
```

### 3.4 Token 管理

```ts
// 设置 token
client.setToken('eyJhbGciOi...')

// 清除 token
client.clearToken()

// 获取当前 token
const token = client.getToken()
```

---

## 四、API 端点模块

### 4.1 CardsAPI

```ts
export interface CardsAPI {
  // 公开端点
  list(params?: CardsListParams): Promise<ApiResponse<Paginated<Card>>>
  get(id: number): Promise<ApiResponse<Card>>
  hot(params?: PaginationParams): Promise<ApiResponse<Paginated<Card>>>
  search(params: SearchParams): Promise<ApiResponse<Paginated<Card>>>

  // 需要登录
  create(data: CreateCardParams): Promise<ApiResponse<Card>>
  update(id: number, data: UpdateCardParams): Promise<ApiResponse<Card>>
  delete(id: number): Promise<ApiResponse<void>>
  like(id: number): Promise<ApiResponse<void>>
}

export interface CardsListParams extends PaginationParams {
  tag?: string
  status?: number
}

export interface SearchParams extends PaginationParams {
  keyword: string
}

export interface CreateCardParams {
  content: string
  data?: Record<string, any>
  tags?: string[]
  cover?: string
}

export interface UpdateCardParams {
  content?: string
  data?: Record<string, any>
  tags?: string[]
  cover?: string
}
```

### 4.2 UsersAPI

```ts
export interface UsersAPI {
  // 认证
  login(data: LoginParams): Promise<ApiResponse<LoginResult>>
  register(data: RegisterParams): Promise<ApiResponse<LoginResult>>
  guest(): Promise<ApiResponse<LoginResult>>
  logout(): Promise<ApiResponse<void>>

  // 用户信息
  me(): Promise<ApiResponse<User>>
  updateMe(data: UpdateUserParams): Promise<ApiResponse<User>>
  updatePassword(data: PasswordParams): Promise<ApiResponse<void>>
  updateEmail(data: EmailParams): Promise<ApiResponse<void>>

  // 验证码
  captcha(params: CaptchaSendParams): Promise<ApiResponse<void>>
}

export interface LoginParams {
  account: string
  password: string
  captcha?: string
}

export interface RegisterParams {
  account: string
  password: string
  password_confirm: string
  captcha?: string
}

export interface LoginResult {
  token: string
  user: User
}

export interface UpdateUserParams {
  nickname?: string
  avatar?: string
  email?: string
}

export interface PasswordParams {
  old_password: string
  new_password: string
  new_password_confirm: string
}

export interface EmailParams {
  email: string
  captcha: string
}

export interface CaptchaSendParams {
  account: string
  scene?: string
}
```

### 4.3 CommentsAPI

```ts
export interface CommentsAPI {
  listByCard(cardId: number, params?: PaginationParams): Promise<ApiResponse<Paginated<Comment>>>
  create(data: CreateCommentParams): Promise<ApiResponse<Comment>>
  delete(id: number): Promise<ApiResponse<void>>
}

export interface CreateCommentParams {
  pid: number              // 卡片 ID
  content: string
  parent_id?: number       // 父评论 ID（回复）
}
```

### 4.4 TagsAPI

```ts
export interface TagsAPI {
  list(params?: PaginationParams): Promise<ApiResponse<Paginated<Tag>>>
  get(id: number): Promise<ApiResponse<Tag>>
}
```

### 4.5 CaptchaAPI

```ts
export interface CaptchaAPI {
  config(): Promise<ApiResponse<CaptchaConfig>>
  verify(data: CaptchaVerifyParams): Promise<ApiResponse<void>>
}

export interface CaptchaConfig {
  captcha_geetest_v4?: {
    id: string
    status: boolean
  }
  code_enabled: boolean
  captcha_enabled: boolean
}

export interface CaptchaVerifyParams {
  type: 'code' | 'captcha'
  // code 类型
  key?: string
  code?: string
  // captcha 类型（极验）
  lot_number?: string
  captcha_output?: string
  pass_token?: string
  gen_time?: string
}
```

### 4.6 SystemAPI

```ts
export interface SystemAPI {
  themeConfig(): Promise<ApiResponse<ThemePublicConfig>>
}

export interface ThemePublicConfig {
  theme: string
  config: Record<string, any>
}
```

---

## 五、通用类型

### 5.1 ApiResponse

```ts
export interface ApiResponse<T> {
  code: number
  message: string
  data: T
}
```

### 5.2 Paginated

```ts
export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}
```

### 5.3 PaginationParams

```ts
export interface PaginationParams {
  page?: number
  list_rows?: number
}
```

### 5.4 Card

```ts
export interface Card {
  id: number
  aid: number
  user_id: number
  status: number
  is_top: number
  content: string
  data: Record<string, any>
  cover: string
  tags: string
  goods: number
  views: number
  comments: number
  post_ip: string
  created_at: string
  updated_at: string
  deleted_at: string | null
  user?: User
  tags_list?: Tag[]
}
```

### 5.5 User

```ts
export interface User {
  id: number
  nickname: string
  email: string
  phone: string
  avatar: string
  roles_id: number
  status: number
  created_at: string
  updated_at: string
}
```

### 5.6 Comment

```ts
export interface Comment {
  id: number
  aid: number
  pid: number
  user_id: number
  parent_id: number | null
  content: string
  status: number
  created_at: string
  updated_at: string
  user?: User
  children?: Comment[]
}
```

### 5.7 Tag

```ts
export interface Tag {
  id: number
  name: string
  status: number
  created_at: string
}
```

---

## 六、PUBLIC_API 常量表

SSR 模式预加载数据集的定义。PHP 侧和 JS 侧各维护一份，保持同步。

```ts
// constants.ts

export const PUBLIC_API = {
  // Cards
  'cards.hot':     { method: 'GET',  path: '/api/cards/hot' },
  'cards.list':    { method: 'GET',  path: '/api/cards' },
  'cards.get':     { method: 'GET',  path: '/api/cards/:id' },
  'cards.search':  { method: 'GET',  path: '/api/cards/search' },

  // Tags
  'tags.list':     { method: 'GET',  path: '/api/tags' },
  'tags.get':      { method: 'GET',  path: '/api/tags/:id' },

  // Comments
  'comments.list': { method: 'GET',  path: '/api/comments/card/:id' },

  // Users（需要登录，不预加载）
  'users.me':      { method: 'GET',  path: '/api/users/me' },

  // System
  'system.theme':  { method: 'GET',  path: '/api/theme/config' },
} as const

export type PublicAPIKey = keyof typeof PUBLIC_API
```

---

## 七、构建配置

### 7.1 vite.config.ts

```ts
import { defineConfig } from 'vite'
import dts from 'vite-plugin-dts'

export default defineConfig({
  build: {
    lib: {
      entry: 'src/index.ts',
      name: 'LoveCards',
      formats: ['es', 'cjs', 'umd'],
      fileName: (format) => `lovecards.${format}.js`,
    },
    rollupOptions: {
      external: ['axios'],
      output: {
        globals: {
          axios: 'axios',
        },
      },
    },
  },
  plugins: [dts()],
})
```

### 7.2 构建产物

```
dist/
├── lovecards.es.js          # ESM 格式（import）
├── lovecards.cjs.js         # CJS 格式（require）
├── lovecards.umd.js         # UMD 格式（<script> 标签）
├── lovecards.d.ts           # TypeScript 类型声明
└── style.css                # 如果有样式
```

### 7.3 使用方式

```ts
// ESM（SPA 主题）
import { createClient } from '@lovecards/sdk'
const client = createClient({ apiUrl: '/api' })
const { data } = await client.cards.list()

// UMD（SSR 主题，CDN 引入）
<script src="/theme/default-ssr/assets/lovecards.umd.js"></script>
<script>
  const client = LoveCards.createClient({ apiUrl: '/api' })
  client.cards.list().then(res => console.log(res))
</script>
```

---

## 八、package.json

```json
{
  "name": "@lovecards/sdk",
  "version": "1.0.0",
  "description": "LoveCards3 JavaScript SDK",
  "type": "module",
  "main": "dist/lovecards.cjs.js",
  "module": "dist/lovecards.es.js",
  "types": "dist/lovecards.d.ts",
  "exports": {
    ".": {
      "import": "./dist/lovecards.es.js",
      "require": "./dist/lovecards.cjs.js",
      "types": "./dist/lovecards.d.ts"
    }
  },
  "files": ["dist"],
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "lint": "eslint src/",
    "typecheck": "tsc --noEmit"
  },
  "dependencies": {
    "axios": "^1.7.0"
  },
  "devDependencies": {
    "typescript": "^5",
    "vite": "^6",
    "vite-plugin-dts": "^4"
  }
}
```

---

## 九、版本策略

| 变更类型 | 版本影响 | 示例 |
|---|---|---|
| 新增 API 端点 | minor（1.1.0） | 新增 `cards.batch()` |
| 新增类型定义 | minor 或 patch | 新增 `BatchParams` |
| API 端点删除 | major（2.0.0） | 删除 `cards.oldMethod()` |
| API 签名变更 | major（2.0.0） | `list()` 参数结构变化 |
| 响应格式变更 | major（2.0.0） | `data` 字段结构变化 |
| 内部实现优化 | patch（1.0.1） | 错误处理改进 |
| 类型定义修正 | patch（1.0.1） | 修正类型错误 |

---

## 十、错误处理

### 10.1 统一错误格式

```ts
export interface ApiError {
  code: number              // HTTP 状态码
  message: string           // 错误消息
  detail?: any              // 详细信息
}
```

### 10.2 错误类型

```ts
// 网络错误
// error.code = 'ERR_NETWORK'
// error.message = 'Network Error'

// 超时错误
// error.code = 'ECONNABORTED'
// error.message = 'timeout of 10000ms exceeded'

// API 错误
// error.response.status = 400/401/403/404/500
// error.response.data = { code: 0, message: "...", detail: [...] }
```

### 10.3 推荐的错误处理模式

```ts
try {
  const { data } = await client.cards.create({ content: 'hello' })
  // 成功
} catch (error) {
  if (axios.isAxiosError(error)) {
    const apiError = error.response?.data as ApiError
    switch (error.response?.status) {
      case 400:
        // 参数错误，显示 apiError.message
        break
      case 401:
        // 未登录，跳转登录页
        break
      case 403:
        // 无权限
        break
      case 422:
        // 验证失败，显示 apiError.detail
        break
      default:
        // 其他错误
    }
  }
}
```
