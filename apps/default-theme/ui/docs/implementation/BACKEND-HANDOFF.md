# 后端协作需求 · 交互与媒体

> 这是给后端/SDK Agent 的契约清单，不是前端绕过权限的方案。前端已经按现有能力施工；只有下列能力完成后，UI 才会移除“暂未开放”或只读边界。

## P0 · @ 提及候选搜索（已接入）

1. 生成 SDK 提供 `users.searchMentionCandidates({ query, limit })`，对应登录用户可访问的 `GET /users/mention-candidates`；前端不手写 URL、不调用管理端用户列表，也不通过逐个 `profile(handle)` 猜候选。
2. `query` 为 1–12 个字符的 canonical username 前缀，`limit` 为 1–20；空/非法查询返回稳定 4xx，服务端不得因 `@` 空查询枚举用户。排序与分页结构由 SDK `ListResult<MentionCandidate>` 冻结。
3. 只返回 active、未注销且可被当前用户发现的 `{ username, avatar }`；private 用户、未关注的 followers 用户、当前用户本人以及任一方向的 block 均排除。响应不得暴露 `id`、状态、隐私字段或通知接收人。
4. 默认主题只把候选选择写成原始 `@username ` 文本；卡片正文、评论和回复照常调用现有创建/更新 SDK。MentionParser/Mentions 在服务端解析、去重、排除本人/屏蔽用户并发布通知；前端不提交 `mentioned_user_ids`，不创建通知。
5. SDK 未就绪、候选为空或搜索网络失败时，前端输入仍可按普通文本提交，并显示非阻塞的空/错误状态；不能伪造候选或把搜索失败当成发布失败。

## P0 · 评论线程

1. `POST /cards/:id/comments` 接受 `parent_id` 时，必须校验父评论属于同一张卡片、未删除且对当前用户可见；跨卡片 ID 返回稳定 4xx。
2. `GET /cards/:id/comments` 返回稳定的 `parent_id`、`created_at`、作者标识和状态；列表顺序至少保证根评论与回复可重建线程。
3. 删除根评论时保留可识别的关系占位，或提供 `deleted` 状态，避免回复变成无主评论。
4. 评论创建失败不能增加卡片 `comments` 计数；重复提交要有幂等/限流策略。
5. 评论正文长度与主题统一为 140 个字符（当前后端校验为 140）；若产品要放宽，需同步更新 SDK、验证器和主题，不要只改前端计数。
6. 普通 Card 只有顶层 `content` 正文，创建与更新统一限制为 1000 字；`data.title` 不再是正式写入或展示契约。字符计数规则由后端冻结并通过生成 SDK/OpenAPI 同步，前端不自行放宽。

## P0 · 卡片作者置顶评论

1. 已提供默认主题可用的用户侧生成 SDK，冻结为 `comments.pin(cardId, { comment_id })` 与 `comments.unpin(cardId)`；内部 HTTP 使用 `PUT /me/cards/:cardId/pinned-comment`（body `{ comment_id }`）和 `DELETE /me/cards/:cardId/pinned-comment`。前端不会调用管理端 `pinManaged/unpinManaged`，也不会手写 URL。
2. 服务端必须验证：当前用户是卡片作者、卡片可管理、目标评论属于该卡片且未删除、`parent_id === null`。无归属返回 403，卡片或评论不存在返回 404，回复尝试置顶返回稳定 422/400。
3. 一张卡片最多一个置顶评论。置顶新的一级评论时在同一事务中替换旧项；取消置顶必须幂等。`GET /cards/:id/comments` 返回真实 `is_pinned` 并固定置顶一级评论优先，其余排序规则保持不变。
4. 会话 capability 已增加 `comments.pin` 的 own scope。前端只在“live + 已登录 + 自己的卡片 + capability + SDK 方法存在”时显示菜单项。
5. 写接口返回 204 或更新后的置顶摘要均可，但必须写入 OpenAPI/契约生成源并重新生成 `packages/sdk` 与浏览器 SDK；不要只在后端增加未生成的方法。

## P0 · 已发布卡片完整编辑契约

1. `cards.getOwn(id)` 的 `topics[]` 必须返回全部 `{ id, name }`，`media[]` 按 `position` 稳定排序，并为每个可编辑媒体提供非空 `file_id`。历史记录若无法提供文件引用，应给出显式不可编辑状态，不能让前端凭 URL 猜文件 ID。
2. `cards.update(id, payload)` 接受 `tags: []` 清空话题、`media_file_ids: []` 清空媒体、`cover_file_id: 0` 清空旧封面；图文切换为视频或随笔时不得残留旧图片封面。
3. 修正 SDK `Card.tags` 类型与真实响应：当前 API 返回关系 ID 时应声明为 `number[]`；人类可读名称只来自 `topics[]`。创建/更新的 `tags` 输入类型也需与服务端实际支持的名称数组/ID 数组冻结一致，不能继续暴露模糊 `unknown[]`。

