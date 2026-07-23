# @lovecards/sdk

LoveCards3 JavaScript SDK — 框架无关的 REST API 封装层，覆盖后端 91 个命名路由。

**依赖**：axios
**产物**：ESM / CJS / UMD（gzip ~3KB）
**TypeScript**：16 个类型模块，全量类型定义

---

## 安装

```bash
# npm
npm install @lovecards/sdk

# 本地开发（monorepo）
npm install
```

### UMD（<script> 标签）

```html
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="/lovecards.umd.js"></script>
<script>
  var client = window.LC.createClient({ apiUrl: '/api' })
</script>
```

---

## 快速开始

```typescript
import { createClient } from '@lovecards/sdk'

const client = createClient({
  apiUrl: '/api',
  onAuthError: () => { location.href = '/login' },
})

// 公开接口 — 列表返回 ListResult<T>（含 data + pagination）
const list = await client.cards.list({ page: 1 })
console.log(list.data)    // Card[]
console.log(list.pagination) // { currentPage, totalPages, totalItems, itemsPerPage }

// 登录 — 返回 LoginResult（已解包）
const loginResult = await client.session.login({ account, password })
client.setToken(loginResult.token)

// 单条 — 直接返回业务对象
const user = await client.users.me()
console.log(user) // User
```

---

## 完整 API 参考

见 [`docs/API_REFERENCE.md`](./docs/API_REFERENCE.md)，覆盖全部模块和示例。

---

## 响应格式

所有方法返回已解包的业务数据，不再包裹在 `ApiResponse` 中：

```typescript
// 列表 — 返回 ListResult<T>
const { data, pagination } = await client.cards.list()
// data: Card[]                  ← 直接是业务数据数组
// pagination: PaginationInfo    ← 列表接口才有

// 单条 — 直接返回业务对象
const card = await client.cards.get(1)
// card: Card

// 创建 — 返回 CreateResult
const result = await client.cards.create({ content: '...' })
// result: { id: string }

// void 操作 — 无返回值
await client.cards.delete(1)
```

## Token 管理

```typescript
client.setToken('eyJhbGci...')       // 设置 token
client.getToken()                     // 获取当前 token
client.clearToken()                   // 清除 token
```

Token 自动附加到每个请求的 `Authorization` 和 `X-Token` header。

### 自定义 Token 存储

```typescript
const client = createClient({
  apiUrl: '/api',
  tokenStore: {
    get: () => localStorage.getItem('my_token'),
    set: (t) => localStorage.setItem('my_token', t),
    clear: () => localStorage.removeItem('my_token'),
  },
})
```

---

## 错误处理

```typescript
import { ApiError, isApiError } from '@lovecards/sdk'

try {
  const list = await client.cards.list()
} catch (e) {
  if (isApiError(e)) {
    console.log(e.code, e.message, e.status)
  }
}
```

---

## SSR 预加载

```typescript
import { PUBLIC_API } from '@lovecards/sdk'

console.log(PUBLIC_API['cards.hot'])
// { method: 'GET', path: '/api/cards/hot' }
```

| Key | 端点 | 公开 |
|-----|------|:----:|
| `cards.hot` | GET /api/cards/hot | ✅ |
| `cards.list` | GET /api/cards | ✅ |
| `cards.get` | GET /api/cards/:id | ✅ |
| `cards.search` | GET /api/cards/search | ✅ |
| `tags.list` | GET /api/tags | ✅ |
| `tags.get` | GET /api/tags/:id | ✅ |
| `comments.list` | GET /api/cards/:id/comments | ✅ |
| `users.me` | GET /api/users/me | ❌ |
| `system.theme` | GET /api/theme/config | ✅ |
| `captcha.config` | GET /api/captcha/config | ✅ |

PHP 侧维护相同数据（`ThemeEngine::PUBLIC_API`），用于内部调度获取首屏数据。

---

## 构建产物

```bash
npm run build        # 构建（自动同步 UMD 到 SSR theme）
npm run typecheck    # 类型检查
```

| 文件 | 格式 | 大小 | gzip |
|------|------|:----:|:----:|
| `lovecards.es.js` | ESM | ~13 KB | ~3 KB |
| `lovecards.cjs.js` | CJS | ~8 KB | ~3 KB |
| `lovecards.umd.js` | UMD | ~9 KB | ~3 KB |

---

## 版本策略

| 变更 | 版本 |
|------|------|
| 新增 API 端点 | minor |
| 新增类型定义 | minor 或 patch |
| API 端点删除 | major |
| 签名/响应格式变更 | major |
| 内部优化 | patch |
