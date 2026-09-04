# LoveCards 业务 Pattern 注册表

> 本表是页面复合组件的唯一登记入口。Primitive 的用途见 [`ASTRYX-COMPONENTS.md`](./ASTRYX-COMPONENTS.md)，卡片类型细节见 [`CARD-PATTERNS.md`](./CARD-PATTERNS.md)。

## 状态

- `canonical`：语义和设计已冻结，可进入实现迁移。
- `needs-blueprint`：规范已确定，但缺少可视化状态板。
- `needs-sync`：已有蓝图或实现与现行规范冲突。
- `future`：只记录扩展边界，不进入当前施工。

## 核心 Pattern

| Pattern | 状态 | 组成 | 责任 | 使用表面 |
|---|---|---|---|---|
| AppShell | canonical | TopNav/BottomNav + search + account + Banner slot | 全局导航、内容入口、safe-area、会话状态 | 全站 |
| MobilePageShell | canonical | LocalHeader + content scroll + safe-area + optional task footer | 将 IA depth 与 `standard/task/immersive/gateway` 外壳分离，固定返回和 BottomNav 规则 | 全站移动页面 |
| AuthorIdentity | canonical | Avatar + Link + Badge/Timestamp | 真实 handle、身份入口、无效用户降级、排版变体 | Feed、详情、评论、关系、会话、通知 |
| FeedStory | canonical | AuthorIdentity + content + media + actions | 同一 Card 的 compact/standard/detail 身份 | 首页、搜索、主页、收藏、历史、详情 |
| ProfileCardFeed | canonical | Grid/Masonry + FeedStory + ResourceState | 首页与用户中心一致的列、gutter、Skeleton 和状态 | 本人/他人主页、资产 Tab |
| ProfileIdentity | canonical | Shared `ProfileIdentitySurface`: mobile is cover → vertically centered avatar/name/UID group → bio → public metadata → actions → stats; desktop keeps the cover gradient, places the page-state label at top-left and cover/overflow tools at top-right, then uses one avatar + inline name/UID identity group, stacked bio/metadata, and vertically centered right actions | 本人头像、姓名、简介支持就地编辑且只保留一个“编辑资料”主按钮；他人保留关注/私信；分享/举报/拉黑进入 overflow menu；本人和他人都保留简介与元信息空槽，本人可编辑、他人只读；访问本人的 `/user/:uid` 必须重定向到 `/me`，不能渲染第二套可编辑主页 | `/me`、`/user/:uid` |
| ProfileTabs | canonical | TabList + URL state + ProfileCardFeed | 同页内容/喜欢/收藏/历史切换 | `/me`、公开主页结构锚点 |
| CommentThread | canonical | AuthorIdentity + rows + Collapsible + ChatComposer | 根评论、回复、删除占位、深链和发送恢复 | 详情、评论深链 |
| ResourceState | needs-sync | Skeleton + EmptyState + Banner/Button | loading/empty/error/403/404/deleted/restricted/offline | 所有数据区域 |
| FormDialog | canonical | DialogHeader + FormLayout + Banner + Footer | 短编辑任务、提交状态、固定操作栏 | 编辑评论、举报、头像、短设置 |
| AvatarUploadTask | canonical | Avatar preview + FileInput/FieldStatus + scoped Banner + Toast | 文件限制、上传、恢复和成功 | 本人主页头像、主页背景等单个媒体资料 |
| MediaDropzone | canonical | FileInput + Thumbnail + Progress + FieldStatus | 多文件选择、预览、排序、移除和上传 | 发布、消息附件 |
| ActionMenu | canonical | MoreMenu + typed actions | 卡片/评论/用户的次级动作和权限 | Feed、详情、评论、主页 |
| ConfirmAction | canonical | AlertDialog | 删除、清空、屏蔽、放弃修改 | 全站高影响动作 |
| NotificationItem | canonical | Item + actor identity + resource state + unread | 后端 action_url、真实 item category、actor/resource 状态和文案 | `/inbox`、`/inbox/system` |
| MessageCenterHeader | canonical | Page title + primary tabs + contextual actions | 消息中心统一标题和“私信 / 通知”一级语义，不合并两类数据 | `/messages`、`/inbox` |
| MessageCenterTabs | canonical | “私信 / 通知”一级 Tab + 独立未读徽标 | 私信徽标只汇总 `messages.conversations()` 的 `unread_count`；通知徽标只使用 `notifications.unread()` 或通知分类未读合计，禁止互相推算或混用 | `/messages`、`/inbox` |
| ConversationRow | canonical | AuthorIdentity + summary action + timestamp + unread state | 用户主页与会话两条合法路径；摘要优先使用 `last_message_body`，时间优先使用 `last_message_at`；桌面切换工作区，移动端进入独立会话页且不默认选中第一条 | `/messages` |
| MessageThread | canonical | ChatLayout/Message/Composer + ResourceState | 游标消息、发送、重试、阻止和空会话 | `/messages/:id` |
| RelationshipList | canonical | MobilePageShell(task) + List/Item + AuthorIdentity + relation action | 本人/他人关注与粉丝使用同一独立任务壳、连续列表和动作反馈 | followers/following |
| SettingsGroup | canonical | MobilePageShell(task) + SettingsLayout + SettingsNavigation + Section + FormLayout + Switch/Selector | 独立设置栈、分类扩展、保存、权限、会话和危险区 | `/settings*` |
| SearchSurface | canonical | Typeahead + results + Empty/ResourceState | IME、防抖、取消旧请求、桌面锚定/移动全屏 | 顶栏、发现、完整搜索 |
| MediaViewer | canonical | Lightbox/Overlay + MediaTheme | 图像、图集、视频、GIF、焦点与返回 | `/media/:id` |

