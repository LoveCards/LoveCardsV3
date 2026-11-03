# LoveCards 页面设计矩阵

版本：2026-08-22
状态：设计系统规范已冻结；单个 Blueprint 是否可施工以 [`BLUEPRINTS.md`](./BLUEPRINTS.md) 的 `canonical/needs-sync/review-required` 状态为准。SDK 已支持的能力真实接入，未支持能力诚实显示边界。

## Status legend

- `Foundation`：已有可复用视觉基底，但不是完整页面。
- `Blueprint`：需要桌面与移动参考图。
- `State`：共享页面模板，只需补状态板。
- `Future`：当前只定义扩展接口。

## IA depth × Mobile shell

本文件的 Level 只表达信息架构深度，不直接决定移动端是否显示 BottomNav。移动壳以 [`NAVIGATION.md`](../specifications/NAVIGATION.md) 的 `standard/task/immersive/gateway` 为准。

| 路由家族 | IA depth | Mobile shell | BottomNav | 返回 fallback |
|---|---|---|---|---|
| `/me` 与本人资产 Tab | 一级 | `standard` | 显示 | — |
| 本人/他人关注与粉丝 | 二级 | `task` | 隐藏 | 本人或对应公开主页 |
| `/compose`、草稿恢复、已发布编辑 | 一级入口 / 深度任务 | `immersive` | 隐藏 | 来源页；缺失时 `/` |
| `/drafts` | 二级 | `task` | 隐藏 | `/compose` |
| `/settings` | 二级 | `task` | 隐藏 | `/me` |
| `/settings/*` | 三级 | `task` | 隐藏 | `/settings` |
| `/messages/:conversationId` | 二级 | `task` | 隐藏 | `/messages` |
| `/auth/*` | 认证流程 | `gateway` | 隐藏 | 来源页；缺失时 `/` |

## Level 1 — 全局入口

| ID | 页面 | Route | 桌面结构 | 移动结构 | 必备状态 | 交付 |
|---|---|---|---|---|---|---|
| L1-01 | 推荐首页 | `/` | 顶栏 + 频道 + 多列流 + 右栏 | 顶栏 + 双列流 + 底栏 | loading/empty/error/guest | Foundation → Blueprint 01 |
| L1-02 | 发现 | `/explore` | 搜索 + 趋势 + 话题 + 创作者 | 搜索入口 + 分区纵向流 | recent/no-history/no-result | Blueprint 02 |
| L1-03 | 发布 / 编辑 | `/compose`、`/compose?draft=<id>`、`/compose?edit=<cardId>` | 显式选择随笔/图文/视频 + 单编辑轴 | `immersive`：局部任务顶栏 + 单轴编辑 + 固定发布栏，无 BottomNav；已发布编辑不显示草稿动作 | loading/404/403/uploading/field-error/task-error/dirty | Blueprint 04 |
| L1-04 | 消息 | `/messages` | 统一消息中心标题 + 私信双栏工作区 | 统一标题/一级 Tab + 私信列表 | loading/empty/error/permission | Blueprint 07 |
| L1-05 | 我的 | `/me` | 个人封面 + 同页内容标签 + 统一卡片流 | 全宽身份头 + 可横向滑动标签 + 双列卡片流 | loading/empty/error/private/editing | Blueprint 05 |
| L1-06 | 交互表面 | shared | 移动壳层 + Typeahead + 线程 + Dialog/Modal + 模式注册 | 顶部搜索 + 底部我的 + 内联回复 / Sheet | focus/loading/empty/error | Blueprint 10 |

## Level 2 — 核心页面