## 用户中心关系与互动二级页

1. `social.following` / `social.followers` 继续返回稳定的 `CursorPage`（`items`、`next_cursor`、`has_more`），关系行至少包含 `id`、真实 `username`/handle 和头像；前端只通过生成 SDK 调用。
2. 后端与 SDK 已冻结 `users.me().stats.received_likes`。该字段只统计当前用户公开且未删除卡片收到的点赞，不包含评论获赞、收藏或用户发出的点赞；前端只将它作为 `/me` 资料卡的只读数字展示。
3. 当前不提供收到的获赞明细资源。前端不得把 `likes.list()`（我点赞过的记录）、`library.list()`（我收藏的记录）或当前页卡片点赞数当作收到的互动数据，也不得手写请求 URL；历史 `/me/likes-and-saves` 深链由前端重定向回 `/me`。

## 当前范围 · 不提供投票能力

## 消息中心联合互动分类（契约已冻结）

1. 私信是消息中心第一入口，使用 `/messages`、`/messages/:conversationId` 和独立的 `messages.conversations/list/send`；顶部“消息”主入口指向 `/messages`。`type=message` 同时会作为通知投影进入联合互动列表，但通知页不得替代私信会话。
2. 通知二级分类只保留“互动消息”和“系统通知”：`/inbox` 调用 `notifications.list({ category: 'engagement', cursor, limit })`，`/inbox/system` 调用 `notifications.list({ category: 'system', cursor, limit })`。旧 `/inbox/message`、`/inbox/social`、`/inbox/interaction` 统一重定向到 `/inbox`，不保留第二套视图或兼容请求。
3. `NotificationListCategory` 包含查询专用值 `engagement`；`NotificationItem.category` 仍只返回真实的 `social | message | interaction | system`。渲染继续根据 `item.type` 与真实 `item.category`，不得把联合结果改写为 `interaction`。
4. `next_cursor` 是服务端生成的不透明复合游标。前端只原样回传，不解析、不拼接、不排序重建，也不通过 `message/social/interaction` 三次请求后合并当前页。
5. 分类页的未读与批量已读必须贯穿同一参数：`notifications.unread({ category })`、`notifications.readAll({ category })`。顶部全局消息角标需要全部通知数时调用 `notifications.unread()`，不得用当前页条目推算。
6. 单条已读继续调用现有 SDK；actor/resource 状态和 `action_url` 安全规则不变。前端不新增通知触发请求，不解析 `@username`，也不根据资源 ID 猜路由。

投票卡片和基于评论正文的选择兼容层已从当前产品范围移除。后端与 SDK 不需要为本阶段新增 Interaction 资源、选择结果或相关权限；前端也不会读取 `cards.data` 中的历史选择声明。

- `discussion` 继续复用普通评论资源和 `parent_id`，不承担结构化选择或结果统计。
- 历史 `data.kind` 未命中当前卡片注册表时由前端安全降级，不应改变服务端权限或数据归属。
- 评论正文始终按用户内容处理；不要把 marker、选项或结果写入评论来模拟独立资源。
- 未来若重新启动结构化互动，需要另立产品决策和契约评审；本文件不保留待施工的投票接口方案。

## P0 · 媒体响应

列表、热门、搜索、详情统一返回有序 `media[]`：

```json
{
  "file_id": 123,
  "type": "image|video",
  "url": "…",
  "mime_type": "image/jpeg",
  "size": 1024,
  "width": 1200,
  "height": 900,
  "duration_ms": null,
  "poster_url": null,
  "is_animated": false,
  "position": 1
}
```

`media[]` 是唯一权威展示来源；兼容期可保留 `cover/pictures`。创建/更新接受有序 `media_file_ids[]`，事务内验证所有权、`scene=card`、完成状态、MIME、数量和生命周期。视频另需海报、时长、正确 MIME 与 Range 206。

## P1 · 前端策略接口

建议提供 `GET /me/files/policy?scene=card|avatar`，返回 `allowed_mime_types/max_files/max_size/supports_video/supports_direct_upload`。前端当前按默认渠道将图片限制为 10MB、图文最多 9 个文件；后端策略和上传限流必须与之统一。头像上传固定提交 `scene=avatar`。

文件大小和类型拒绝应分别返回稳定的 `6001/413` 与 `6002/415`，上传失败返回 `6003`。当前存储驱动仍可能以 `9999/500` 返回“不支持的文件类型”或“文件大小超出限制”，前端只能依据已知文件文案降级显示，无法可靠区分所有场景。

默认本地存储策略不包含视频 MIME，而发布页存在视频形态。后端需要通过场景策略明确 `supports_video`；前端在取得该能力前不能把默认渠道是否支持视频当成稳定事实。
