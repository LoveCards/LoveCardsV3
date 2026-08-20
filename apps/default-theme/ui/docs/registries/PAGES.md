# LoveCards 页面设计矩阵

版本：2026-08-16
状态：Blueprint 01–10 已冻结，作为 React 产品前端的视觉与结构真源。SDK 已支持的能力真实接入；未支持能力显示“暂未开放”。C2C 仅冻结扩展入口和视觉衔接，不进入交易全链路。

## Status legend

- `Foundation`：已有可复用视觉基底，但不是完整页面。
- `Blueprint`：需要桌面与移动参考图。
- `State`：共享页面模板，只需补状态板。
- `Future`：当前只定义扩展接口。

## Level 1 — 全局入口

| ID | 页面 | Route | 桌面结构 | 移动结构 | 必备状态 | 交付 |
|---|---|---|---|---|---|---|
| L1-01 | 推荐首页 | `/` | 顶栏 + 频道 + 多列流 + 右栏 | 顶栏 + 双列流 + 底栏 | loading/empty/error/guest | Foundation → Blueprint 01 |
| L1-02 | 发现 | `/explore` | 搜索 + 趋势 + 话题 + 创作者 | 搜索入口 + 分区纵向流 | recent/no-history/no-result | Blueprint 02 |
| L1-03 | 发布 | `/compose` | 自动识别内容形态 + 内联媒体工作区 | 全屏堆叠编辑 + 固定发布栏 | draft/uploading/error/review | Blueprint 04 |
| L1-04 | 消息 | `/inbox` | 双栏消息工作区 | 分类列表 | unread/empty/error/permission | Blueprint 07 |
| L1-05 | 我的 | `/me` | 个人封面 + 标签页 + 内容流 | 紧凑身份头 + 标签页 | empty/private/editing | Blueprint 05 |
| L1-06 | 交互表面 | shared | 移动壳层 + Typeahead + 线程 + Dialog/Modal + 模式注册 | 顶部搜索 + 底部我的 + 内联回复 / Sheet | focus/loading/empty/error | Blueprint 10 |

## Level 2 — 核心页面

| ID | 页面 | Route | 页面家族 | 必备状态 | 交付 |
|---|---|---|---|---|---|
| L2-01 | 关注流 | `/?feed=following` | Feed | no-following/new-post | Blueprint 01 |
| L2-02 | 最新/媒体/讨论频道 | `/?channel=:type` | Feed | channel-empty/filter | Blueprint 01 states |
| L2-03 | 帖子详情 | `/post/:id` | Content detail | deleted/reviewing/restricted | Blueprint 03 |
| L2-04 | 评论线程 | `/post/:id/thread/:commentId` | Thread | collapsed/deleted/error | Blueprint 03 |
| L2-05 | 媒体查看器 | `/media/:id` | Focus | image/gallery/video/GIF | Blueprint 03 variant |
| L2-06 | 搜索首页 | `/search` | Explore | recent/trending/no-history | Blueprint 02 |
| L2-07 | 搜索结果 | `/search/:scope?q=` | Results | all/posts/users/topics/media/no-result | Blueprint 02 |
| L2-08 | 话题详情 | `/topic/:slug` | Topic | following/rules/empty | Blueprint 02 variant |
| L2-09 | 草稿箱 | `/drafts` | Compose | autosaved/conflict/empty | Blueprint 04 state |
| L2-10 | 他人主页 | `/user/:handle` | Profile | followed/private/blocked/not-found | Blueprint 05 |
| L2-11 | 编辑资料 | `/me/edit` | Form | dirty/saving/error/success | Blueprint 06 |
| L2-12 | 关注列表 | `/user/:handle/following` | Relationship | empty/search/remove | Blueprint 06 |
| L2-13 | 粉丝列表 | `/user/:handle/followers` | Relationship | empty/search/follow-back | Blueprint 06 |
| L2-14 | 通知中心 | `/inbox/notifications` | Inbox | all/mentions/replies/system | Blueprint 07 |
| L2-15 | 私信列表 | `/messages` | Messaging | unread/request/empty | Blueprint 07 |
| L2-16 | 聊天会话 | `/messages/:conversationId` | Messaging | sending/failed/blocked/request | Blueprint 07 |
| L2-17 | 收藏 | `/me/library/saved` | Library | folders/empty/removed | Blueprint 08 |
| L2-18 | 喜欢 | `/me/library/likes` | Library | private/empty | Blueprint 08 |
| L2-19 | 浏览历史 | `/me/library/history` | Library | grouped/clear/empty | Blueprint 08 |
| L2-20 | 设置首页 | `/settings` | Settings | search/changed | Blueprint 08 |
| L2-21 | 登录/注册 | `/auth/:mode` | Auth | validation/loading/error | Blueprint 09 |

## Level 3 — 深层任务与状态

| ID | 任务 | Surface | 必备状态 | 交付 |
|---|---|---|---|---|
| L3-01 | 回复评论 | inline composer / sheet | draft/sending/failed | Blueprint 03 state |
| L3-02 | 分享内容 | popover / bottom sheet | link/copied/system-share | Shared pattern |
| L3-03 | 举报内容/用户 | step dialog / page | reason/details/sent | Blueprint 06 state |
| L3-04 | 屏蔽/取消屏蔽 | confirm dialog | blocked/undo | Shared pattern |
| L3-05 | 编辑/删除帖子 | action menu / composer | dirty/delete-confirm | Blueprint 04 state |
| L3-06 | 头像/封面编辑 | crop dialog | crop/upload/error | Blueprint 06 state |
| L3-07 | 账号安全 | `/settings/security` | devices/2FA/recovery | Blueprint 08 variant |
| L3-08 | 隐私与黑名单 | `/settings/privacy` | audience/blocked-list | Blueprint 08 variant |
| L3-09 | 通知与内容偏好 | `/settings/preferences` | toggles/digest/topics | Blueprint 08 variant |
| L3-10 | 外观与无障碍 | `/settings/appearance` | theme/text/motion | Blueprint 08 variant |
| L3-11 | 消息请求与权限 | `/messages/requests` | accept/delete/block | Blueprint 07 state |
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
7. Blueprint 07：通知 + 私信 + 聊天。
8. Blueprint 08：个人资产 + 设置。
9. Blueprint 09：登录 + 注册 + 账号恢复。
10. Blueprint 10：交互表面、Dialog/Modal、移动壳层、卡片模式注册表和评论线程。
11. System Boards：共享状态、响应式和主题。

## Design completion gate

Blueprint 01–09 和 S-01–S-06 已通过设计评审。施工阶段不得以组件库默认外观、通用页面模板或 SDK 测试界面替代蓝图；蓝图 HTML/CSS 是参考实现，React 页面需在保持设计意图的同时满足真实路由、状态、响应式与可访问性。

## Scope audit result

社区核心页面无缺页。当前阶段明确不展开以下未来模块：

- C2C 商城的商品发布、商品详情、购物车、订单、支付、售后和评价链路。
- 完整圈子/社区治理的创建、成员管理、版主管理、提案与申诉工作台。
- 创作者商业中心的收益、合作、推广、结算与商业数据后台。

这些模块只继承现有视觉语义与扩展接口，不计入 Blueprint 01–09 的设计完成门槛。