| ID | 页面 | Route | 页面家族 | 必备状态 | 交付 |
|---|---|---|---|---|---|
| L2-01 | 关注流 | `/?feed=following` | Feed | no-following/new-post | Blueprint 01 |
| L2-02 | 最新频道 | `/?channel=latest` | Feed | channel-empty/filter | Blueprint 01 states |
| L2-03 | 帖子详情 | `/post/:id` | Content detail | deleted/reviewing/restricted | Blueprint 03 |
| L2-04 | 评论线程 | `/post/:id/thread/:commentId` | Thread | collapsed/deleted/error | Blueprint 03 |
| L2-05 | 媒体查看器 | `/media/:id` | Focus | image/gallery/video/GIF | Blueprint 03 variant |
| L2-06 | 搜索首页 | `/search` | Explore | recent/trending/no-history | Blueprint 02 |
| L2-07 | 搜索结果 | `/search/:scope?q=` | Results | all/posts/users/topics/media/no-result | Blueprint 02 |
| L2-08 | 话题详情 | `/topic/:slug` | Topic | following/rules/empty | Blueprint 02 variant |
| L2-09 | 草稿箱 | `/drafts` | Compose secondary · Editor 单轴连续恢复列表 | loading/error/delete-error/empty/restoring；手机无 BottomNav | Blueprint 04B |
| L2-10 | 他人主页 | `/user/:uid` | Profile | followed/private/blocked/not-found | Blueprint 05 |
| L2-10a | 本人主页与内容 | `/me` | Profile in-page tabs | content/likes/saved/history/loading/error | Blueprint 05 |
| L2-10b | 本人关注列表 | `/me/following` | Relationship secondary · mobile `task` | loading/empty/error/load-more | Blueprint 06B |
| L2-10c | 本人粉丝列表 | `/me/followers` | Relationship secondary · mobile `task` | loading/empty/error/load-more | Blueprint 06B |
| L2-10d | 获赞统计（同页展示） | `/me` | Profile stat (read-only) | aggregate/missing | Blueprint 05 |
| L2-11 | 编辑资料（历史重定向） | `/me/edit` → `/settings/profile` | Full profile task; quick edits remain on `/me` | loading/validation/error/success | Blueprint 05/06 |
| L2-12 | 关注列表 | `/user/:uid/following` | Relationship secondary · mobile `task` | empty/search/remove | Blueprint 06B |
| L2-13 | 粉丝列表 | `/user/:uid/followers` | Relationship secondary · mobile `task` | empty/search/follow-back | Blueprint 06B |
| L2-14 | 通知中心 · 互动消息 | `/inbox` | Notification list | engagement/unread/empty/error/load-more | Blueprint 07 |
| L2-14a | 通知中心 · 系统通知 | `/inbox/system` | Notification list | system/severity/unread/empty/error/load-more | Blueprint 07 |
| L2-14b | 旧通知分类（重定向） | `/inbox/message`、`/inbox/social`、`/inbox/interaction` | Redirect | redirect-only | Blueprint 07 |
| L2-15 | 私信列表 | `/messages` | Messaging workspace | loading/empty/error/permission | Blueprint 07 |
| L2-16 | 聊天会话 | `/messages/:conversationId` | Messaging secondary task | loading/empty/error/sending/failed/permission；手机隐藏 BottomNav | Blueprint 07 |
| L2-18 | 个人内容标签（兼容入口） | `/me?tab=likes|saved|history`、`/me/library/{likes|saved|history}` | Profile in-page tab | loading/empty/removed/clear | Blueprint 05 |
| L2-19 | 设置总览与设置壳 | `/settings`、`/settings/*` | Settings stack · mobile `task` | overview/category/loading/error/permission/changed；整个设置栈无 BottomNav | Blueprint 08 |
| L2-20 | 登录/注册 | `/auth/:mode` | Auth | validation/loading/error | Blueprint 09 |

## Level 3 — 深层任务与状态

