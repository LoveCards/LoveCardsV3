# LoveCards Primitive Components

> v1.2 · 施工前冻结。通用组件负责行为和状态；完整组件选型见 [`../docs/registries/ASTRYX-COMPONENTS.md`](../docs/registries/ASTRYX-COMPONENTS.md)，Feed Card 的内容轮廓归 [`CARD-SYSTEM.md`](./CARD-SYSTEM.md)。

## 通用状态契约

每个交互组件必须具备 Default、Hover、Focus Visible、Press、Selected（适用时）、Disabled、Loading、Invalid/Error。触控热区 ≥44×44px，焦点顺序与视觉顺序一致，Overlay 打开后焦点进入、关闭后返回触发器。

| 状态 | 规则 |
|---|---|
| Hover | 仅 hover 设备启用；亮度约 2% 或轻调边界 |
| Focus | 2px Deep Rose + 3px 低透明 ring，不改尺寸 |
| Press | 100–140ms `scale(.97)`，可中断 |
| Selected | 颜色 + 形状/文字同时变化 |
| Disabled | 保持 Label 可读，取消动作 |
| Loading | 保留尺寸和语义，Spinner 不替换整个布局 |
| Invalid | `aria-invalid`、原因、恢复动作；不只变红边 |

## Button / IconButton / Link

- Primary：Deep Rose、白字、8px，40px 高，左右 16px；每个区域最多一个。
- Secondary：Surface、Ink、1px Separator；Ghost 透明底，Hover 才出现 Subtle Surface；Destructive 使用 Danger 但不满屏染红。
- 文案使用动词：“发布”“保存修改”“加入讨论”；图标与文字间距 8px。
- IconButton 视觉 36–40px、命中区 44px；Like 激活 Signal Rose，Save 激活 Ink，More/Back/Close 统一图标体系。
- Link 必须有文字或下划线状态；禁止整卡 `<a>` 包裹内部按钮。

## Fields

每个字段由 Label、Control、Description、Message 组成；Placeholder 不代替 Label。

- TextInput：44px 高、10px 圆角、Subtle Surface、左右 14px；Focus 只改变边界和 ring。
- TextArea：最小 96px，正文编辑 160–180px，行高 1.65；发送失败保留输入。
- Search：桌面 42–44px，点击后在原位聚焦并展开结果层；移动进入独立搜索面，输入后原位更新结果。统一使用 Astryx Typeahead 的 Combobox/Listbox 语义，方向键移动高亮、Enter 打开高亮结果、Escape 与点外关闭并把焦点归还触发位置。
- 实时搜索默认防抖 150–250ms，新输入必须取消上一请求并阻止旧结果覆盖新结果；中文输入法组合期间不发请求。无关键词展示近期内容与热门话题，搜索中显示局部 Loading，无结果和错误均留在结果层并提供明确下一步。
- 结果项保持“类型/作者或话题 → 标题 → 一行上下文”的信息层级；桌面结果层锚定输入框且不推动页面，移动结果更新不得造成键盘收起、页面跳顶或输入焦点丢失。
- Select/Combobox：少量静态选项用 Select，大量/异步用 Combobox；Popup 使用 Portal，移动空间不足转 Sheet。

### Comment Composer

- 评论和回复统一使用 Astryx `ChatComposer`、`ChatComposerInput`、`ChatSendButton`；LoveCards 只提供 `elevation="none"`、密度、回复上下文和状态文案。
- 回复状态必须可取消；提交失败保留文本；Enter 发送、Shift+Enter 换行，IME 期间不提交。
- 评论行不是卡片：Root/Reply 使用同一行语法，回复只增加缩进和关系线。

## Selection and navigation

- Checkbox/Radio/Switch 使用对应语义；整行可点击，Selected 同时有形状和文字差异。
- 2–5 个互斥选项使用 Segmented Control；筛选使用 Chip，标签仅导航，Action Chip 必须有动词。
- Channel/Tabs：桌面 52px、移动 44px；当前项使用 Ink + 3px Deep Rose 下划线，横向滚动但键盘可达。
- TopNav：桌面 68px、移动 56px；BottomNav：64px + safe-area，4–5 个稳定入口，不用超大悬浮球遮挡信息流。

