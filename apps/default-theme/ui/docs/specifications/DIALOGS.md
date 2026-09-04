# LoveCards Dialog / Modal 弹窗规范

> v1.0 · 共享交互表面规范。本文是所有 Dialog、Alert、Form Sheet 和全屏任务层的事实源；视觉参考见 [`blueprint-10-interaction-surfaces.html`](../../blueprints/blueprint-10-interaction-surfaces.html) 的 Dialog System 章节。该页面同时加载 `blueprint-10-interaction-surfaces.css` 与 `blueprint-10-dialogs.css`，后者只承载弹窗章节的参考样式。

## 1. 目标与边界

LoveCards 的弹窗只承载一个清晰任务：编辑、确认、举报、选择或暂时聚焦内容。弹窗离开文档流后，必须仍然像页面的一部分，保留当前内容的上下文和返回路径。

### 1.1 设计现场

用户通常在手机上单手浏览信息流，拇指操作，屏幕下方可能被软键盘占据；桌面用户则在详情页、草稿或设置工作区中用键盘完成短任务。弹窗因此要稳定、可预期、可恢复，不能让标题、输入内容和操作按钮互相争夺空间。

### 1.2 适用范围

- `Form Dialog`：编辑评论、举报内容、编辑资料、裁剪头像等短表单。
- `Alert Dialog`：删除、放弃编辑、清空历史、拉黑等需要确认后果的动作。
- `Form Sheet`：手机端短表单，或需要保持底部操作靠近拇指的编辑任务。
- `Fullscreen Task`：移动搜索、媒体查看器等本身就是独立工作区的任务。

不适合弹窗的任务优先使用页面内联展开、渐进披露或独立路由。成功提示使用 Toast 或页面内状态，不再用一个“成功弹窗”打断用户。

## 2. 不可破坏的结构

所有标准弹窗必须遵守以下三行骨架：

```text
Backdrop
└─ Dialog surface
   ├─ Header（标题、说明、关闭）
   ├─ Body（唯一滚动区域）
   └─ Footer（固定操作区）
```

### 2.1 三行布局契约

Dialog surface 使用稳定的行轨道：

```css
display: grid;
grid-template-rows: auto minmax(0, 1fr) auto;
overflow: hidden;
```

- **Header**：正常参与布局，不能用绝对定位覆盖标题或关闭按钮。标题区允许换行；关闭按钮独立保留 `44 × 44px` 热区。
- **Body**：`min-height: 0`，只在这一层设置 `overflow: auto` 和 `overscroll-behavior: contain`。长文本、错误提示和字段辅助信息都留在自己的内容轴内。
- **Footer**：不放在 Body 内，不随正文滚动。操作栏通过上边界与正文分开，始终保持可见；按钮换行时不能改变 Dialog 的宽度。

禁止使用 Header、Body、Footer 之间互相覆盖的 `position: absolute` 布局，也禁止把按钮通过 `float`、负边距或固定坐标“贴”到正文上。

### 2.2 结构示意

```html
<Dialog class="lc-dialog lc-dialog--form">
  <DialogHeader />
  <div class="lc-dialog__body">
    <form class="lc-dialog__form">...</form>
  </div>
  <footer class="lc-dialog__footer">
    <button>取消</button>
    <button>保存修改</button>
  </footer>
</Dialog>
```

运行实现继续使用 Astryx `Dialog` 和 `DialogHeader`；上面的类名表达结构契约，不要求页面手写一套新的 Dialog 组件。

## 3. 视觉 Token

| Token | 默认值 | 用途 |
|---|---:|---|
| Surface width / form | `480–560px` | 编辑评论、举报、编辑资料等短表单 |
| Surface width / alert | `360–440px` | 删除、放弃、屏蔽等确认 |
| Surface max-height | `min(75dvh, 620px)` | 桌面短表单；超出后只滚动 Body |
| Mobile sheet max-height | `min(92dvh, 760px)` | 手机表单；为浏览器工具栏和键盘留空间 |
| Surface radius | `14px` 桌面 / `16px` Sheet 顶角 | 与现有面板同源，不使用过度圆角 |
| Header min-height | `68–78px` | 标题、说明、关闭按钮的稳定行高 |
| Body padding | `20px` 桌面 / `16px` 手机 | 与输入轴和详情正文一致 |
| Footer min-height | `72px` 桌面 / `64px + safe-area` 手机 | 操作固定、触控目标稳定 |
| Control target | 最小 `44 × 44px` | 关闭、取消、主动作和菜单触发器 |
| Backdrop layer | `z-index: 70` | 模态背景，不遮蔽 Toast 语义 |
| Dialog layer | `z-index: 80` | 弹窗表面；菜单和 Popover 必须先关闭或位于其上 |