| ID | 任务 | Surface | 必备状态 | 交付 |
|---|---|---|---|---|
| L3-01 | 回复评论 | inline composer / sheet | draft/sending/failed | Blueprint 03 state |
| L3-02 | 分享内容 | popover / bottom sheet | link/copied/system-share | Shared pattern |
| L3-03 | 举报内容/用户 | step dialog / page | reason/details/sent | Blueprint 06 state |
| L3-04 | 屏蔽/取消屏蔽 | confirm dialog | blocked/undo | Shared pattern |
| L3-05 | 编辑/删除帖子 | action menu / composer | dirty/delete-confirm | Blueprint 04 state |
| L3-06 | 头像编辑 | 主页头像 → task dialog/mobile sheet | default/selected/type/size/unreadable/uploading/network/permission/success | Blueprint 06A |
| L3-07 | 账号安全 | `/settings/security` | mobile `task`；返回 `/settings`；password/email/loading/error/permission | Blueprint 08 variant |
| L3-07a | 登录与会话 | `/settings/session` | mobile `task`；返回 `/settings`；authenticated/visitor/current-session/logout/error；多设备待契约 | Blueprint 08 variant |
| L3-08 | 隐私 | `/settings/privacy` | mobile `task`；返回 `/settings`；audience/loading/error/changed/blocked-capability | Blueprint 08 variant |
| L3-09 | 通知偏好（契约待补） | 无正式路由 | capability-pending；不进入当前导航 | Blueprint 08 boundary |
| L3-10 | 外观与无障碍 | `/settings/appearance` | mobile `task`；返回 `/settings`；theme/motion | Blueprint 08 variant |
| L3-11 | 消息请求与权限（暂未开放） | `/messages/requests` | capability-pending/不可交互 | Blueprint 07C 仅作后续设计参考 |
| L3-12 | 账号恢复 | `/auth/recovery` | identify/code/reset/done | Blueprint 09 state |
| L3-13 | 编辑评论 | `/post/:id` | form/dirty/saving/error/long-content | Blueprint 03 state + Dialogs |

## Shared system-state boards

| Board | 覆盖 |
|---|---|
| S-01 | Skeleton：Feed、Profile、Thread、Inbox、Settings |
| S-02 | Empty：新用户、无关注、无收藏、无通知、无搜索结果 |
| S-03 | Error：网络、媒体失败、提交失败、权限不足、内容不可用 |
| S-04 | Permission：游客、私密账号、被屏蔽、未成年人/敏感内容门槛 |
| S-05 | Responsive：320/390/768/1024/1440 页面骨架 |
| S-06 | Theme：亮色、暗色、系统跟随和 Portal 浮层 |

## Reference-board sequence

1. Blueprint 01：全局 Shell + 首页信息流。
2. Blueprint 02：发现 + 搜索 + 话题。
3. Blueprint 03：内容详情 + 评论线程 + 媒体查看器。
4. Blueprint 04：发布器 + 草稿。
5. Blueprint 05：本人/他人用户中心。
6. Blueprint 06：编辑资料 + 关系链 + 举报。
7. Blueprint 07：私信 + 聊天 + 通知。
8. Blueprint 08：设置总览、账号安全、隐私、外观和当前会话；个人资产继续归 Blueprint 05，契约待补能力只在边界板登记。
9. Blueprint 09：登录 + 注册 + 账号恢复。
10. Blueprint 10：交互表面、Dialog/Modal、移动壳层、卡片模式注册表和评论线程。
11. System Boards：共享状态、响应式和主题。

## Design completion gate

系统架构、组件用途、反馈、表单上传、布局和导航已经通过体系化冻结。页面施工不得以组件库默认外观、通用页面模板或 SDK 测试界面替代规范；只有注册表标记为 `canonical` 的 Blueprint 可直接作为视觉参考，其他状态必须先同步。

## Scope audit result

社区核心页面无缺页。当前阶段明确不展开以下未来模块：

- C2C 商城的商品发布、商品详情、购物车、订单、支付、售后和评价链路。
- 完整圈子/社区治理的创建、成员管理、版主管理、提案与申诉工作台。
- 创作者商业中心的收益、合作、推广、结算与商业数据后台。

这些模块只继承现有视觉语义与扩展接口，不计入 Blueprint 01–09 的设计完成门槛。
