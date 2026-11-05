# SDK 能力核查矩阵 · 2026-08-25

核查来源：`packages/sdk/src/client.ts`、`packages/sdk/src/generated/resources/*`、OpenAPI 与 Route Manifest。本文件只记录用户前端可调用的能力，不把 admin resource 视为用户端接口。设置归属与缺口优先级见 [`USER-SELF-SERVICE-MATRIX.md`](../implementation/USER-SELF-SERVICE-MATRIX.md)。

## 已有接口，可接入

- Session：登录、注册、游客、状态检查、登出、认证策略与验证码触发
- Cards：公开/热门/个人列表、搜索、详情、发布、编辑、删除、点赞、取消点赞
- Comments：帖子评论列表、评论详情、创建、编辑、删除与当前用户评论；管理端已有置顶能力，但默认主题所需的作者侧置顶 SDK 尚未提供
- Users：仅当前用户资料、资料更新、密码、邮箱验证码与邮箱更新
- Files：当前用户文件列表、上传、直传、确认、读取与删除
- Likes：当前用户喜欢列表、取消喜欢；点赞动作由 Cards resource 提供
- Tags：公开标签、当前用户标签及个人标签增删改
- Social：公开主页、关注/取消关注、关系状态、粉丝与关注列表
- Drafts：草稿列表、读取、新建、更新与删除
- Library：收藏、新增/移除收藏、浏览历史与清空历史
- Notifications：列表、未读数、单条/批量已读与通知流快照
- Messages：会话列表、会话消息与带客户端稳定 ID 的发送
- Safety：隐私偏好、拉黑/解除拉黑与举报
- Recovery：账号恢复申请与密码重置

## 本主题已接入

- 鉴权：登录、注册、游客会话、会话恢复与退出（`/auth/*`、全局会话状态）
- 内容闭环：发布、图片上传、详情、点赞、编辑、删除（`/compose`、`/post/:id`）
- 评论闭环：列表、创建、编辑、删除（`/post/:id`）
- 个人中心：资料读取与更新、我的内容、喜欢列表（`/me/*`）；评论参与仅发生在内容详情页
- 账户设置：密码更新、邮箱验证码与邮箱更新、退出当前会话（`/settings/*`）
- 社区关系：公开主页、关注状态、关注/取消关注、粉丝与关注列表（`/user/*`）
- 个人资产：草稿、收藏、浏览历史与清理（`/drafts*`、`/me/library/*`）
- 消息中心：私信通过 `messages.conversations/list/send` 独立接入（`/messages*`）；通知通过 `notifications.list/unread/read/readAll` 接入互动消息与系统通知（`/inbox`、`/inbox/system`）
- 安全与恢复：隐私偏好、拉黑、举报和账号恢复（`/settings/privacy`、`/auth/recovery`）

## 当前 SDK 契约限制

- 默认主题不得调用管理端 `comments.pinManaged/unpinManaged`。卡片作者置顶一级评论使用已冻结的 SDK `comments.pin(cardId, { comment_id })` / `comments.unpin(cardId)`，并由 `can('comments.pin')` 控制作者范围写操作。
- generated SDK 已为 `drafts.list`、`library.list`、`library.history`、`notifications.list`、`messages.list`、`social.followers` 和 `social.following` 暴露 `cursor / limit` 参数；这些 collection 仍返回 `items / next_cursor / has_more`，主题必须通过 SDK 传递分页参数，不得绕过 SDK 直连 API。通知 `next_cursor` 是不透明复合游标，只能原样回传同一 category，禁止解析或自行构造。
- `NotificationListCategory` 支持查询专用值 `engagement`。`/inbox` 使用 `notifications.list/unread/readAll({ category: 'engagement' })`，`/inbox/system` 使用 `category: 'system'`；`NotificationItem.category` 仍保持 `social | message | interaction | system`，不得被呈现层改写。
- 私信、关注、点赞与评论等用户事件在 `engagement` 通知中联合分页；禁止前端分别请求 `message/social/interaction` 后拼接。旧 `/inbox/message`、`/inbox/social`、`/inbox/interaction` 只做路由重定向。
- 顶部全局角标需要全部通知未读数时调用 `notifications.unread()` 无参数；通知页的未读与全部已读始终传当前 category。不得按当前页条目推算未读总数。
- `messages.conversations` 当前没有 `cursor / limit` 参数，主题只能消费会话列表接口当前返回的集合；不要在设计文档或页面中假设会话列表已经可分页。
- `notifications.streamTicket()` 只获取短期 Gateway ticket。主题端用 ticket 建立可取消的 EventSource；Gateway signal 不承载通知快照，收到后按当前 category 调用 `notifications.list/unread` 回源；失败或不可用时使用同分类定时刷新，并提供手动刷新。
- 私信发送使用 `recipient_id + body + client_message_id`。会话详情必须从会话/消息上下文得到对方用户 ID；无法确定收件人时禁用发送并解释原因。
- `safety.listBlocked({ cursor, limit, query })` 返回 `CursorPage` 的 `data`、不透明 `next_cursor` 和 `has_more`；设置页支持搜索、加载更多和解除拉黑。
- `session.listSessions/revokeSession/revokeOthers` 是普通用户自助会话接口；session id 只作为不透明字符串传递。
- 当前没有通知接收偏好、账号停用/注销、数据导出、手机号修改、2FA、Passkey 或恢复代码的普通用户契约；这些能力不得以开关、数量或可点击入口进入正式设置页。
- `CurrentUser` / `users.updateMe` 已冻结 `bio`、`location` 字段；本人主页提供 Unicode 长度校验和就地编辑。
- `safety.preferences()` 仍返回 `Record<string, unknown>`，且更新方法的三个字段虽标可选，服务端未传字段可能恢复默认值。主题在契约修正前必须读取后完整提交 `profile_visibility/messages_from/show_relations`，不能实现单字段即时保存。
- `users.emailCaptcha()` 的响应和 `users.updateEmail()` 的验证码必填性仍需强类型化。UI 可以继续使用现有完整流程，但不能根据松散 SDK 类型省略验证码。
- 当前没有按公开用户读取其 Cards 的专用接口；他人主页展示真实资料与关系信息，内容区显示明确能力边界。

所有写操作必须有进行中、成功、失败和重复提交保护；乐观更新失败必须回滚。服务层不得回退为 `{ preview: true }` 后让页面显示成功。
