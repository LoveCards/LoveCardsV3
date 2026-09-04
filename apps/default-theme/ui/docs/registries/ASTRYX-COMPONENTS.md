# Astryx 组件用途与采用注册表

> 基线：`@astryxdesign/core@0.3.0`。官网只用于理解组件意图；实现参数以本地 `*.doc.mjs` 为准。子组件和 hooks 跟随所属主组件，不单独形成页面选型。

## 状态

- `adopt`：可直接作为 LoveCards Primitive 使用。
- `compose`：保留 Astryx 行为，由 LoveCards Pattern 决定业务结构和视觉。
- `defer`：用途成立，但当前主题没有真实场景。
- `avoid`：当前产品中容易造成错误语义；重新启用需要设计决策。

## Action

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Button | 提交、确认和明确动作 | adopt | 每区一个主动作，使用具体动词 |
| IconButton | 紧凑且图标普遍可理解的动作 | adopt | 必须有可访问名称和 44px 热区 |
| Link | 页面或资源导航 | adopt | 路由语义不用 Button 模拟 |
| ButtonGroup | 视觉相连的一组同级动作 | defer | 不用于主次动作混排 |
| DropdownMenu | 触发器后的动作列表 | adopt | 适合有明确标签的动作菜单 |
| MoreMenu | 三点入口的次级动作 | adopt | 卡片、评论、会话统一入口 |
| ContextMenu | 鼠标右键的上下文动作 | avoid | 触屏不可发现，不作为唯一入口 |
| SegmentedControl | 2–5 个互斥值或模式 | adopt | 不承担路由和页面 Tab |
| ToggleButton | 保持选中/未选中的工具动作 | adopt | 只用于持久开关型动作 |
| ToggleButtonGroup | 单选或多选工具组 | defer | 富文本/媒体工具需要时启用 |
| Toolbar | 内容区域内的上下文工具条 | compose | 不作为页面顶栏 |

## Chat

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Chat | 会话组件家族的组合入口 | compose | 按评论或私信任务选子组件，不整体套用默认示例 |
| ChatComposer | 评论、回复和私信的输入外壳 | compose | 评论与消息共享输入行为，不共享业务字段 |
| ChatComposerInput | 多行消息正文 | adopt | Enter/Shift+Enter/IME 行为统一 |
| ChatSendButton | 发送、发送中和禁用状态 | adopt | 保留按钮尺寸和错误草稿 |
| ChatLayout | 完整会话滚动与 Composer 布局 | compose | 用于私信，不用于通知列表 |
| ChatMessage | 发件人感知的消息行 | compose | 映射本人/他人和失败状态 |
| ChatMessageMetadata | 时间、送达和失败元数据 | compose | 不堆叠为彩色 Badge |
| ChatSystemMessage | 会话中的系统事件 | defer | 后端有正式事件后启用 |
| ChatToolCalls | AI 工具调用展示 | avoid | 当前产品没有 AI 工具调用场景 |
| ChatDictationButton | 语音听写入口 | defer | 权限、录音和转写能力齐备后启用 |

## Container

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Card | 可独立移动、移除、选择或比较的对象 | compose | 不是默认分组工具；禁止嵌套卡片 |
| ClickableCard | 单一主导航目标且含独立子动作的卡片 | compose | 必须保证作者/正文/动作合法分离 |
| SelectableCard | 在一组卡片中选择对象 | defer | 不用于内容 Feed |
| Collapsible | 原位展开次要内容 | adopt | 线程、设置说明和长详情适用 |
| Carousel | 横向浏览同类项目或媒体 | compose | 图集可用，不隐藏关键内容 |

## Content

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Avatar | 展示用户或群体身份 | adopt | 统一尺寸、Fallback、加载失败和编辑/导航语义 |
| AvatarGroup | 展示参与者集合 | defer | 讨论参与者或共创场景再启用 |
| Heading | 语义化页面与 Section 标题 | adopt | 保持 h1–h6 层级，不按外观跳级 |
| Text | 正文、标签和说明文字 | adopt | 使用固定产品字号体系 |
| Icon | 动作或概念的辅助符号 | adopt | 统一圆端线性体系，不混图标集 |
| EmptyState | 成功加载后的零数据和首次使用 | adopt | 标题 + 原因 + 一个下一步 |
| Timestamp | 相对或绝对时间 | adopt | 近期用相对时间，精确场景用绝对时间 |
| Thumbnail | 小型附件或上传预览 | adopt | 发布队列和消息附件使用 |
| Markdown | 渲染受支持的富文本内容 | compose | 详情正文需安全白名单；Feed 不全量渲染 |
| Blockquote | 引用内容 | compose | 使用 Quote Pattern；移除粗彩色侧条 |
| Citation | 外部来源引用 | defer | 正式来源契约存在后启用 |
| Code | 行内代码 | defer | 技术社区内容需要时启用 |
| CodeBlock | 多行代码与复制 | defer | 需要内容类型和安全策略后启用 |
| Kbd | 键盘快捷键提示 | defer | 帮助/命令面板场景使用 |
| Token | 标签、筛选值或收件人选择 | compose | 普通元数据不得全部胶囊化 |