颜色沿用 [`DESIGN.md`](../../DESIGN.md)：`surface` 承载弹窗，`canvas` 只作为被遮罩的上下文，`primary-rose` 只用于主动作，`danger` 只用于危险动作和错误。Backdrop 使用中性黑色透明度，不使用玫瑰色遮罩、渐变或玻璃背景。

## 4. 变体

### 4.1 Form Dialog

用于编辑评论、举报内容、头像上传和短设置；整页资料编辑已经取消，不再作为 Form Dialog 的入口理由。

- 桌面居中，宽度 `480–560px`，高度由内容决定。
- `purpose="form"`：阻止点击 Backdrop 误关闭，允许 Escape 退出未保存表单。
- 主动作是明确动词，例如“保存修改”“提交举报”；取消是次级动作。
- 有错误时保留用户输入，错误就地出现在字段或操作栏上方。
- 保存中保留按钮宽度，主按钮标签替换为“保存中”；禁止重复提交和误关闭。
- 字段/文件值错误使用 FieldStatus；影响整个任务的网络、权限或服务端错误使用 Body 顶部 scoped Banner。

### 4.2 Alert Dialog

用于有不可逆后果的操作。

- 宽度 `360–440px`，正文控制在 `45–60ch` 内。
- 标题直接描述后果，例如“删除这条评论？”；说明补足范围和可恢复性。
- 取消在次级位置，危险动作使用明确动词“删除评论”“清空历史”。
- 不用“确定”“是”这类无法回忆上下文的标签。
- 危险色同时配合图标或文字，不依赖颜色单独传达风险。

### 4.3 Mobile Form Sheet

手机端短表单优先转为底部 Sheet：

- `width: 100%`，贴近视口底部；底部圆角为 `0`，上方使用 `16px` 圆角。
- `max-height: min(92dvh, 760px)`；Body 仍是唯一滚动层。
- Footer 使用 `env(safe-area-inset-bottom)`，按钮横向均分并保持 `44px` 高度。
- 可显示 32px 拖拽手柄作为方向线索，但手柄不是唯一关闭方式；关闭按钮仍在 Header。
- 软键盘出现时不能依赖 `100vh` 固定高度；使用动态视口单位，确保 Footer 不被键盘遮挡。
- 头像编辑在手机端使用这一响应式形态：点击主页头像直接进入任务，关闭后回到原头像，不经过资料二级页面。

### 4.4 Fullscreen Task

搜索和媒体查看器是独立工作区，使用 Astryx `variant="fullscreen"`。它们不套 Form Dialog 的固定宽度规则，但仍保留 Header、Body 和可恢复的关闭/返回路径；媒体查看器的操作浮层只能覆盖媒体，不覆盖正文输入。

## 5. 编辑评论专用规格

编辑评论是当前错位问题的第一施工对象，必须按以下 profile 实现：

| 区域 | 规则 |
|---|---|
| 标题 | `编辑评论`；副标题“修改后会立即更新到讨论中。”可换行但不截断 |
| 关闭 | Header 右侧独立 `44px` 图标按钮；保存中禁用 |
| Label | 始终存在“评论内容”；视觉隐藏时仍保留可访问名称 |
| TextArea | 桌面最小高度 `152px`，手机最小高度 `110px`；内容可换行，不用绝对定位计数器 |
| 计数 | 字段底部右对齐，使用 tabular numbers；不遮盖文本 |
| 错误 | TextArea 下方显示原因，例如“网络暂时不可用，请重试。”；内容保持 |
| Footer | `取消` + `保存修改`；主动作固定在右侧或手机横向均分 |
| 退出 | Escape 关闭未保存表单；保存中不允许关闭；成功后关闭并恢复触发器焦点 |
| 长文本 | Body 滚动，Header/Footer 不移动；滚动条不改变 surface 宽度 |

编辑时不要自动选择全部文本，默认将光标放到文本末尾，避免用户误操作覆盖整条评论。回复上下文、原评论位置和“修改后会立即更新”都属于辅助信息，不能挤占 TextArea 的可读高度。

## 6. 状态矩阵

每个弹窗变体至少要有以下状态参考；蓝图中的移动 Sheet、危险确认和错误状态对应这些状态：

