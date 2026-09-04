# LoveCards 导航、Tab 与选择规范

> 状态：v1.0。本文区分路由导航、同页视图切换、值选择、筛选和动作，避免“看起来都像选项卡”导致页面结构和 URL 失控。

## 1. 语义选择

| 用户意图 | 组件 | URL 行为 |
|---|---|---|
| 前往另一页面/资源 | Link、TopNav、BottomNav、Breadcrumbs | 路由变化，支持打开新窗口和历史记录 |
| 同一页面切换相关视图 | TabList | 页面壳不变；必要时同步 query/path |
| 选择一个值或模式 | SegmentedControl、RadioList、Selector | 表单值变化，不产生页面导航语义 |
| 筛选当前数据 | Chip/ToggleButton/MultiSelector | 保留页面结构，URL 可同步筛选条件 |
| 执行动作 | Button/IconButton/Menu item | 不伪装成链接或 Tab |
| 展开/收起内容 | Collapsible | 不创建无意义路由 |

视觉相似不能覆盖语义。Tab 不能提交表单，SegmentedControl 不能替代路由，按钮不能用来伪装普通链接。

## 2. IA 深度与移动壳

页面的业务层级与移动端外壳是两条独立轴，不能仅凭 URL 层数或是否出现在全局入口中决定是否显示 BottomNav。

### IA depth

| 深度 | 定义 | 典型入口 |
|---|---|---|
| 一级主页面 | 可在全局导航间稳定切换的长期目的地 | 首页、发现、消息中心、我的 |
| 二级任务页 | 从一级页面进入、完成一类明确任务后返回上级 | 关注/粉丝、草稿箱、设置总览、会话 |
| 三级深层任务 | 在二级任务内继续完成单一高专注操作 | 设置分类、密码/邮箱任务、账号恢复步骤 |

### Mobile shell

| Shell | 全局顶栏 | BottomNav | 局部顶栏 | 适用场景 |
|---|---|---|---|---|
| `standard` | 使用当前一级语境 | 显示 | 标题/搜索/当前页动作 | 首页、发现、消息中心、我的 |
| `task` | 隐藏 | 隐藏 | 返回 + 标题 + 必要动作 | 关系页、草稿箱、设置栈、私信会话 |
| `immersive` | 隐藏 | 隐藏 | 取消/返回 + 任务状态或主动作 | 发布/编辑、媒体查看、长输入任务 |
| `gateway` | 隐藏 | 隐藏 | 品牌/返回入口按认证流程决定 | 登录、注册、账号恢复 |

- 同一 IA 深度可以使用不同 Shell；例如 `/compose` 是全局发布入口，但手机编辑器仍使用 `immersive`。
- `task` 和 `immersive` 只保留系统 safe-area，不预留 BottomNav 高度，也不能渲染不可见的全局导航占位。
- 页面从 Skeleton、Empty、Error、Permission 切换到完成态时，Shell 不得变化。
- 浏览器/系统返回优先恢复真实历史；无有效站内历史时使用下表的固定上级。

| 路由 | IA depth | Mobile shell | 固定上级 |
|---|---|---|---|
| `/me` 及本人资产 Tab | 一级 | `standard` | — |
| `/me/following`、`/me/followers` | 二级 | `task` | `/me` |
| `/user/:uid/following`、`/user/:uid/followers` | 二级 | `task` | `/user/:uid` |
| `/messages`、`/inbox`、`/inbox/system` | 一级 | `standard` | — |
| `/messages/:conversationId` | 二级 | `task` | `/messages` |
| `/compose`、`/compose?draft=*`、`/compose?edit=*` | 一级入口 / 深度任务 | `immersive` | 来源页；缺失时 `/` |
| `/drafts` | 二级 | `task` | `/compose` |
| `/settings` | 二级 | `task` | `/me` |
| `/settings/*` | 三级 | `task` | `/settings` |
| `/auth/*` | 认证流程 | `gateway` | 来源页；缺失时 `/` |

