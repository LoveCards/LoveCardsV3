# @lovecards/sdk AI 维护指南

> AI agent 通过本文档了解 SDK 架构、同步规则和编码规范。

---

## 核心原则

SDK 和 PHP 后端**不共享代码**，但共享两样东西：

1. **API 路由映射** — 16 个 `app/api/route/*.php` 文件 ↔ `src/resources/*.ts` 的 8 个资源类
2. **PUBLIC_API 常量表** — `src/constants.ts` ↔ `ThemeEngine.php::PUBLIC_API`

修改 API 时必须同时更新这两侧。

---

## 第一步：定位后端路由

所有 API 端点在 `BackEnd/app/api/route/` 下，16 个文件：

| 路由文件 | SDK 资源类 | 端点数 |
|---------|-----------|:------:|
| `session.php` | `Session` | 6 |
| `cards.php` | `Cards` | 14 |
| `comments.php` | `Comments` | 11 |
| `tags.php` | `Tags` | 10 |
| `likes.php` | `Likes` | 2 |
| `users.php` | `Users` | 10 |
| `roles.php` | —（管理端，SDK 未覆盖） | 8 |
| `permissions.php` | —（管理端） | 2 |
| `config.php` | —（管理端） | 8 |
| `system.php` | —（管理端） | 1 |
| `dashboard.php` | —（管理端） | 1 |
| `storage.php` | —（管理端） | 6 |
| `files.php` | `Files` | 8 |
| `captcha.php` | —（管理端） | 5 |
| `sender.php` | —（管理端） | 6 |
| `theme.php` | `Theme` | 8 |

每个路由定义格式：

```php
Route::get('path', 'Controller/method')
    ->name('domain.action')
    ->setOption('meta', ['name' => '描述', 'group' => '分组', 'public' => true]);
```

提取字段：
- `Route::get/post/patch/delete/put` → HTTP 方法
- `'path'` → API 路径（不含 `/api` 前缀）
- `name('domain.action')` → 路由名称，`domain` 对应资源类名
- `'public' => true` → 公开端点，无需 token
- `'group'` → 分组标签

---

## 第二步：添加方法到 SDK

### A. 添加类型（`src/types/{domain}.ts`）

```typescript
// 新增类型
export interface MyNewParams {
  field1: string
  field2?: number
}
```

### B. 添加方法到资源类（`src/resources/{Domain}.ts`）

```typescript
class Cards extends LoveCardsResource {
  // 已有方法...

  // 新增方法
  newMethod: (id: number, data: MyNewParams) =>
    this._post<void>(`/cards/${id}/action`, data),
}
```

可用的请求辅助方法（继承自 `LoveCardsResource`）：

| 辅助方法 | HTTP | 用途 |
|---------|------|------|
| `this._get<T>(url, params?)` | GET | 列表/详情，带自动去重 |
| `this._post<T>(url, data?, config?)` | POST | 创建/提交 |
| `this._patch<T>(url, data?, config?)` | PATCH | 局部更新 |
| `this._put<T>(url, data?, config?)` | PUT | 全量更新 |
| `this._delete<T>(url, body?, config?)` | DELETE | 删除（支持带 body） |

**文件上传**需要覆盖 Content-Type：

```typescript
upload: (formData: FormData) =>
  this._post<FileItem>('/files', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
```

### C. 如果是公开 GET 端点，更新 PUBLIC_API

```typescript
// src/constants.ts
'cards.new': { method: 'GET', path: '/api/cards/new' },
```

同时更新 `BackEnd/app/frontend/service/ThemeEngine.php` 的同一常量。

---

## 第三步：新增模块

1. 新建 `src/resources/{Domain}.ts`
2. 在 `src/core/LoveCards.ts` 中注册

```typescript
// src/core/LoveCards.ts
import { Notifications } from '../resources/Notifications'

export class LoveCards {
  notifications: Notifications

  constructor(config: LoveCardsConfig) {
    // ...
    this.notifications = new Notifications(this)
  }
}
```

---

## 第四步：方法命名规范

| 路由 name | SDK 方法名 | 说明 |
|-----------|-----------|------|
| `cards.list` | `cards.list()` | 使用路由 name 的最后一段 |
| `cards.allList` | `cards.allList()` | admin 方法保留 `all` 前缀 |
| `cards.batch` | `cards.batch()` | 批操作统一用 `batch` |
| `users.me.get` | `users.me()` | 取中间部分做方法名 |
| `theme.publicConfig` | `theme.publicConfig()` | 完整使用 |

特殊路径参数映射：

| 路由路径 | SDK 参数 | 示例 |
|---------|---------|------|
| `:id` | `id: number` | `cards.get(id)` |
| `:type` | `type: string` | `storage.meta(type)` |
| `:slug` | `slug: string` | `captcha.meta(slug)` |

---

## 第五步：类型使用规范

### 共性类型（`src/types/api.ts`）

```typescript
PaginationParams     // page, list_rows
AdminListParams      extends PaginationParams  // + search_value, search_keys, order_key, order_desc
BatchOperateParams   // operation, ids, value?
```

### 响应解包规则

所有方法返回 `Promise<ApiResponse<T>>`，`data` 已是一级业务数据：

```typescript
const { data, pagination } = await client.cards.list()
// data: Card[]           ← 直接是数组
// pagination: PaginationInfo  ← 可选，列表接口才有
const { data: card } = await client.cards.get(1)
// card: Card             ← 直接是对象
```

### 避免 `any`

新加方法必须使用具体类型，杜绝 `any`。如返回结构复杂，先定义 interface。

---

## 第六步：消费端同步

修改 SDK 方法后，检查以下消费端是否需要同步：

| 消费端 | 位置 | 涉及变更 |
|--------|------|---------|
| FrontEnd-index | `lib/hooks/` 目录 | 方法名/参数变化需同步 |
| SSR theme (app.js) | `BackEnd/public/theme/default-ssr/assets/app.js` | 原生 JS，UMD 方式调用 |

---

## 第七步：构建验证

```bash
# SDK 目录
npm run typecheck    # 类型检查，零错误
npm run build        # 构建（自动执行 postbuild 同步 UMD 到 SSR theme）
```

---

## 架构速查

### 配置项

```typescript
interface LoveCardsConfig {
  apiUrl: string
  tokenStore?: { get(): string|null; set(t: string): void; clear(): void }
  deduplicate?: boolean         // 默认 true（GET 请求去重）
  timeout?: number              // 默认 10000
  onAuthError?: () => void      // 401 回调
  onError?: (error: ApiError) => void
}
```

### 错误对象

```typescript
class ApiError extends Error {
  code: number    // 业务码
  message: string // 可读的错误信息
  status: number  // HTTP 状态码
}
```

调用方捕获：

```typescript
import { ApiError, isApiError } from '@lovecards/sdk'

try {
  const { data } = await client.cards.list()
} catch (e) {
  if (isApiError(e)) {
    console.log(e.code, e.message, e.status)
  }
}
```

### Token Store 注入

```typescript
// 默认 localStorage，SSR 安全
createClient({ tokenStore: { get, set, clear } })

// Admin 对接 cookie
createClient({
  tokenStore: {
    get: () => Cookies.get('UTOKEN'),
    set: (t) => Cookies.set('UTOKEN', t, { expires: 7 }),
    clear: () => Cookies.remove('UTOKEN'),
  },
})
```

### UMD 全局名

```typescript
window.LC.createClient()
window.LoveCards.createClient()  // 向下兼容别名
```