## Identity and status

Avatar 24/32/36/48px 分级，图片失败必须有稳定 Fallback；官方、创作者、社区身份使用独立 Badge。Badge 只承载短状态（官方、置顶、GIF、广告、待审核），不能把所有元数据做成彩色胶囊。

`AuthorIdentity` 是卡片作者的唯一身份原语：头像与用户名由同一个语义化链接包裹，地址为 `/user/{username}`，链接支持 Tab/Enter，并使用全局 Focus Visible ring。只有后端返回非空且有效的 username/handle 时生成链接；展示昵称、数字用户 ID、“未知用户”或注销状态都不能推导主页地址。无有效 handle 时保留同尺寸的普通头像与文本，避免布局跳变。

身份入口不以容器类型为边界：信息流卡片、详情作者栏、评论与回复、收藏记录以及关系列表中，只要同时出现用户头像或用户名，就复用相同主页入口和无效 handle 降级规则。评论操作菜单、回复按钮与身份链接保持独立热区。

统一身份行为不等于统一排版。运行实现固定使用四个变体：`compact` 用于信息流与作品元数据；`byline` 用于详情作者栏；`comment` 保留 32px 左头像及右侧用户名、时间、正文阅读轴；`list` 用于关系、收藏与会话列表。变体只能调整头像尺寸、间距和元数据排列，不能改变主页地址、键盘行为或无效 handle 降级规则。

会话行采用合法的双路径结构：`AuthorIdentity` 进入用户主页，“进入会话”作为兄弟链接打开对话。搜索 Typeahead 是单动作 Listbox，内容结果中的作者仅作为说明文字；用户主页入口应作为独立用户结果出现，禁止在 `option` 内嵌第二个链接。

当卡片正文可进入详情时，`AuthorIdentity` 与正文链接必须是兄弟热区，不允许用整卡 `<a>` 包住作者、菜单或操作控件。作者链接的点击事件不得冒泡到卡片详情；触控端头像与用户名共享连续命中区，不能与更多菜单或统计区域重叠。

## Overlay

- Dropdown/MoreMenu：项目级动作，项高 ≥40px，破坏性动作分组。
- Popover：筛选、快速资料等非阻塞内容；空间不足转 Sheet。
- Tooltip：只解释陌生图标，不承载重要信息或交互。
- Dialog/AlertDialog：必须有 Title；破坏确认默认聚焦 Cancel；能内联完成的任务不使用 Modal。
- Sheet/Drawer：移动筛选和短编辑；关闭不能只依靠拖拽，必须有明确按钮。

所有 Overlay 通过 Portal/top layer 逃离 `overflow:hidden`，禁止用 9999 解决裁切。

## Feedback

- FieldStatus：字段和文件值的校验反馈；不承担网络、权限或页面错误。
- Banner：页面、Section 或任务区的持续信息；error 在问题解决前保留，同类信息不堆叠。
- Toast：只提示已完成且不需立即处理的短反馈；关键失败、表单验证和需要行动的信息不能使用 Toast。
- Progress：已知进度；Spinner：短未知等待；Skeleton：复制最终结构并保留媒体比例。
- Empty：只表示成功加载后的零数据，标题 + 原因 + 一个下一步；403、404、删除和网络失败使用 ResourceState。
- 完整选择矩阵和头像上传流程见 [`../docs/specifications/FEEDBACK-AND-STATUS.md`](../docs/specifications/FEEDBACK-AND-STATUS.md)。

## Astryx mapping

施工时按 [`ASTRYX-COMPONENTS.md`](../docs/registries/ASTRYX-COMPONENTS.md) 的 `adopt/compose/defer/avoid` 状态选型。LoveCards 只维护 Theme、Primitive policy 和 Community Pattern，默认不复制或 swizzle Astryx Core；当前实现 API 以锁定的 `@astryxdesign/core@0.3.0` 本地文档为准。

## 验收

每个 Primitive 通过键盘、亮暗主题、200% 缩放、减少动态、加载/错误/禁用和移动触控检查；同一动作在所有页面保持相同变体、图标与文案。