## 3. 全局导航

### 桌面

- TopNav 负责不超过 5 个稳定一级入口：首页、发现、发布、消息、我的/账户。
- 搜索和当前用户动作属于顶栏工具区，不与一级导航重复。
- 当前项使用文字权重 + 下划线/形状双重信号；不只变玫瑰色。
- 右上角当前用户必须显示真实头像；缺失时使用稳定 Fallback。它进入本人主页，不显示没有身份语义的空圆。

### 手机

- `standard` Shell 的 BottomNav 保留 4–5 个稳定入口并尊重 safe-area；`task`、`immersive`、`gateway` 不显示 BottomNav。
- 顶栏只保留当前语境、返回、搜索或页面动作；不重复底栏“我的”。
- 发布入口不能使用遮挡内容的大悬浮球；视觉强调不改变 44px 触控和可读名称。
- 未读数使用 Badge，但必须有可访问文本，例如“消息，3 条未读”。

### 消息中心

- 桌面 TopNav 与手机 BottomNav 的“消息”主入口都进入 `/messages`；第一层先展示私信，并通过 `messages.conversations/list/send` 完成会话任务。
- 消息中心始终只有一个页面标题“消息中心”和一组一级 Tab（“私信 / 通知”）。桌面私信使用“会话列表 + 当前会话”双栏工作区；手机先展示会话列表，进入会话后使用 `/messages/:conversationId` 独立任务壳，不显示全局 BottomNav。列表摘要和身份链接是兄弟热区，身份进入用户主页，摘要负责打开会话。
- 消息中心一级在“私信 / 通知”之间导航；通知页内部只保留“互动消息 / 系统通知”两个相关视图。默认 `/inbox` 对应 `engagement`，`/inbox/system` 对应 `system`。
- `/inbox/message`、`/inbox/social`、`/inbox/interaction` 是历史深链，统一重定向到 `/inbox`。重定向后不保留旧筛选状态，也不触发旧分类请求。
- `type=message` 只表示私信产生的通知投影；进入私信列表、读取会话与发送消息仍由 `/messages*` 承担，不能把通知列表设计成第二套私信页。

### 设置栈

- 手机端整个 `/settings` 家族使用独立 `task` Shell，不显示全局顶栏或 BottomNav。
- `/settings` 是设置总览，返回目标为 `/me`；`/settings/security|privacy|appearance|session` 返回 `/settings`。
- 设置分类之间通过总览或明确的同栈导航切换，不能返回后落入不同的页面壳，也不能把设置总览伪装成“我的”Tab 内容。
- 桌面仍可保留产品 TopNav 与稳定侧栏；移动端的独立全屏栈不改变真实子路由语义。

## 4. 用户主页 Tab

- `/me` 的本人视图显示“内容 / 喜欢 / 收藏 / 历史”。
- 点击 Tab 只替换卡片数据与对应状态，身份头、关系统计、Tab 和 Feed 容器保持不变。
- `/me` 是本人“内容”的唯一默认 URL；内容总数直接写在“内容”Tab 标签中。喜欢、收藏、历史使用 `/me?tab=likes|saved|history`，浏览器前进/后退恢复 Tab。旧的 `?tab=content` 仍兼容但不作为新入口。
- 资料卡不再显示“动态”统计。关注和粉丝是两条明确的统计 Link，分别进入 `/me/following`、`/me/followers`；获赞只显示服务端聚合数字，不提供明细入口。不得使用折叠面板或把当前页卡片点赞数当作收到的互动总数；历史 `/me/likes-and-saves` 深链重定向回 `/me`。
- 旧 `/me/library/{likes|saved|history}` 只作为兼容入口，最终渲染同一主页壳；不得维护第二套列表页面。
- `/user/:uid` 显示一个“内容”Tab 作为结构锚点，不暴露私有资产。
- 评论只在内容详情和讨论线程中展示；用户中心不提供评论记录入口或独立页面。

