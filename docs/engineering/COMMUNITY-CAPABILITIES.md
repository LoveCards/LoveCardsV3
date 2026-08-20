# 社区能力、数据归属与深链

本轮社区能力已落在独立 Platform 模块中，并复用 Auth、RBAC、Files、Eventing 和 Observability。`Platform.Community` Controller 是 HTTP 聚合适配器，不是新的“万能 Community Service”；业务分别由 Drafts、Library、Messaging、Notifications、Safety 和 Social 模块负责。

## 能力清单

| 能力 | 后端模块 / 数据 | API / SDK | 当前约束 |
| --- | --- | --- | --- |
| 草稿箱 | `Platform/Drafts`、`card_drafts` | `client.drafts.list/create/get/update/delete` | owner 过滤、软删除、updated/id cursor、version 字段 |
| 他人主页、关注/粉丝 | `Platform/Social`、`user_follows` | `profile/follow/unfollow/followers/following/relation` | handle 公开查找；禁止自关注和重复边；关系列表需认证 |
| 通知 | `Platform/Notifications`、`notifications` | `list/unread/read/readAll/stream` | recipient 归属、dedupe/read 状态；SSE 可回退刷新 |
| 私信 | `Platform/Messaging`、conversation 三表 | `conversations/list/send` | 会话成员范围、block/privacy 检查、client message 幂等 |
| 账号恢复 | `Platform/Auth`、`security_challenges`、`auth_sessions` | `session.recovery_request/recovery_reset` | 一次性挑战、过期/尝试次数；成功后 security version 增长并撤销旧 Session |
| 收藏与浏览历史 | `Platform/Library`、`library_items`/`view_history` | `list/save/remove/history/history_record/history_clear` | 用户+资源唯一；cursor；记录浏览累计次数 |
| 举报、隐私偏好、拉黑 | `Platform/Safety`、preferences/relations/reports/events | `preferences/preferences_update/report/block/unblock` | 举报目标解析、证据快照；关系与私信策略联动；治理操作留审计 |
| 媒体与评论深链 | Files public media + Theme SPA | `/media/:hash`、`/media/:cardId?index=n`、`/post/:cardId/thread/:commentId` | 先做可见性/权限，再返回内容；评论缺失显示明确边界并不错误定位 |

## 请求契约与场景隔离

社区 Controller 的所有输入必须经 `BaseController::validated()` 和路由声明的 Validator scene。`Community` Validator 的 `$all_scene` 是字段白名单，`requestSchemas()` 是 OpenAPI/SDK schema；共享 `$rule` 不得残留跨 scene 的 `require`。

当前输入边界：

- `GET /me/library`、`/me/history`、`/me/drafts`：仅可选 `cursor, limit`；
- `DELETE /me/library`：必填 query `resource_type, resource_id`；生成方法为 `library.remove(params)`；
- `GET /me/notifications`：可选 `cursor, limit, unread_only`；unread/read-all 无业务参数；
- `GET /me/notifications/stream`：仅可选 `after`；响应是 `text/event-stream` raw；
- `POST /me/notifications/read`：只要求 `ids`；
- `GET /me/conversations`：无业务参数；消息列表只接收路径 `id` 和可选 cursor/limit；
- `POST /me/messages`：必填 `recipient_id, body`，可选 `client_message_id`；
- 草稿 create/update：只接受 `title, content, payload`；
- 隐私读取无业务参数，更新只接受隐私字段；
- `POST /reports`：只在该 scene 要求 `resource_type, resource_id, reason`，`details` 可选。

GET/DELETE 的非路径参数由 SDK 作为 query 发送，POST/PATCH 作为 JSON body；前端不得增加空兼容字段来规避后端 scene 错误。

## 认证、权限与资源范围

认证只证明“是谁”；业务模块仍在查询/事务中执行 owner、recipient、conversation membership 和 block/privacy 范围：

- 草稿、收藏、历史、通知只返回当前 uid 数据；ID 猜测不能越权。
- 私信读取要求当前用户是会话成员；发送前验证收件人、拉黑关系与 `messages_from` 偏好。
- 公开主页只暴露 Public Resource 白名单；邮箱、电话、安全版本和恢复状态不进入公开响应。
- 管理员查看社区聚合只返回计数，不返回草稿正文、个人收藏明细或浏览轨迹。
- 举报详情只给 `reports.read`；状态转换另需 `reports.manage`，原因进入 report event 与审计。

## 通知与事件一致性

关注和私信先完成业务事务，再通过 MySQL Outbox 投递通知；通知自身用 dedupe key 幂等。最小环境由请求预算推进，完整环境由 Worker 推进。SSE 是读取通知的增强通道，不是通知事实来源；断线后客户端使用 `after`/未读接口恢复。

## 深链协议

### 公开媒体文件

上传文件获得不可变公开 `hash`。只有 `is_public`、正常状态、上传完成、未软删除且没有 purge 状态的文件可由后端 `GET /media/:hash` 解析；Controller 返回 redirect/流响应时附稳定 ETag。本地文件支持 `HEAD`、单段 byte `Range`、206/416 和 `Accept-Ranges`，远端驱动仍返回受校验的稳定 URL。私有文件不能因为知道 hash 而公开，卡片隐藏、删除、恢复或审核状态变化会同步引用公开性。

### 媒体查看器

默认主题的 `/media/:cardId?index=1` 是可分享的 SPA 查看器，按卡片公开契约加载媒体列表并限制 index。底层单文件 URL仍使用 `/media/:hash`，因此 UI 深链与存储稳定标识职责分离。

### 评论定位

默认主题支持 `/post/:cardId/thread/:commentId`。`loadDetail` 在目标评论不在首屏分页时调用 `comments.get(commentId)`，必要时继续取父评论，合并去重后再渲染。公开详情只返回已发布评论；创建回复时后端在插入前锁定并校验父评论属于同一卡片、未删除且公开，跨卡或隐藏父级会被拒绝。删除根评论后可返回安全占位以维持公开回复线程。`CommentThread` 展开目标根线程、给目标节点 `id=comment-<id>`，并在首屏完成后 `scrollIntoView`；尊重 reduced-motion。评论不可见、已删除或不属于该卡片时展示边界提示，不滚动到错误元素。

### Theme Gateway

主题路由刷新由 Theme Gateway 的 History fallback 返回同一 Release 的 `index.html`，Bootstrap 中提供 `/api`、theme/release、assets base 与配置。`/api`、`/media/:hash` 和 `/_lc/*` 在 SPA fallback 之前由专用路由处理。

## 为什么不做一张通用互动表

点赞、收藏、历史、关注、拉黑和举报只有“用户对资源做动作”的表面相似，核心约束不同：点赞当前是 card-only；收藏按用户+资源幂等；历史需要 view count/last viewed；关注是双用户图；拉黑影响授权；举报有状态机、证据与审计。因此只共享 `ResourceRef` 等值对象和权限/错误协议，表结构按查询、唯一性和生命周期拆分。
