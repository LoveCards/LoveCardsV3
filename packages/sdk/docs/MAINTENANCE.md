# @lovecards/sdk AI 维护指南

> AI agent 通过本文档了解 SDK 架构、同步规则和编码规范。

---

## 核心原则

SDK 和 PHP 后端**不共享代码**，但共享两样东西：

1. **API 路由映射** — 16 个 `app/api/route/*.php` 文件 ↔ `src/resources/*.ts` 的 16 个资源类
2. **PUBLIC_API 常量表** — `src/constants.ts` ↔ `ThemeEngine.php::PUBLIC_API`

修改 API 时必须同时更新这两侧。

---

## 架构概览

```
createClient(config) → LCClient
  ├── session: Session       # 认证
  ├── cards: Cards           # 卡片
  ├── users: Users           # 用户
  ├── comments: Comments     # 评论
  ├── tags: Tags             # 标签
  ├── likes: Likes           # 点赞
  ├── files: Files           # 文件
  ├── theme: Theme           # 主题
  ├── roles: Roles           # 角色
  ├── permissions: Permissions # 权限
  ├── config: Config         # 配置
  ├── dashboard: Dashboard   # 控制台
  ├── storage: Storage       # 存储
  ├── sender: Sender         # 消息
  └── captcha: Captcha       # 验证码
```

每个资源类继承 `BaseResource`，通过 `_get/_post/_patch/_put/_delete` 发送请求。

---

## 第一步：定位后端路由

所有 API 端点在 `BackEnd/app/api/route/` 下，16 个文件：

| 路由文件 | SDK 资源类 | 端点数 |
|---------|-----------|:------:|
| `session.php` | `Session` | 6 |
| `cards.php` | `Cards` | 10 |
| `comments.php` | `Comments` | 7 |
| `tags.php` | `Tags` | 6 |
| `likes.php` | `Likes` | 2 |
| `users.php` | `Users` | 10 |
| `roles.php` | `Roles` | 8 |
| `permissions.php` | `Permissions` | 2 |
| `config.php` | `Config` | 8 |
| `dashboard.php` | `Dashboard` | 1 |
| `storage.php` | `Storage` | 6 |
| `files.php` | `Files` | 8 |
| `captcha.php` | `Captcha` | 5 |
| `sender.php` | `Sender` | 6 |
| `theme.php` | `Theme` | 8 |
| `system.php` | `System` | 1 |

每个路由定义格式：

```php
Route::get('path', 'Controller/method')
    ->name('domain.action')
    ->setOption('meta', ['name' => '描述', 'group' => '分组', 'public' => true]);
```

---

## 第二步：添加方法到 SDK

### A. 添加类型（`src/types/{domain}.ts`）

```typescript
export interface MyNewParams {
  field1: string
  field2?: number
}
```

### B. 添加方法到资源类（`src/resources/{domain}.ts`）

```typescript
class Cards extends BaseResource {
  newMethod(id: number, data: MyNewParams): Promise<void> {
    return this._post<void>(`/cards/${id}/action`, data)
  }
}
```

可用的请求方法：

| 方法 | HTTP | 用途 |
|------|------|------|
| `this._get<T>(url, params?, signal?)` | GET | 列表/详情，带自动去重 |
| `this._post<T>(url, data?, config?)` | POST | 创建/提交 |
| `this._patch<T>(url, data?)` | PATCH | 局部更新 |
| `this._put<T>(url, data?)` | PUT | 全量更新 |
| `this._delete<T>(url, params?)` | DELETE | 删除 |

### C. 如果是公开 GET 端点，更新 PUBLIC_API

```typescript
// src/constants.ts
'cards.new': { method: 'GET', path: '/api/cards/new' },
```

同时更新 `BackEnd/app/frontend/service/ThemeEngine.php` 的同一常量。

---

## 第三步：新增模块

1. 新建 `src/resources/{domain}.ts`
2. 在 `src/client.ts` 中导入并添加到 `LCClientImpl`

```typescript
import { Notifications } from './resources/notifications'

// LCClientImpl 构造函数中
this.notifications = new Notifications(instance, opts)

// LCClient 接口中
readonly notifications: Notifications
```

---

## 第四步：方法命名规范

| 路由 name | SDK 方法名 | 说明 |
|-----------|-----------|------|
| `cards.list` | `cards.list()` | 使用路由 name 的最后一段 |
| `users.me.get` | `users.me()` | 取中间部分做方法名 |
| `theme.publicConfig` | `theme.publicConfig()` | 完整使用 |
| `roles.getRoleCapabilities` | `roles.getCapabilities()` | 简化 |