主页统计进入的关系二级页使用独立任务壳：桌面保留产品顶栏和返回主页链接；移动端使用“返回 + 标题”的局部顶栏，不显示全局 BottomNav。二级页拥有自己的 loading、empty、error 和分页状态，不把列表插回主页身份头下。获赞是只读统计，不创建二级任务壳。

Tab 切换期间只在内容区显示与 Feed 完成态对应的 Skeleton。空、404、403、网络失败也在同一内容区表达。

## 5. 内容与身份导航

- 有效 UID 的头像和用户名共同组成一个 `AuthorIdentity` 链接，进入 `/user/{uid}`。
- UID 是公开身份的稳定展示值；展示昵称、`@username`、内部数字 id、注销状态或空 UID 不能推导主页地址。
- 卡片作者、卡片正文、更多菜单、喜欢/收藏/评论是兄弟热区，不允许嵌套 `<a>`。
- 点击作者不能触发卡片详情；点击卡片正文不能触发作者链接或动作。
- 评论、会话、关系列表和通知 actor 遵守相同身份规则，但排版使用对应变体。

## 6. 返回与层级

- 浏览器/系统返回优先恢复真实历史；没有站内历史时回到明确上级路由。
- 返回按钮的 fallback 必须来自本规范的路由矩阵；不能统一写死为首页，也不能依赖 `navigate(-1)` 产生站外或循环返回。
- 详情页来自 Feed 时尽量恢复原滚动位置和筛选状态。
- Breadcrumbs 只用于设置、深层管理或清楚的资源层级；移动端使用返回按钮与标题，不横向挤入完整面包屑。
- 关闭 Dialog 恢复触发器焦点；关闭全屏搜索恢复搜索按钮；关闭 Lightbox 恢复原媒体。

## 7. Tabs 键盘行为

- TabList 是一个键盘导航组：Tab 进入，方向键移动，Enter/Space 激活，Home/End 到首尾。
- 当前 Tab 使用 `aria-selected`，面板通过 `aria-controls`/`aria-labelledby` 关联。
- 横向溢出允许滚动，但当前项必须滚入可见区，首尾保留 gutter。
- 内容数量和未读 Badge 不改变 Tab 的可访问语义；数量应作为标签的次级文本，不得挤压其他 Tab 的最小触控宽度。

## 8. 筛选与分页

- 2–5 个简单互斥筛选可使用 SegmentedControl；内容分类视图使用 TabList。
- 复杂筛选使用 Popover/Sheet 内表单，不把一排胶囊无限横向堆叠。
- Cursor 列表使用“加载更多”或连续加载状态，不伪造页码；真正 page-based 数据才使用 Pagination。
- 通知的 `next_cursor` 是不透明复合游标，只能原样回传当前分类请求；URL 不保存、不暴露也不构造 cursor。
- 切换筛选时取消旧请求，清楚区分首次加载和加载更多；URL 只保存可分享、可恢复的筛选。

## 9. 禁止

- 禁止点击 Tab 后进入不同页面模板。
- 禁止为同一入口同时保留顶栏和底栏副本。
- 禁止用整卡链接包裹内部按钮或作者链接。
- 禁止使用没有可读名称的图标导航。
- 禁止把 Button 写成只有 `onClick(navigate)` 的假链接；语义导航必须可复制地址、打开新窗口和被读屏识别。
- 禁止把服务端 `action_url` 之外的信息拼成通知路由。
- 禁止在 `task`、`immersive` 或 `gateway` Shell 底部保留 BottomNav 的高度占位。

## 10. 验收

- 鼠标、Tab、方向键、Enter、Space、浏览器前进/后退得到一致结果。
- 用户主页切 Tab 不重建页面壳、不跳顶、不改变 Feed gutter。
- 所有作者链接使用真实 username，缺失时诚实降级。
- 320px 下 Tab 可滚动、当前项可见、操作不重叠。
- 焦点在导航、Overlay 和路由切换后都有合理落点。