## Data Input

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Field | 为自有/第三方控件补 Label、Description、Status | adopt | Astryx 输入已有字段壳时不重复包装 |
| FieldStatus | 单字段或文件的验证反馈 | adopt | 不替代页面/任务错误 |
| TextInput | 短单行文本 | adopt | 姓名、账号、搜索等 |
| TextArea | 多行正文 | adopt | 评论、私信、发布正文和举报详情 |
| FileInput | 单/多文件选择、拖放和验证 | adopt | 头像与媒体上传唯一文件入口 |
| InputGroup | 前后缀与输入的连续单元 | defer | 链接、单位等真实场景再启用 |
| CheckboxInput | 单个需提交的布尔值 | adopt | 条款与表单选择 |
| CheckboxList | 多个同时可选且全部可见的选项 | defer | 小型设置组使用 |
| RadioList | 少量互斥选项 | adopt | 原因、隐私范围等需要比较的选择 |
| Switch | 立即生效的开关 | adopt | 延迟保存的设置不用 Switch |
| Selector | 中等数量的单选下拉 | adopt | 静态/有限选项 |
| ComplexSelector | 需要自定义复杂内容的单选 | defer | 普通选项不得使用 |
| MultiSelector | 有限列表多选 | defer | 多话题/多筛选能力稳定后启用 |
| Typeahead | 大型或异步数据的搜索单选 | adopt | 顶栏搜索、话题建议、用户选择 |
| Tokenizer | 搜索并累积多个 Token | defer | 收件人或多标签场景使用 |
| NumberInput | 带范围与步进的数字 | defer | 当前用户主题很少需要 |
| Slider | 连续数值或范围探索 | defer | 不用于精确值输入 |
| Calendar | 月视图日期/范围选择 | defer | 当前无日程场景 |
| DateInput | 单个日期 | defer | 当前无正式字段 |
| DateRangeInput | 日期范围 | defer | 当前无报表/筛选场景 |
| DateTimeInput | 日期与时间 | defer | 当前无排期能力 |
| TimeInput | 时间值 | defer | 当前无正式字段 |
| PowerSearch | 多字段结构化查询 | avoid | 用户社区搜索保持轻量，不后台化 |

## Feedback & Status

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Banner | 持续的页面/Section/任务信息 | adopt | 按范围使用；字段错误不用 Banner |
| Toast | 已完成动作的短暂非阻塞确认 | adopt | 关键失败和表单验证不用 Toast |
| Badge | 短状态或分类 | adopt | 日期、计数和说明不是 Badge |
| StatusDot | 在线、未读、运行状态 | adopt | 必须配文字或可访问名称 |
| Skeleton | 对应最终结构的首次加载 | adopt | 不使用万能骨架卡 |
| Spinner | 紧凑未知等待 | adopt | 按钮/局部控件内使用 |
| ProgressBar | 可信的已知进度 | adopt | 不显示虚假百分比 |

## Layout

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| AppShell | 顶栏、侧栏、Banner 与主内容的应用根壳 | compose | 保留现有主题路由和移动底栏 Pattern |
| Layout | Header/Panel/Content/Footer 的结构组合 | compose | 复杂工作区使用，简单页面不用 |
| Section | 页面内容分区 | adopt | 页面分组优先于 Card |
| Stack | 一维排列与一致间距 | adopt | 默认布局 Primitive |
| Grid | 二维列/行编排 | adopt | Feed、媒体和状态板使用 |
| Center | 空态、认证或明确居中内容 | adopt | 不把长页面内容垂直强行居中 |
| FormLayout | 字段间距与方向 | adopt | 不管理提交和业务状态 |
| AspectRatio | 稳定图片/视频比例 | adopt | Skeleton 与失败态同样使用 |
| Divider | 有必要的内容分组边界 | adopt | 不为每一行重复描边 |
| Resizable | 可调整工作区面板和拖拽分隔 | defer | 私信双栏需求证明后启用，ResizeHandle 跟随使用 |

