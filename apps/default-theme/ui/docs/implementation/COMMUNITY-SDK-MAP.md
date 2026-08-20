# 社区页面 UX 与 SDK 映射

版本：2026-08-18
范围：`apps/default-theme` 用户前台；Blueprint 05–09 的真实数据施工补充规范。

## 页面真源

| 页面 | 路由 | SDK | 核心状态 |
|---|---|---|---|
| 公开主页 | `/user/:handle` | `social.profile`、`relation`、`follow`、`unfollow` | 访客、本人、已关注、未关注、私密/受限、404、网络错误 |
| 关系列表 | `/user/:handle/followers`、`following` | `social.followers`、`following` | 加载、空列表、权限不足、分页边界 |
| 草稿箱/编辑 | `/drafts`、`/compose?draft=:id` | `drafts.list/get/create/update/delete` | 空箱、发布页主动保存、恢复同一编辑器、保存失败、删除确认 |
| 收藏/历史 | `/me/library/saved`、`history` | `library.list/remove/history/history_clear` | 空列表、原资源失效、移除回滚、清空确认 |
| 通知 | `/inbox`、`/inbox/notifications` | `notifications.list/unread/read/readAll/stream` | 未读、已读、空列表、流降级、手动刷新 |
| 私信 | `/messages`、`/messages/:conversationId` | `messages.conversations/list/send` | 空会话、发送中、失败重试、重复提交保护、受限 |
| 隐私 | `/settings/privacy` | `safety.preferences/preferences_update` | 加载、脏状态、保存、失败回滚、权限不足 |
| 安全操作 | 用户/内容操作菜单 | `safety.block/unblock/report` | 确认、提交中、成功、失败、重复提交保护 |
| 账号恢复 | `/auth/recovery` | `session.recovery_request/recovery_reset` | 识别、验证/重置、完成、过期、网络错误 |
| 内容深链 | `/post/:id/thread/:commentId`、`/media/:id` | Cards、Comments、Media 既有资源 | 先鉴权/加载资源，再定位；anchor 缺失与资源删除分开提示 |

## 共同交互规则

1. 页面只调用 `@lovecards/sdk`，不直接请求 URL。
2. collection 同时兼容纯数组与 `{ items, next_cursor, has_more }`；按稳定 `id` 去重。
3. 401 交给全局会话失效处理；403 显示权限边界；404 显示资源已删除或不存在；状态 0 显示网络错误并保留重试入口。
4. 写操作期间锁定同一动作；乐观更新保留快照，失败后恢复。
5. 移动端使用单列任务流与 44px 触控目标；桌面端才展开双栏或三栏信息架构。
6. 不支持的子能力保留在信息架构中，但必须不可交互并显示“暂未开放”，不得模拟成功。

## 通知实时策略

1. 首屏并行读取列表与未读数。
2. 通过 SDK 调用 `notifications.stream()` 尝试读取服务器通知快照。
3. 快照不可解析、请求失败或页面隐藏时，改用 SDK 列表/未读轮询；页面重新可见时立即刷新。
4. 任何来源均按通知稳定 ID 合并，较新的记录置顶；已读状态以最新服务端结果为准。
5. 页面始终提供“刷新”操作，自动同步失败不阻断已有内容阅读。

## 草稿保存策略

- 只有发布页的“存入草稿箱”动作调用 `create`；已有草稿使用“更新草稿”调用 `update`。
- 保存成功后 URL 使用 `/compose?draft=:id`，草稿箱和旧 `/drafts/:id` 链接都回到这套发布编辑器。
- 草稿 payload 保存发布类型、话题和有序媒体引用；恢复时不重复上传已存在媒体。
- 离开存在未保存内容的页面前给出浏览器确认；保存失败不清空正文、话题、文件或已恢复媒体。
- 后端没有版本号时不宣称自动解决冲突；页面只报告当前保存动作的成功或失败。