## Pattern 所有权

| 页面拥有 | Pattern 拥有 |
|---|---|
| 页面标题、Section 顺序、主容器、辅助栏、路由状态 | 内部语义顺序、状态、热区、圆角、焦点和数据降级 |
| Feed 的列数和外 gutter | 卡片作者、正文、媒体、元数据和动作关系 |
| 用户主页身份头与 Tab 的位置 | `AuthorIdentity` 的链接规则和 FeedStory 内容结构 |
| 列表所在页面和筛选 | 每一行的头像、文字、状态和兄弟动作语义 |

页面 CSS 禁止选择 Pattern 深层子元素进行结构重排。确有新密度时，在 Pattern 注册新变体并补状态，不创建页面专属复制品。

## 关键变体

### MobilePageShell

- `standard`：一级主页面；使用当前全局语境并显示 BottomNav。
- `task`：二级/三级独立任务；局部“返回 + 标题 + 可选动作”，无全局顶栏和 BottomNav。
- `immersive`：发布、编辑等长输入任务；无全局导航，只允许一个固定任务栏并处理软键盘、dirty guard 与 safe-area。
- `gateway`：登录、注册、恢复；不继承已登录产品导航。
- `/settings` 与全部 `/settings/*` 固定为 `task`；总览返回 `/me`，分类返回 `/settings`。
- 本人和他人的关注/粉丝列表固定为 `task`；发布/草稿恢复/已发布编辑器固定为 `immersive`。

变体控制的是页面壳，不改变内部 Pattern 的数据和交互语义。任何状态切换都不能在变体之间跳转。

### AuthorIdentity

- `compact`：信息流元数据。
- `byline`：详情作者栏。
- `comment`：32px 左头像 + 右侧讨论轴。
- `list`：关系、通知和会话行。

变体只改变排版密度，不改变有效 UID、链接、键盘和降级规则。UID 是稳定主页入口；handle 仅作为没有 UID 的兼容降级，不作为账号展示文本。

### ResourceState

- `page`：页面主资源。
- `section`：独立区域。
- `feed`：与 FeedStory 对应的 Skeleton/Empty/Error。
- `list`：连续 Item 行。
- `load-more`：保留已有数据的尾部状态。

`empty` 只用于成功零数据；404、403、deleted、restricted 和 network 均为不同状态。

### AvatarUploadTask

- `default`：当前头像 + 文件限制 + 禁用保存。
- `selected`：新预览 + 可替换/保存。
- `invalid`：FileStatus，不出现 Banner。
- `uploading`：保留预览和按钮尺寸。
- `task-error`：Dialog 内 scoped Banner。
- `success`：关闭并 Toast；不保留成功 Dialog。

### ConversationRow

- 身份、摘要和打开会话是兄弟元素：头像/用户名进入 `/user/{uid}`，摘要按钮进入会话，不允许整行链接包裹身份链接。身份副文本显示 `UID · {uid}`，不能用 `@username` 冒充唯一账号标识。
- 服务端会话字段映射固定为：`username/avatar` → `AuthorIdentity`，`last_message_body` → 摘要，`last_message_at`（缺失时 `updated_at`）→ 相对时间，`unread_count` → 未读徽标。
- 缺少正文时显示“消息内容暂不可见”或“还没有消息，发送第一条”，不显示原始数据库时间或技术字段名。
- 桌面列表可保留当前会话背景，移动端列表不预选第一条；移动端点击摘要进入 `/messages/:conversationId` 独立任务页。
- 骨架必须同时预留头像、两行摘要和时间/未读徽标的几何空间，不能只显示单行灰条。
- 容器几何固定为：桌面外层工作区使用 `--lc-radius-panel`，列表内部使用连续表面，行本身保持方角并以分隔线区分；移动端消息页直接使用页面连续表面，不额外包裹浮层圆角，行仍保持方角。
- 会话线程外壳不再叠加圆角；输入器是独立控件，使用 `--lc-radius-control`，不得使用胶囊式大圆角。消息气泡的非对称圆角属于内容语义，不能被全局圆角规则覆盖。
- ConversationRow 与其 skeleton 必须共享同一层级和几何：同样的外层圆角、行高、分隔线和内边距，避免加载完成时从卡片组跳变为连续列表。

## Pattern 完成门槛

- 正常、加载、空、错误、权限和移动状态齐全。
- 所用 SDK 能力有明确边界，不能从显示数据猜写入能力。
- 交互热区语义合法，无嵌套链接和冒泡误触。
- Pattern Skeleton 与完成态同构。
- 在 320/390/768/1050/1440px、亮暗主题、键盘和减少动态下通过。
