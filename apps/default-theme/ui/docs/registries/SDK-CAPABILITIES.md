# SDK 能力核查矩阵 · 2026-08-18

核查来源：`packages/sdk/src/client.ts`、`packages/sdk/src/generated/resources/*`、OpenAPI 与 Route Manifest。本文件只记录用户前端可调用的能力，不把 admin resource 视为用户端接口。

## 已有接口，可接入

- Session：登录、注册、游客、状态检查、登出、认证策略与验证码触发
- Cards：公开/热门/个人列表、搜索、详情、发布、编辑、删除、点赞、取消点赞
- Comments：帖子评论列表、评论详情、创建、编辑、删除与当前用户评论
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
- 个人中心：资料读取与更新、我的内容、喜欢列表、我的评论（`/me/*`）
- 账户设置：密码更新、邮箱验证码与邮箱更新、退出登录（`/settings`）
- 社区关系：公开主页、关注状态、关注/取消关注、粉丝与关注列表（`/user/*`）
- 个人资产：草稿、收藏、浏览历史与清理（`/drafts*`、`/me/library/*`）
- 收件箱：通知、未读同步、已读操作、会话和消息发送（`/inbox*`、`/messages*`）
- 安全与恢复：隐私偏好、拉黑、举报和账号恢复（`/settings/privacy`、`/auth/recovery`）

## 当前 SDK 契约限制

- generated SDK 已为 `drafts.list`、`library.list`、`library.history`、`notifications.list`、`messages.list`、`social.followers` 和 `social.following` 暴露 `cursor / limit` 参数；这些 collection 仍返回 `items / next_cursor / has_more`，主题必须通过 SDK 传递分页参数，不得绕过 SDK 直连 API。
- `messages.conversations` 当前没有 `cursor / limit` 参数，主题只能消费会话列表接口当前返回的集合；不要在设计文档或页面中假设会话列表已经可分页。
- `notifications.stream()` 由 SDK 发起，当前服务端返回单次 SSE 快照而非永久连接。主题先尝试解析该快照，失败或不可用时自动降级为 SDK 定时刷新，并提供手动刷新。
- 私信发送使用 `recipient_id + body + client_message_id`。会话详情必须从会话/消息上下文得到对方用户 ID；无法确定收件人时禁用发送并解释原因。
- 当前没有黑名单列表读取接口，只有拉黑与解除拉黑写操作。隐私页不能伪造黑名单列表。
- 当前没有按公开用户读取其 Cards 的专用接口；他人主页展示真实资料与关系信息，内容区显示明确能力边界。

所有写操作必须有进行中、成功、失败和重复提交保护；乐观更新失败必须回滚。服务层不得回退为 `{ preview: true }` 后让页面显示成功。
