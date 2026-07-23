# @lovecards/sdk 开发文档

> 版本：1.0.0 | 最后更新：2026-06-01
>
> 本文档面向 SDK 维护者。AI 维护指南见 `MAINTENANCE.md`，API 参考见 `API_REFERENCE.md`。

---

## 定位

@lovecards/sdk 是**后端 API 的前端类型安全封装层**。它是胶水——把后端 REST API 的 JSON 响应转化为 TypeScript 类型安全的 Promise。

```
SPA 代码  →  SDK (类型安全胶水)  →  HTTP  →  后端 API
```

## 设计原则

| 原则 | 说明 |
|------|------|
| **响应解包** | SDK 方法返回业务数据，不返回 HTTP 包装 |
| **资源按领域分文件** | 每个 API 模块一个 `src/resources/*.ts` |
| **单错误类** | `ApiError` 包含 code/message/status/details，按需判断 |
| **TokenStore 抽象** | token 存储可注入，默认 localStorage |
| **请求去重** | GET 请求自动去重，相同 URL+参数合并为一个 Promise |
| **请求重试** | 可配置重试策略，网络抖动自动恢复 |
| **AbortSignal** | 支持请求取消 |
| **构建产物不提交** | `dist/` 在 `.gitignore` 中 |

## 包结构

```
@lovecards/sdk/
├── src/
│   ├── index.ts                # 入口：createClient + 导出所有类型
│   ├── client.ts               # createClient() + LCClient 接口 + LCClientImpl 类
│   ├── resources/              # 16 个资源类，覆盖后端 16 个路由文件
│   │   ├── base.ts             # BaseResource 基类（_get/_post/_patch/_put/_delete）
│   │   ├── session.ts
│   │   ├── cards.ts
│   │   ├── users.ts
│   │   ├── comments.ts
│   │   ├── tags.ts
│   │   ├── likes.ts
│   │   ├── files.ts
│   │   ├── theme.ts
│   │   ├── roles.ts
│   │   ├── permissions.ts
│   │   ├── config.ts
│   │   ├── dashboard.ts
│   │   ├── storage.ts
│   │   ├── sender.ts
│   │   └── captcha.ts
│   ├── types/
│   │   ├── index.ts            # 聚合导出
│   │   ├── api.ts              # LCClientConfig, TokenStore, ListResult, CreateResult...
│   │   ├── cards.ts            # Card, CardsListParams, CreateCardParams...
│   │   ├── users.ts            # User, LoginParams, LoginResult, ProfileUpdateParams...
│   │   ├── comments.ts         # Comment, CreateCommentParams
│   │   ├── tags.ts             # Tag
│   │   ├── likes.ts            # Like
│   │   ├── files.ts            # File, DirectUploadResult
│   │   ├── theme.ts            # ThemeItem, ThemeConfigData
│   │   ├── roles.ts            # Role, CreateRoleParams, AssignCapabilitiesParams...
│   │   ├── permissions.ts      # CapabilityItem
│   │   ├── config.ts           # ConfigData, ConfigUpdateParams
│   │   ├── dashboard.ts        # DashboardData, ChartDataset
│   │   ├── storage.ts          # StorageDriver, StorageMeta, StorageChannel
│   │   ├── sender.ts           # SenderType, SenderMeta, SenderChannel
│   │   ├── captcha.ts          # CaptchaDriver, CaptchaMeta
│   │   └── system.ts           # SystemUpdateInfo
│   ├── config/
│   │   └── defaults.ts         # defaultTokenStore + defaultConfig
│   ├── helpers/
│   │   └── method-key.ts       # 去重 key 生成
│   ├── constants.ts            # PUBLIC_API（SSR 预加载契约）
│   ├── dedupe.ts               # Deduplicator 请求去重器
│   └── errors.ts               # ApiError 错误类 + from() 工厂
├── docs/
│   ├── SDK_DESIGN.md           # 本文件
│   ├── MAINTENANCE.md          # AI 维护指南
│   └── API_REFERENCE.md        # API 参考
├── dist/                       # 构建产物（不提交 git）
├── package.json
├── tsconfig.json
└── vite.config.ts
```

## 核心类设计

