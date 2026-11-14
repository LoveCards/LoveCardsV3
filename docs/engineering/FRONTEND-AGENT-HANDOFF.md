# 前端 Agent 接手说明（2026-08-20）

本文件面向继续开发用户主题或管理端的前端 Agent。后端、Route Contract、OpenAPI 和 `@lovecards/sdk` 已提供本轮公共能力；前端应从生成 SDK 接入，不手写 Axios/fetch 兼容层，也不要为后端校验问题发送无关空字段。

## 接手前先确认

1. 工作目录的 Git 根必须是 LoveCardsV3 当前仓库。
2. 从仓库根执行 `npm ci`，不要在 workspace 内维护另一份 lockfile。
3. API 改动先由后端更新 Route/Validate/Resource，再运行 `npm run generate:sdk`；不要直接编辑 `packages/sdk/src/generated/**`。
4. Admin 只通过 `apps/admin/lib/api.ts` 的 `useApi()` 获取 client；默认主题只通过现有 SDK client/provider/service 层访问 API。
5. `apiUrl` 使用同源 `/api`。Theme Gateway Bootstrap 已提供同一值，不要写死开发端口。

## 新增 SDK 资源

SDK 当前由 171 条命名路由生成 25 个 Resource。本轮新增/扩展重点如下（签名以生成 `.d.ts`/资源源码为准）：

### 草稿

```typescript
await client.drafts.list({ cursor, limit })
await client.drafts.create({ title, content, payload })
await client.drafts.get(id)
await client.drafts.update(id, { title, content, payload })
await client.drafts.delete(id)
```

对应默认主题 `/drafts`、`/drafts/:id` 与 `/compose?draft=<id>`。payload 可以保存发布类型与已拥有媒体 ID；发布已恢复的草稿媒体时不得重复上传。

### 收藏与浏览历史

```typescript
await client.library.list({ cursor, limit })
await client.library.save({ resource_type: 'card', resource_id: String(cardId) })
await client.library.remove({ resource_type: 'card', resource_id: String(cardId) })
await client.library.history({ cursor, limit })
await client.library.history_record({ resource_type: 'card', resource_id: String(cardId) })
await client.library.history_clear()
```

注意 `remove` 的两个字段是必填 DELETE query，生成 SDK 已真实透传；不得恢复无参 `remove()` 或在前端猜测“删除最近一项”。列表返回 cursor page，而不是旧式 page/list_rows。

### 通知

```typescript
await client.notifications.list({ cursor, limit, unread_only, category: 'engagement' })
await client.notifications.unread({ category: 'engagement' })
await client.notifications.read({ ids })
await client.notifications.readAll({ category: 'engagement' })
await client.notifications.stream({ after })
```

`engagement` 只属于列表/未读/批量已读查询参数，返回项的 `category` 仍是 `social|message|interaction|system`。游标是不透明字符串，必须原样回传；不得分别请求三类后在前端拼接。省略 category 的 unread/readAll 仍表示全局通知。默认轮询模式按配置使用 list/unread；实时模式连接失败时保留实时模式并显示同步异常，连接恢复后通过 HTTP API 补读。已读状态以 HTTP API 为准。默认主题入口为 `/inbox` 与 `/inbox/system`。

`system.*` 只能由可信后端入口产生。前端只消费 SDK 返回的通知结构与安全 `action_url`，不得创建通知、解析 @提及、逐条查询 actor 或猜测跳转路由；完整边界见 [NOTIFICATIONS.md](NOTIFICATIONS.md)。

### 私信

```typescript
await client.messages.conversations()
await client.messages.list(conversationId, { cursor, limit })
await client.messages.send({
  recipient_id: userId,
  body,
  client_message_id: crypto.randomUUID(),
})
```

`client_message_id` 建议每次用户发送动作生成并在重试中复用，避免网络重试产生重复消息。服务端决定会话成员、block/privacy 范围；前端 403 不能当成空会话。默认主题入口为 `/messages`、`/messages/:conversationId`；`/messages/requests` 当前仍是明确的未开放占位，不要伪造请求分类数据。

### 他人主页、关注与粉丝

```typescript
const profile = await client.social.profile(handle)
await client.social.relation(profile.id)
await client.social.follow(profile.id)
await client.social.unfollow(profile.id)
await client.social.followers(profile.id, { cursor, limit })
await client.social.following(profile.id, { cursor, limit })
```

默认主题入口为 `/user/:handle`、`/followers`、`/following`。公开 profile 可无 Token 获取；relation 与关系列表需要登录。后端会拒绝自关注和重复边，前端仍应禁用当前用户的关注按钮并处理幂等反馈。

### 隐私、拉黑与举报

```typescript
await client.safety.preferences()
await client.safety.preferences_update({
  profile_visibility,
  messages_from,
  show_relations,
})
await client.safety.block(userId)
await client.safety.unblock(userId)
await client.safety.report({ resource_type, resource_id, reason, details })
```

默认主题隐私入口是 `/settings/privacy`。举报 `reason` 必须使用后端公开枚举；不要发送 `integration_test` 等私有字符串。举报按钮应从稳定资源 type/id 构造，服务端会解析目标并保存 evidence snapshot。

