# @lovecards/sdk 开发文档

> 版本：2.0.0 | 最后更新：2026-05-31
>
> 本文档面向 SDK 维护者。AI 维护指南见 `MAINTENANCE.md`，用户文档见 `README.md`，API 参考见 `API_REFERENCE.md`。

---

## 定位

@lovecards/sdk 是**后端 API 的前端类型安全封装层**。它是胶水——把后端 REST API 的 JSON 响应转化为 TypeScript 类型安全的 Promise。

```
SPA 代码  →  SDK (类型安全胶水)  →  HTTP  →  后端 API
```

## 设计原则

| 原则 | 说明 |
|------|------|
| **响应格式统一** | `ApiResponse<T> = { data: T, pagination? }` |
| **资源按领域分文件** | 每个 API 模块一个 `src/resources/*.ts` |
| **错误类层次** | 按 HTTP 状态码派生子类，`instanceof` 可判断 |
| **核心最小化** | `src/core/` 只负责请求发送 + token 管理 |
| **构建产物不提交** | `dist/` 在 `.gitignore` 中 |

## 包结构

```
@lovecards/sdk/
├── src/
│   ├── index.ts                # 入口：createClient + 导出所有类型
│   ├── core/
│   │   ├── LoveCards.ts        # 核心构造函数（组装资源类）
│   │   ├── LoveCardsResource.ts # 资源基类（_get/_post/_patch/_delete/_put）
│   │   └── Deduplicator.ts     # GET 请求去重
│   ├── resources/              # 8 个资源类，覆盖后端 16 个路由文件
│   │   ├── Cards.ts
│   │   ├── Session.ts
│   │   ├── Users.ts
│   │   ├── Comments.ts
│   │   ├── Tags.ts
│   │   ├── Likes.ts
│   │   ├── Files.ts
│   │   └── Theme.ts
│   ├── errors/
│   │   ├── LoveCardsError.ts    # 基类
│   │   ├── AuthenticationError.ts # 401
│   │   ├── PermissionError.ts   # 403
│   │   ├── NotFoundError.ts     # 404
│   │   ├── ValidationError.ts   # 400/422
│   │   ├── RateLimitError.ts    # 429
│   │   └── ServerError.ts       # 500/502/503/504
│   ├── types/
│   │   ├── index.ts            # 聚合导出
│   │   ├── api.ts              # ApiResponse<T>, PaginationInfo, PaginationParams...
│   │   ├── cards.ts            # Card, CreateCardParams...
│   │   ├── users.ts            # User, LoginParams, LoginResult...
│   │   ├── comments.ts         # Comment, CreateCommentParams
│   │   ├── tags.ts             # Tag
│   │   ├── likes.ts            # LikeItem
│   │   ├── files.ts            # FileItem, DirectUploadResult
│   │   └── theme.ts            # ThemeItem, ThemeConfigData
│   ├── config/
│   │   └── defaults.ts         # defaultTokenStore + defaultConfig
│   ├── helpers/
│   │   └── method-key.ts       # 去重 key 生成
│   ├── constants.ts            # PUBLIC_API（SSR 预加载契约）
│   ├── dedupe.ts               # Deduplicator 请求去重器
│   └── errors.ts               # ApiError 错误类 + from() 工厂（保留兼容）
├── docs/
│   ├── SDK_DESIGN.md            # 本文件
│   ├── MAINTENANCE.md           # AI 维护指南
│   ├── API_REFERENCE.md         # API 参考（91 个命名路由）
│   └── README.md                # 用户文档
├── dist/                        # 构建产物（不提交 git）
├── package.json                 # v2.0.0
├── tsconfig.json
└── vite.config.ts
```

## 核心类设计

### LoveCards（核心构造函数）

```typescript
class LoveCards {
  cards: Cards
  session: Session
  users: Users
  comments: Comments
  tags: Tags
  likes: Likes
  files: Files
  theme: Theme

  constructor(config: LoveCardsConfig)
  setToken(token: string): void
  clearToken(): void
  getToken(): string | null
}
```

### LoveCardsResource（资源基类）

所有资源类继承此基类，自动处理请求发送和 token 注入：

```typescript
class LoveCardsResource {
  protected _get<T>(url: string, params?: any): Promise<ApiResponse<T>>
  protected _post<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>>
  protected _patch<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>>
  protected _put<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>>
  protected _delete<T>(url: string, body?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>>
}
```

### 资源类示例