### LCClient（接口）

`createClient()` 返回 `LCClient` 接口，不暴露内部实现：

```typescript
interface LCClient {
  readonly session: Session
  readonly cards: Cards
  readonly users: Users
  readonly comments: Comments
  readonly tags: Tags
  readonly likes: Likes
  readonly files: Files
  readonly theme: Theme
  readonly roles: Roles
  readonly permissions: Permissions
  readonly config: Config
  readonly dashboard: Dashboard
  readonly storage: Storage
  readonly sender: Sender
  readonly captcha: Captcha
  readonly system: System

  setToken(token: string): void
  clearToken(): void
  getToken(): string | null
}
```

### BaseResource（资源基类）

所有资源类继承此基类，自动处理请求发送、token 注入、去重、重试：

```typescript
class BaseResource {
  protected _get<T>(url: string, params?: any, signal?: AbortSignal): Promise<T>
  protected _post<T>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T>
  protected _patch<T>(url: string, data?: any): Promise<T>
  protected _put<T>(url: string, data?: any): Promise<T>
  protected _delete<T>(url: string, params?: any): Promise<T>
}
```

### 资源类示例

```typescript
class Cards extends BaseResource {
  list(params?: CardsListParams): Promise<ListResult<Card>>
  get(id: number): Promise<Card>
  hot(): Promise<Card[]>
  search(params: CardsListParams & { search_value: string }): Promise<ListResult<Card>>
  create(data: CreateCardParams): Promise<CreateResult>
  update(id: number, data: UpdateCardParams): Promise<void>
  delete(id: number): Promise<void>
  like(id: number): Promise<void>
  listOwn(params?: PaginationParams): Promise<ListResult<Card>>
  batch(data: BatchOperateParams): Promise<void>
}
```

## 响应解包

所有方法返回**解包后的业务数据**，不返回 HTTP 包装：

```typescript
// 列表 → ListResult<T>
const { data, pagination } = await client.cards.list({ page: 1 })
// data: Card[], pagination?: PaginationInfo

// 单条 → T
const card = await client.cards.get(1)
// card: Card

// 创建 → CreateResult
const { id } = await client.cards.create({ content: '...' })
// id: string | null（null = 审核模式）

// 更新/删除 → void
await client.cards.update(1, { content: '...' })
await client.cards.delete(1)

// 点赞 → { likes: number }
const { likes } = await client.cards.like(1)
```

### ListResult

```typescript
interface ListResult<T> {
  data: T[]
  pagination?: PaginationInfo
}

interface PaginationInfo {
  currentPage: number
  totalPages: number
  totalItems: number
  itemsPerPage: number
}
```

### CreateResult

```typescript
interface CreateResult {
  id: string | null  // null 表示审核模式，资源已创建但等待审核
}
```

## 错误处理

单一 `ApiError` 类，包含所有错误信息：

```typescript
class ApiError extends Error {
  code: number      // 业务码
  message: string   // 可读错误信息
  status: number    // HTTP 状态码
  details?: any     // 验证错误详情（字段级错误）
}

// 判断是否为 API 错误
import { isApiError } from '@lovecards/sdk'

try {
  await client.cards.create({ content: '...' })
} catch (e) {
  if (isApiError(e)) {
    if (e.status === 401) { /* token 过期 */ }
    if (e.status === 403) { /* 权限不足 */ }
    if (e.status === 422) { /* 验证错误，e.details 有字段级错误 */ }
    showToast(e.message)
  }
}
```

拦截器自动将 AxiosError 转换为 ApiError。前端 `catch {}` 空 catch 仍可用。

## 配置接口

```typescript
interface LCClientConfig {
  apiUrl: string
  tokenStore?: TokenStore       // 可注入，默认 localStorage
  timeout?: number              // 默认 10000
  onAuthError?: () => void      // 401 回调
  debug?: boolean               // 开启请求/响应日志
  retry?: RetryConfig           // 重试配置
  hooks?: {
    beforeRequest?: BeforeRequestHook
    afterResponse?: AfterResponseHook
    onError?: OnErrorHook
  }
}

interface RetryConfig {
  maxRetries?: number           // 最大重试次数，默认 0
  retryOn?: number[]            // 重试的 HTTP 状态码
  retryDelay?: number           // 重试间隔 ms，默认 1000
}
```