---

## 第五步：类型使用规范

### 共性类型（`src/types/api.ts`）

```typescript
PaginationParams     // page, list_rows
ListParams           extends PaginationParams  // + search_value, search_keys, order_key, order_desc
BatchOperateParams   // method, ids, value?
ListResult<T>        // { data: T[], pagination?: PaginationInfo }
CreateResult         // { id: string | null }
UploadResult         // { id, url, path, size, mime_type, original_name, channel_slug }
```

### 响应解包

所有方法返回解包后的业务数据：

```typescript
const { data, pagination } = await client.cards.list()
// data: Card[]           ← 直接是数组
// pagination: PaginationInfo  ← 可选，列表接口才有

const card = await client.cards.get(1)
// card: Card             ← 直接是对象
```

### 避免 `any`

新加方法必须使用具体类型，杜绝 `any`。如返回结构复杂，先定义 interface。

---

## 生命周期钩子

SDK 提供 3 个生命周期钩子。添加新的 hook 类型时需要：

### 1. 定义类型（`src/types/api.ts`）

```typescript
export interface NewHookContext extends RequestContext {
  customField: string
}
export interface NewHook {
  (ctx: NewHookContext): void | Promise<void>
}
```

### 2. 添加到 `ResourceOptions`（`src/resources/base.ts`）

```typescript
export interface ResourceOptions {
  // 现有字段...
  hooks: {
    beforeRequest: BeforeRequestHook[]
    afterResponse: AfterResponseHook[]
    onError: OnErrorHook[]
    // ↑ 在这里添加新的 hook 类型
  }
}
```

### 3. 在 `_request()` 中调用

```typescript
// 在请求前调用
for (const fn of [...this._opts.hooks.yourNewHook]) {
  try { await fn(ctx) } catch {}  // 只读
  // 或
  await fn(ctx)                    // 可中断
}
```

### 4. 添加到 `HookRegistration` 和 `LCClientImpl`

```typescript
// client.ts — HookRegistration
export interface HookRegistration {
  beforeRequest: (fn: BeforeRequestHook) => () => void
  afterResponse: (fn: AfterResponseHook) => () => void
  onError: (fn: OnErrorHook) => () => void
  // ↑ 添加运行时注册方法
}
```

### 钩子设计原则

| 原则 | 说明 |
|------|------|
| 可修改 vs 只读 | `beforeRequest` 可修改 headers；`afterResponse`/`onError` 只读 |
| 异常处理 | `beforeRequest` 异常中断请求；`afterResponse`/`onError` 异常被吞掉 |
| 执行顺序 | FIFO（注册顺序），遍历前复制快照 `[...arr]` |
| 去重 | 并发相同 GET 只触发一次 hook |
| 约束 | hook 类型不暴露 Axios 内部类型；所有错误统一为 `ApiError` |

---

## 第六步：消费端同步

修改 SDK 方法后，检查以下消费端是否需要同步：

| 消费端 | 位置 | 涉及变更 |
|--------|------|---------|
| FrontEnd-index | `lib/sdk/` 目录 | 类型声明 + JS 产物 |
| SSR theme (app.js) | `BackEnd/public/theme/default-ssr/assets/app.js` | UMD 方式调用 |

---

## 第七步：构建验证

```bash
# SDK 目录
npm run typecheck    # 类型检查，零错误
npm run build        # 构建（自动执行 postbuild 同步 UMD 到 SSR theme）
```

---

## 关键约束

| 约束 | 原因 |
|------|------|
| `createClient()` 入口签名不变 | 前端 `lib/api.ts` 依赖 |
| `LCClient` 接口结构不变 | 前端调用方式 `client.cards.list()` |
| UMD 全局名 `window.LC` | SSR theme `app.js` 依赖 |
| `PUBLIC_API` 常量不变 | SSR 预加载契约 |
| axios 作为外部依赖 | UMD bundle 体积 |
| 所有类型字段与数据库 schema 对齐 | 避免运行时字段名不匹配 |
| 不暴露 `password` 等敏感字段 | 安全考虑 |
| `params` 用 `DELETE` query params 而非 body | 部分代理会剥离 DELETE body |
| Hook 类型不暴露 `AxiosRequestConfig` | 消费者不应依赖 Axios 类型 |
| `beforeRequest` 不应修改 `params`/`data` | 已过内部序列化，修改破坏后端契约 |