```typescript
class Cards extends LoveCardsResource {
  list(params?: CardsListParams) { return this._get<Card[]>('/cards', params) }
  get(id: number)               { return this._get<Card>(`/cards/${id}`) }
  hot()                         { return this._get<Card[]>('/cards/hot') }
  search(params: SearchParams)  { return this._get<Card[]>('/cards/search', params) }
  create(data: CreateCardParams){ return this._post<{ id: string }>('/cards', data) }
  update(id: number, data: UpdateCardParams) { return this._patch<void>(`/cards/${id}`, data) }
  delete(id: number)            { return this._delete<void>(`/cards/${id}`) }
  like(id: number)              { return this._post<void>(`/cards/${id}/like`) }
  listOwn()                     { return this._get<Card[]>('/users/me/cards') }
  allList(params?: AdminListParams) { return this._get<Card[]>('/all/cards', params) }
  allGet(id: number)            { return this._get<Card>(`/all/cards/${id}`) }
  allUpdate(id: number, data: UpdateCardParams) { return this._patch<void>(`/all/cards/${id}`, data) }
  allDelete(id: number)         { return this._delete<void>(`/all/cards/${id}`) }
  batch(data: BatchOperateParams) { return this._post<void>('/all/cards/batch', data) }
}
```

## 响应类型

### 成功响应

```typescript
interface ApiResponse<T> {
  data: T
  pagination?: PaginationInfo   // 列表接口才有
}

interface PaginationInfo {
  currentPage: number
  totalPages: number
  totalItems: number
  itemsPerPage: number
}
```

### 错误响应

不返回值，直接抛出错误类实例：

```typescript
try {
  const { data } = await client.cards.list()
  // data: Card[]
} catch (e) {
  if (e instanceof ValidationError) { ... }
  if (e instanceof AuthenticationError) { ... }
}
```

## 配置接口

```typescript
interface LoveCardsConfig {
  apiUrl: string
  tokenStore?: TokenStore       // 可注入，默认 localStorage
  deduplicate?: boolean         // 默认 true（GET 请求去重）
  timeout?: number              // 默认 10000
  onAuthError?: () => void      // 401 回调
  onError?: (error: ApiError) => void // 错误钩子，不阻断 reject
}
```

## 错误类层次

```
LoveCardsError (基类)
├── AuthenticationError   # 401 - token 无效/过期
├── PermissionError       # 403 - 权限不足
├── NotFoundError         # 404 - 资源不存在
├── ValidationError       # 400/422 - 参数错误
├── RateLimitError        # 429 - 请求过于频繁
└── ServerError           # 500/502/503/504 - 服务器错误
```

错误工厂：响应拦截器根据 HTTP 状态码自动创建对应实例。

## 请求去重

GET 请求自动去重。相同 URL + 参数的并发请求合并为一个 Promise：

```typescript
const [a, b] = await Promise.all([
  client.cards.list({ page: 1 }),
  client.cards.list({ page: 1 }),  // 复用第一个请求
])
```

## 构建配置

- **Vite 库模式**：`src/index.ts` 为入口
- **三种格式**：ESM + CJS + UMD
- **外部依赖**：`axios` 不打包（external）
- **UMD 全局名**：`window.LC`
- **类型声明**：`vite-plugin-dts` 生成 `.d.ts`
- **postbuild**：自动复制 UMD 到 `BackEnd/public/theme/default-ssr/assets/lovecards.umd.js`

## 构建流程

```bash
npm run build        # 构建（自动同步 UMD 到 SSR theme）
npm run typecheck    # 类型检查
```

`dist/` 不提交 git，由构建脚本自动生成。

## 版本策略

| 变更 | 版本 |
|------|------|
| 新增 API 端点 | minor |
| 新增类型定义 | minor 或 patch |
| API 端点删除 | major |
| 签名/响应格式变更 | major |
| 内部优化 | patch |

## PUBLIC_API 常量表

SSR 预加载数据集定义。PHP 侧 `ThemeEngine::PUBLIC_API` 保持同步。

```typescript
export const PUBLIC_API = {
  'cards.hot':      { method: 'GET', path: '/api/cards/hot' },
  'cards.list':     { method: 'GET', path: '/api/cards' },
  'cards.get':      { method: 'GET', path: '/api/cards/:id' },
  'cards.search':   { method: 'GET', path: '/api/cards/search' },
  'tags.list':      { method: 'GET', path: '/api/tags' },
  'tags.get':       { method: 'GET', path: '/api/tags/:id' },
  'comments.list':  { method: 'GET', path: '/api/cards/:id/comments' },
  'users.me':       { method: 'GET', path: '/api/users/me' },
  'system.theme':   { method: 'GET', path: '/api/theme/config' },
  'captcha.config': { method: 'GET', path: '/api/captcha/config' },
} as const
```

新增公开 API 端点时：
1. 在 `SDK/src/constants.ts` 添加 key
2. 在 `BackEnd/app/frontend/service/ThemeEngine.php` 的 `PUBLIC_API` 常量添加相同 key
3. 确保 PHP 的 path 与 SDK 的 path 一致

## 与主题引擎的关系

```
SPA 主题：React/Next.js → SDK → /api/* → PHP
SSR 主题：PHP ThemeEngine → 内部调用 Service 层（不走 SDK）
Widget 模式：UMD SDK（window.LC）→ /api/* → PHP
```

SDK 是前端调后端 API 的桥梁。PHP ThemeEngine 内部调用 Service 层，不经过 SDK。