## Token 管理

SDK 通过 `TokenStore` 接口管理 token，默认使用 `localStorage`：

```typescript
// 默认行为（SPA）
const client = createClient({ apiUrl: '/api' })
client.setToken('xxx')    // 写入 localStorage
client.getToken()         // 读取 localStorage
client.clearToken()       // 清除 localStorage

// 自定义 TokenStore（SSR/Admin）
const client = createClient({
  apiUrl: '/api',
  tokenStore: {
    get: () => Cookies.get('UTOKEN'),
    set: (t) => Cookies.set('UTOKEN', t),
    clear: () => Cookies.remove('UTOKEN'),
  },
})
```

## 请求功能

### 去重

GET 请求自动去重。相同 URL + 参数的并发请求合并为一个 Promise：

```typescript
const [a, b] = await Promise.all([
  client.cards.list({ page: 1 }),
  client.cards.list({ page: 1 }),  // 复用第一个请求
])
```

### 重试

```typescript
const client = createClient({
  apiUrl: '/api',
  retry: {
    maxRetries: 2,
    retryOn: [408, 429, 500, 502, 503, 504],
    retryDelay: 1000,
  },
})
```

### 请求取消

```typescript
const controller = new AbortController()
client.cards.list({ page: 1 }, controller.signal)
// 取消
controller.abort()
```

### 调试模式

```typescript
const client = createClient({
  apiUrl: '/api',
  debug: true,  // console.log 所有请求/响应
})
```

## 生命周期钩子（Lifecycle Hooks）

SDK 提供 3 个生命周期钩子，用于监控和干预请求/响应流程：

| 钩子 | 触发时机 | 可修改 | 异常处理 |
|------|---------|--------|---------|
| `beforeRequest` | 每次 HTTP 请求前（含重试） | 可修改 headers | 抛异常 = 中断请求，包装为 ApiError |
| `afterResponse` | 每次 HTTP 响应后（解包后） | 只读 | 异常被吞掉，不影响业务 |
| `onError` | 每次 HTTP 请求失败时 | 只读 | 异常被吞掉，不影响业务 |

### 构造时注册

```typescript
const client = createClient({
  apiUrl: '/api',
  hooks: {
    beforeRequest: (ctx) => {
      // ctx.config.headers 可修改（加 trace ID 等）
      ctx.config.headers['X-Trace-Id'] = generateTraceId()
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

### 运行时注册（动态增删）

```typescript
const unsub = client.hooks.afterResponse((ctx) => {
  monitorStore.add(ctx)
})

// 组件卸载时清理
onUnmounted(() => unsub())
```

### Hook Context 数据

```typescript
interface RequestContext {
  requestId: string     // 唯一请求 ID，重试间不变
  method: string        // 'GET'
  url: string           // '/cards'
  startTime: number     // Date.now()
  retryCount: number    // 当前重试次数（0 = 首次）
  config: {
    headers: Record<string, string | string[] | undefined>
  }
}

interface ResponseContext extends RequestContext {
  status: number        // 200
  data: any             // 解包后的业务数据
  elapsedMs: number     // 请求耗时
}

interface ErrorContext extends RequestContext {
  status: number        // 404
  message: string       // 错误消息
  code: number          // 业务码
  elapsedMs: number
  isRetryable: boolean  // 是否可重试
  willRetry: boolean    // 是否会重试
  reason: 'http' | 'timeout' | 'network' | 'cancelled'
}
```

### 设计约束

- `beforeRequest` 可以修改 `ctx.config.headers`（加自定义 header）。**不要修改 params/data**——已过内部序列化，修改会破坏后端契约
- `afterResponse`/`onError` 是只读通知，**不要修改**传入的 data/error 对象
- `beforeRequest` 抛异常 = 中断请求，异常会被 `ApiError.from()` 包装
- 去重请求（相同 GET 并发）只触发一次 hook
- 重试时 hooks 每次重试都触发，`retryCount` 递增
- `requestId` 在同一请求的所有重试中保持不变

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