| 状态 | 可见反馈 | 操作规则 |
|---|---|---|
| Default | 正常标题、字段、操作 | 主动作可用；焦点清晰 |
| Focus | 字段边界切换为 Deep Rose + Focus Ring | 不改变尺寸，不触发布局跳动 |
| Disabled | 保留标签与原因，降低交互性而非可读性 | 禁止提交；仍允许阅读输入 |
| Loading | 主按钮保留宽度，显示“保存中/提交中” | 禁止重复提交；取消和关闭按任务规则处理 |
| Error | 字段或 Footer 上方显示可读原因 | 保留正文；提供“重试”或可执行恢复动作 |
| Long content | Body 出现滚动，Header/Footer 固定 | 不让标题、关闭、主动作被内容覆盖 |
| Guest / Permission | 说明需要登录或权限 | 提供登录/返回动作，不假装完成写入 |
| Success | 关闭后在原页面显示 Toast 或局部状态 | 不额外弹成功 Dialog |

错误、成功和权限状态必须同时使用文字；不能只变色，也不能把后端失败渲染成“已保存”。

## 7. 键盘、读屏与焦点

- 使用原生 `dialog` 或 Astryx Dialog 的模态行为，焦点不能逃出当前 surface。
- 打开时保存触发器，关闭后恢复触发器焦点；触发器已卸载时回到当前页面的合理标题。
- Form Dialog 初始焦点放在第一个可编辑字段；Alert Dialog 初始焦点放在取消动作或标题后的安全动作。
- `Tab` 顺序固定为 Header 关闭 → Body 字段 → Footer 取消 → 主动作；不把隐藏的加载控件放进顺序。
- `Escape` 只关闭最上层菜单/Popover/Dialog；IME 组合期间的 Escape 和 Enter 不得误提交或误关闭。
- Dialog 必须有可访问名称；标题使用 `aria-labelledby`，说明使用 `aria-describedby`。
- 关闭按钮的可访问名称使用“关闭编辑评论”“关闭举报”等上下文文本，不只读“×”。

## 8. 滚动、层级与响应式验收

### 8.1 滚动边界

1. 打开 Dialog 时锁定页面背景滚动，保留当前滚动位置。
2. Body 在顶部/底部到达边界时不把滚动串给背景。
3. Footer 不参与 Body 的滚动高度计算；按钮始终在 surface 内。
4. 软键盘打开、屏幕旋转和 200%/400% 缩放后，内容仍能通过滚动访问。

### 8.2 断点

| 视口 | 形态 | 必检 |
|---|---|---|
| `1440px` | 居中 Form Dialog | 标题、正文和 Footer 轴对齐 |
| `768px` | 窄桌面 / 平板 Form | 按钮不挤压，长说明可换行 |
| `390px` | Mobile Sheet | safe-area、软键盘、按钮均分 |
| `320px` | 极窄手机 | 最长中文词、错误文案、计数不溢出 |

### 8.3 非重叠不变量

- 任何文本都不能越过父容器边界。
- 关闭按钮、字段计数和错误消息不能覆盖正文。
- 主动作在加载、错误、禁用时保持原尺寸，不能让 Footer 高度跳变。
- Dialog 与 Backdrop 的层级必须高于页面粘性导航，低于 Toast 和 Tooltip。
- Dialog 不嵌套 Dialog；需要第二步确认时关闭当前表面或使用同一 surface 的状态转换。

## 9. Astryx 映射

| 设计语义 | 运行组件 / 参数 |
|---|---|
| 标准弹窗 | `@astryxdesign/core/Dialog` |
| 标题和关闭 | `DialogHeader` |
| 危险确认 | `AlertDialog` / `ConfirmActionDialog` |
| 手机全屏任务 | `Dialog variant="fullscreen"` |
| 表单行为 | `purpose="form"` |
| 信息查看 | `purpose="info"` |
| 内部高度 | `maxHeight` + Body `min-height: 0` |
| 关闭后焦点 | Dialog 的触发器焦点恢复机制 |

施工时要把动作栏作为 Dialog 的 Footer 同级内容传入布局组件；不要继续把 `.lc-dialog-actions` 放在可滚动 Body 末尾。现有 React 页面本轮不在本规范任务中修改，后续施工以本文件和 Blueprint 10 为准。

## 10. 验收清单

- [ ] 编辑评论在桌面、平板、390px、320px 下无 Header/Body/Footer 重叠。
- [ ] 长评论只滚动 Body，操作栏始终可见。
- [ ] 提交失败保留输入，错误文字不覆盖计数或按钮。
- [ ] 保存中不重复提交，按钮宽度稳定，关闭策略明确。
- [ ] 手机软键盘出现时 Footer 位于可视区域内，并尊重 safe-area。
- [ ] Escape、关闭按钮、Backdrop 行为与 `purpose` 一致。
- [ ] 关闭后焦点回到打开 Dialog 的触发器。
- [ ] 删除、举报、放弃编辑使用统一 Header、Footer、按钮顺序和危险语义。
- [ ] 成功使用原页面 Toast/局部状态，不出现成功弹窗套弹窗。
