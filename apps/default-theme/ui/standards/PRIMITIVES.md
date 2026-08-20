# LoveCards Primitive Components

> v1.1 · 施工前冻结。通用组件负责行为和状态；Feed Card 的内容轮廓归 [`CARD-SYSTEM.md`](./CARD-SYSTEM.md)，模式注册归 [`../docs/registries/CARD-PATTERNS.md`](../docs/registries/CARD-PATTERNS.md)。

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

## Overlay

- Dropdown/MoreMenu：项目级动作，项高 ≥40px，破坏性动作分组。
- Popover：筛选、快速资料等非阻塞内容；空间不足转 Sheet。
- Tooltip：只解释陌生图标，不承载重要信息或交互。
- Dialog/AlertDialog：必须有 Title；破坏确认默认聚焦 Cancel；能内联完成的任务不使用 Modal。
- Sheet/Drawer：移动筛选和短编辑；关闭不能只依靠拖拽，必须有明确按钮。

所有 Overlay 通过 Portal/top layer 逃离 `overflow:hidden`，禁止用 9999 解决裁切。

## Feedback

- Toast：只提示已完成且不需立即处理的短反馈；错误优先 Inline Message/Banner。
- Progress：已知进度；Spinner：短未知等待；Skeleton：复制最终结构并保留媒体比例。
- Empty：标题 + 原因 + 一个下一步，不写完“暂无数据”就结束。
- Banner/Message：原因、范围和恢复动作相邻显示；禁止彩色粗侧条。

## Astryx mapping

施工时优先映射到 Astryx：Button/IconButton、TextInput/TextArea/Field、TabList/SegmentedControl、Selector/Typeahead、CheckboxInput/RadioList/Switch、Avatar/Badge、MoreMenu/Popover/Tooltip、Dialog/Overlay、Toast/Banner/Skeleton/EmptyState、AppShell/TopNav。LoveCards 只维护 Theme、Primitive policy 和 Community Pattern，默认不复制或 swizzle Astryx Core。

## 验收

每个 Primitive 通过键盘、亮暗主题、200% 缩放、减少动态、加载/错误/禁用和移动触控检查；同一动作在所有页面保持相同变体、图标与文案。