### 账号恢复与 Session 失效

```typescript
const challenge = await client.session.recovery_request({ account })
await client.session.recovery_reset({ challenge_id, code, password })
```

默认主题入口 `/auth/recovery`。恢复和密码修改成功后，旧 JWT 会因为 security version 变化而立即 401；清空本地 Token 并重新登录，不要尝试继续刷新旧会话。

### 管理端六个管理域

- 举报：`client.reports.listManaged/getManaged/transition`；
- 账号安全：`client.users.recovery/security/sessions/revokeSession/revokeSessions`；
- 投递：`client.operations.deliveries/retryDelivery/notificationStats`；
- 审计：`client.operations.auditLogs`；
- 运行时/升级：`runtime/migrations/runMigrations/upgrades/upgrade/createUpgrade/advanceUpgrade/approveUpgradeContract/recoverUpgrade`；
- 社区聚合：`client.community.insights`。

页面、capability 和 UX 边界见 `ADMIN-ARCHITECTURE.md`。所有高风险动作继续使用现有 guard、确认对话框和 notifier，不提交通用操作原因。

系统升级上传不是 JSON。必须构造仅包含 Release 文件的 `FormData`：

```typescript
const formData = new FormData()
formData.append('file', releaseZip)
const plan = await client.operations.createUpgrade(formData)
```

`advanceUpgrade` 每次 HTTP 调用最多推进一个 Upgrade Step，迁移步骤内部也最多推进一个 phase 单元或 backfill batch；前端应刷新返回的 plan/steps 并由操作者继续推进，不能假设一次调用完成升级。只有返回 waiting 且目标 Release 确实包含 Contract migration 时才展示独立批准动作。当前 `recoverUpgrade` 只恢复旧 Release pointer 并 reload/health-check，不会自动导入数据库 SQL 或恢复文件 ZIP；UI 不得把它命名为“整站数据回滚”。

## 深链与媒体

默认主题当前真实路由：

```text
/post/:cardId
/post/:cardId/thread/:commentId
/media/:cardId?index=1
```

评论深链加载目标评论及必要父评论，渲染后展开线程并自动定位；公开 `comments.get` 不会返回隐藏评论，回复只能挂到同一卡片且公开、未删除的父评论，删除根节点可能以 `deleted=true` 的安全占位维持线程。媒体查看器的 card id/index 用于页面上下文，卡片响应的有序 `media[]` 提供 `file_id/type/url/mime_type/size/width/height/duration_ms/poster_url/is_animated/position`；实际公共文件 URL 是后端稳定 `/media/:hash`，并支持本地媒体 `HEAD` 与单段 byte Range。不要暴露 storage driver path，也不要将私有文件拼成公开 URL。

Theme Gateway 对这些路径执行 History fallback。HTML、Bootstrap 与 `/_lc/theme-assets/<theme>/<release>/...` 必须来自同一 Host Binding/Release；前端不缓存跨 Release 的 assets base。

## 请求 scene 回归要求

以下无参/可选参数请求应直接调用，不附加占位字段：

```typescript
client.library.list()
client.library.history()
client.notifications.list()
client.notifications.unread()
client.notifications.readAll()
client.notifications.stream()
client.messages.conversations()
client.messages.list(id)
client.drafts.list()
client.safety.preferences()
```

非法请求仍应显示后端给出的具体字段错误：空消息只报 `body`，空已读只报 `ids`，非法举报只报举报 scene 字段。若又出现一个 scene 要求另一 scene 字段，问题属于后端契约链，不能在前端加兼容参数绕过。

## 验收命令

```bash
npm run check
npm run build:default-theme
npm run build:admin
```

涉及真实接口时，在一次性 localhost 数据库执行 SDK integration；涉及页面路由/交互时执行 Admin 与 Theme E2E。2026-08-20 基线：SDK integration 133 passed / 0 failed / 24 skipped（157 total），后端 39/39，默认主题 20 files / 113 tests 且 production build 通过，管理端 E2E 28 项通过（3 项认证场景、25 个固定/动态路径），主题真实 E2E 已覆盖入口回退、公开 API、深层路由、Bootstrap/Release 资源、登录、发布与评论。根 `npm run check` 同日 exit 0。

系统升级另有隔离 E2E：在临时复制的 Release source 中加入 E2E-only Contract migration，经真实签名 ZIP、JWT multipart 上传和 21 次单元推进，验证提前批准被拒绝、`contract_migrations` waiting、独立批准持久化、Contract SQL、激活、pointer、Release response header 与 health。当前生产迁移目录仍没有 Contract phase；恢复正向路径仍以专项状态机测试为证据，不应从本次 HTTP 演练推断数据库或文件自动恢复已经实现。

## 不要做

- 不手写与生成 Resource 平行的 API Service。
- 不直接编辑 OpenAPI、generated SDK、dist 或后端公开 SDK bundle。
- 不把可选 Worker/Redis/SSE 当页面可用性的前置条件。
- 不在管理端展示草稿正文、个人收藏明细、浏览轨迹或原始审计秘密。
- 不把 `record<string, unknown>` 猜成永久字段；后端 Resource 类型收紧后重新生成并迁移使用点。