## Navigation

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| TopNav | 不超过 5 个产品级入口 | compose | 桌面壳层使用，筛选不用 TopNav |
| MobileNav | 窄屏侧栏抽屉 | defer | 默认主题使用底栏；设置层级需要时再用 |
| SideNav | 5 个以上或有层级的应用导航 | compose | 设置可用，用户主页不使用资产侧栏 |
| TabList | 同页相关视图切换 | adopt | 用户中心和消息分类使用 |
| Breadcrumbs | 资源/设置层级返回 | defer | 移动用返回标题，浅层页面不用 |
| Pagination | page-based 数据翻页 | defer | cursor 数据使用加载更多 |
| Outline | 长页面内标题目录 | defer | 帮助/长设置页使用 |
| OverflowList | 导航/标签在窄宽度自动收口 | adopt | Tab 自带 overflow 时不重复 |
| NavIcon | 导航分区的识别图标 | defer | 不把每个入口做彩色圆标 |
| NavHeadingMenu | 导航标题切换菜单 | defer | 多站点/空间场景再启用 |
| TopNavMenu / MegaMenu | 复杂顶栏层级 | avoid | 当前信息架构不需要营销式大菜单 |

## Overlay

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Dialog | 阻塞式短任务或必须集中注意力的表面 | adopt | 先评估内联；头像编辑等完整任务可用 |
| AlertDialog | 不可逆/高影响确认 | adopt | 删除、清空、屏蔽和放弃修改 |
| Popover | 锚定触发器的补充信息或次级操作 | adopt | 空间不足时转移动任务表面 |
| HoverCard | hover/focus 的丰富预览 | defer | 不能承载必须信息，触屏有替代 |
| Tooltip | 图标说明或截断全文 | adopt | 不承载关键错误或交互 |
| Overlay | 媒体上的动作/支持内容 | compose | 玻璃仅属于临时媒体控件 |
| Lightbox | 全屏图片/视频查看 | compose | 使用真实媒体权限和返回焦点 |
| Toast | Toast 视觉元素/视口 | adopt | 统一由 provider 管理、去重和时序 |
| CommandPalette | 键盘命令与导航启动器 | defer | 产品入口成熟后再评估 |

## Table & List

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Item | 头像/图标 + 标签/说明 + 尾部内容的结构行 | compose | 通知、关系、会话和设置的基础语法 |
| List | 连续的同类项目 | adopt | 关系、通知和设置使用，不逐行套 Card |
| MetadataList | 资源属性键值对 | defer | 详情/设置摘要需要时启用 |
| OverflowList | 横向项目自动收口 | adopt | 工具条、标签或 Breadcrumb 使用 |
| Table | 结构一致的行列数据 | avoid | 用户主题默认用 List；管理数据不在本主题 |
| TreeList | 可展开层级数据 | defer | 当前无文件树/分类树场景 |

## Utility

| 组件 | 用途 | 状态 | LoveCards 规则 |
|---|---|---|---|
| Theme | 提供 Astryx 主题 Token | adopt | 映射 LoveCards 亮暗语义 Token |
| useTheme | 在 SVG、Canvas 等非 CSS 消费者中读取主题值 | defer | 普通 React 样式继续使用 CSS Token |
| MediaTheme | 媒体覆盖层的反色 Token | adopt | 播放、页码和媒体菜单使用 |
| SyntaxTheme | 代码高亮主题 | defer | CodeBlock 启用时一并采用 |
| Layer | Portal、Toast 和浮层根环境 | adopt | 应用根只注册一次 |
| LinkProvider | 将 Astryx 导航映射到 React Router Link | adopt | 统一站内路由语义 |
| InternationalizationProvider | 组件 locale 和翻译 | adopt | 默认中文，保留未来 locale |
| VisuallyHidden | 视觉隐藏但读屏可读的文本 | adopt | 图标名称、live region、补充上下文 |

## LoveCards 业务 Pattern，不由 Core 替代

| Pattern | Astryx 基础 | 责任 |
|---|---|---|
| AuthorIdentity | Avatar + Link + Badge | 有效 UID 优先、身份链接、排版变体和降级；仅无 UID 时兼容有效 handle |
| FeedStory | AspectRatio + media primitives + actions | 内容类型、密度、作者/正文/媒体/动作语法 |
| ProfileCardFeed | Grid/Masonry + FeedStory + ResourceState | 首页与主页一致的卡片流、gutter 和状态 |
| CommentThread | Avatar + ChatComposer + Collapsible | 评论层级、深链、删除占位和回复 |
| NotificationItem | Item + Avatar + Badge/StatusDot | actor/resource 状态、action_url 和已读 |
| ResourceState | Skeleton + EmptyState + Banner/Button | loading/empty/error/403/404/deleted/restricted |
| FormDialog | Dialog + FormLayout + Banner | Header/Body/Footer、提交和任务错误 |
| MediaDropzone | FileInput + Thumbnail + Progress/FieldStatus | 文件约束、队列、预览和恢复 |

## 维护规则

新增或升级组件时必须更新本表：用途、状态、业务约束、替代方案和受影响 Pattern。页面不得直接采用 `defer`/`avoid` 组件；若有真实需求，先完成设计决策再改状态。
