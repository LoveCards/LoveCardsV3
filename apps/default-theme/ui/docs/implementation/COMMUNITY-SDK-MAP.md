# 社区页面 UX 与 SDK 映射

版本：2026-08-21
范围：`apps/default-theme` 用户前台；Blueprint 05–09 的真实数据施工补充规范。

## 页面真源

| 页面 | 路由 | SDK | 核心状态 |
|---|---|---|---|
| 公开主页 | `/user/:uid` | `social.profile`、`cards.listPublicByUser`、`relation`、`follow`、`unfollow` | 访客、本人、公开卡片、空列表、已关注、未关注、私密/受限、404、网络错误 |
| 关系列表 | `/user/:uid/followers`、`following` | `social.followers`、`following` | 加载、空列表、权限不足、分页边界 |
| 草稿箱/编辑 | `/drafts`、`/compose?draft=:id` | `drafts.list/get/create/update/delete` | 空箱、发布页主动保存、恢复同一编辑器、保存失败、删除确认 |
| 本人内容选项卡 | `/me`（内容默认，Tab 显示总数）与 `/me?tab=likes|saved|history`（旧 `/me?tab=content`、`/me/library/{likes|saved|history}` 兼容） | `users.me`、`cards.listOwn`、`likes.list`、`library.list/remove/history/history_clear` | 同一主页壳内 loading、空列表、原资源失效、移除回滚、清空确认 |
| 本人关系列表 | `/me/following`、`/me/followers` | `users.me`、`social.following`、`social.followers` | 独立任务页；首次加载、空列表、网络/权限错误、Cursor 加载更多；移动端无 BottomNav |
| 获赞统计 | `/me`（资料卡只读统计） | `users.me` → `stats.received_likes` | 只读 aggregate；缺失显示 `—`；不得用 `likes.list`/`library.list` 推算收到的互动；旧 `/me/likes-and-saves` 重定向 |
| 私信（消息中心第一入口） | `/messages`、`/messages/:conversationId` | `messages.conversations/list/send` | 私信列表和会话独立于通知；空会话、发送中、失败重试、重复提交保护、受限 |
| 互动消息通知 | `/inbox` | `notifications.list/unread/read/readAll`，`category: 'engagement'` | 汇总 `message/social/interaction` 通知投影；未读、已读、空列表、加载更多失败、手动刷新 |
| 系统通知 | `/inbox/system` | `notifications.list/unread/read/readAll`，`category: 'system'` | `system.notice/security/moderation`；severity、不可访问资源、未读与批量已读 |
| 旧通知分类入口 | `/inbox/message`、`/inbox/social`、`/inbox/interaction` | 不请求数据 | 统一重定向到 `/inbox`；不保留旧页面、前端筛选或三类请求拼接 |
| 隐私 | `/settings/privacy` | `safety.preferences/preferences_update` | 加载、脏状态、保存、失败回滚、权限不足 |
| 安全操作 | 用户/内容操作菜单 | `safety.block/unblock/report` | 确认、提交中、成功、失败、重复提交保护 |
| 账号恢复 | `/auth/recovery` | `session.recovery_request/recovery_reset` | 识别、验证/重置、完成、过期、网络错误 |
| 内容深链 | `/post/:id/thread/:commentId`、`/media/:id` | Cards、Comments、Media 既有资源 | 先鉴权/加载资源，再定位；anchor 缺失与资源删除分开提示 |

## 共同交互规则

1. 页面只调用 `@lovecards/sdk`，不直接请求 URL。
2. collection 同时兼容纯数组与 `{ items, next_cursor, has_more }`；按稳定 `id` 去重。通知的 `next_cursor` 是不透明复合游标，只能原样传回同一分类请求，禁止解析、构造、跨分类复用或据此自行排序。
3. 401 交给全局会话失效处理；403 显示权限边界；404 显示资源已删除或不存在；状态 0 显示网络错误并保留重试入口。
4. 写操作期间锁定同一动作；乐观更新保留快照，失败后恢复。
5. 移动端使用 44px 触控目标；主页内容选项卡保持页面画布内双列 Compact 网格，并沿用首页 `9px` 内容 gutter；关注/粉丝列表作为独立任务页使用返回标题栏且不挂 BottomNav；获赞只在资料卡中展示，不生成内容表面或任务页。
6. 不支持的子能力保留在信息架构中，但必须不可交互并显示“暂未开放”，不得模拟成功。

## 公开主页卡片接口（第一阶段）

`/user/:uid` 只通过 SDK 调用 `cards.listPublicByUser(uid, { page, list_rows })`。服务端负责从 UID 解析用户并过滤 active 用户的公开卡片；前端不传 `user_id`，也不使用 number、展示昵称或 `@username` 拼接主页地址。404、403、网络错误和 200 空列表分别进入对应状态，卡片数据继续复用 `CardResource` 与 `normalizeSdkCard`。

## 通知分类、分页与同步策略

1. `/inbox` 首屏并行调用 `notifications.list({ category: 'engagement', cursor: undefined, limit })` 与 `notifications.unread({ category: 'engagement' })`；`/inbox/system` 使用相同流程并把 category 替换为 `system`。
2. “全部标为已读”调用 `notifications.readAll({ category: currentCategory })`；单条已读继续使用现有单条 SDK。顶部全局角标若表达全部通知未读数，单独调用 `notifications.unread()`，不带 category。
3. 加载更多只把服务端返回的 `next_cursor` 原样传回当前分类。不得解析或构造游标，不得跨分类复用，也不得分别请求 `message/social/interaction` 后在前端合并。
4. `engagement` 只存在于 `NotificationListCategory` 查询参数。每个返回项保留真实 `item.category`（`message/social/interaction/system`）与 `item.type`，呈现层不得重写分类。
5. `type=message` 是私信产生的通知投影；点击仍只消费服务端 `action_url`，私信正文、会话列表与发送继续由 Messages SDK 负责。
6. 定时同步必须沿用当前分类请求。Gateway signal 不承载通知快照，只表示数据可能更新；收到后必须沿用当前 category 调用 `notifications.list/unread` 回源。
7. 页面始终提供“刷新”操作；后台同步或加载更多失败不清空已有内容。actor/resource 状态、站内 `action_url` 校验和已读状态以最新服务端结果为准。

## 草稿保存策略

- 只有发布页的“存入草稿箱”动作调用 `create`；已有草稿使用“更新草稿”调用 `update`。
- 保存成功后 URL 使用 `/compose?draft=:id`，草稿箱和旧 `/drafts/:id` 链接都回到这套发布编辑器。
- 草稿 payload 保存发布类型、话题和有序媒体引用；恢复时不重复上传已存在媒体。
- 离开存在未保存内容的页面前给出浏览器确认；保存失败不清空正文、话题、文件或已恢复媒体。
- 后端没有版本号时不宣称自动解决冲突；页面只报告当前保存动作的成功或失败。
