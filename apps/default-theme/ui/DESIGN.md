---
name: LoveCards System Material Feed
description: 以系统级精度承载真实内容的社区信息流设计系统
colors:
  primary-rose: "#c92c60"
  highlight-rose: "#e63d63"
  rose-soft: "#fff0f4"
  canvas: "#f5f5f7"
  surface: "#ffffff"
  surface-subtle: "#f0f1f4"
  ink: "#1d1d1f"
  ink-muted: "#6e6e73"
  separator: "rgba(60,60,67,.16)"
  danger: "#c93642"
  success: "#24835b"
  warning: "#9a6700"
  canvas-dark: "#101216"
  surface-dark: "#171a20"
  surface-subtle-dark: "#20242c"
  ink-on-dark: "#f5f7fa"
  ink-muted-on-dark: "#a7b0bd"
  separator-dark: "rgba(255,255,255,.12)"
  rose-soft-dark: "#2b171d"
typography:
  headline:
    fontFamily: "Noto Sans SC, PingFang SC, Microsoft YaHei, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "24px"
    fontWeight: 800
    lineHeight: 1.25
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Noto Sans SC, PingFang SC, Microsoft YaHei, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "17px"
    fontWeight: 700
    lineHeight: 1.4
    letterSpacing: "-0.01em"
  body:
    fontFamily: "Noto Sans SC, PingFang SC, Microsoft YaHei, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: 1.65
    letterSpacing: "normal"
  body-compact:
    fontFamily: "Noto Sans SC, PingFang SC, Microsoft YaHei, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: "normal"
  label:
    fontFamily: "Noto Sans SC, PingFang SC, Microsoft YaHei, system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "12px"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "normal"
rounded:
  control: "9px"
  field: "10px"
  media: "12px"
  card: "12px"
  panel: "14px"
  pill: "999px"
spacing:
  micro: "4px"
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "24px"
  xl: "32px"
  wide: "48px"
components:
  button-primary:
    backgroundColor: "{colors.primary-rose}"
    textColor: "{colors.surface}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
    height: "40px"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
    height: "40px"
  field-default:
    backgroundColor: "{colors.surface-subtle}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.field}"
    padding: "10px 14px"
    height: "44px"
  chip-selected:
    backgroundColor: "{colors.rose-soft}"
    textColor: "{colors.primary-rose}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "7px 12px"
    height: "32px"
  feed-card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body-compact}"
    rounded: "14px desktop / 11px mobile"
    padding: "0"
  quote-card:
    backgroundColor: "{colors.rose-soft}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    rounded: "{rounded.card}"
    padding: "16px"
---

# Design System: LoveCards System Material Feed

## Overview

**Creative North Star: “System Material Feed / 系统材质信息流”**

LoveCards 像一套成熟操作系统中的社区信息流：结构熟悉、反馈精确、材质克制，用户首先看见内容和人，而不是设计师的炫技。页面延续现有原型的浅灰画布、白色内容表面、玫瑰强调色和顶部导航布局；高级感来自同心几何、光学校准、信息密度与状态连续性，不来自大面积玻璃、广角阴影或夸张动画。

信息流同时吸收两种产品逻辑：像小红书一样快速判断图片、视频和生活经验是否值得停留；像 Twitter/X 一样始终交代作者、来源、时间、引用与讨论上下文。二者使用同一套语义骨架——作者/来源 → 内容 → 媒体 → 操作——但内容类型决定卡片轮廓，禁止把所有内容塞进同一种白色圆角矩形。

**Key Characteristics:**

- 内容主导，系统材质退后；常驻内容表面不透明，玻璃只属于媒体上的临时控件。
- 整体布局安静稳定，张力集中在文字、单图、图集、视频、GIF、引用和讨论等卡片家族。
- 默认扁平，通过色调变化、1px 位移和连续展开表达深度。
- 一套中文友好的系统无衬线字体覆盖界面、正文和数据。
- 桌面、平板和手机是同一系统的结构适配，不是放大或缩小同一张页面。

### Layout and responsive behavior

- **Wide / Desktop（≥ 1051px）：**页面容器最大宽度为 1240px；顶部栏为 68px，频道栏为 52px。发现流使用 3–4 列自适应卡片与 280px 辅助栏；正文阅读轴保持有限宽度，辅助栏不挤压主内容。
- **Tablet（761–1050px）：**隐藏辅助栏，主流使用 3 列发现卡或单列关系流；顶部结构保持，触控目标不缩小。
- **Mobile（360–760px）：**顶部栏为 56px、频道栏为 44px、底部导航为 64px。发现流使用双列紧凑卡，关注流、讨论和详情使用单列标准卡；小于 360px 时允许降为单列。
- **Card profiles：**紧凑发现卡理想宽度为 180–280px；标准关系卡为 520–680px；详情阅读轴不超过 760px。语义顺序不因断点变化，紧凑卡只允许把作者信息折叠到下方元数据栏，不能删除来源。

**The Preserve-the-Shell Rule.** 保留现有布局与玫瑰配色基底，创新只发生在卡片家族、材质状态和连续交互中。

**The Content-Determines-Silhouette Rule.** 先识别内容类型，再选择轮廓、密度与操作；绝不先画一张万能卡片，再把所有内容硬塞进去。

## Colors

这是一套偏冷、低色度的中性画布配一条玫瑰状态轴。玫瑰色用于行动、当前选择与关系反馈，不能成为页面装饰底色。

### Primary

- **Accessible Deep Rose**（`primary-rose`）：主按钮、焦点、选中标签和可阅读的玫瑰文字。白字与该色对比度为 5.23:1，可用于普通字号的填充按钮。
- **Signal Rose**（`highlight-rose`）：喜欢图标、轻量计数变化和小面积高光。它在白色上的对比度为 4.03:1，禁止作为普通正文或白字按钮背景。
- **Rose Mist**（`rose-soft`）：选中背景、引用材质和局部关系反馈；面积可见但饱和度必须低。

### Neutral

- **Cool Canvas**（`canvas`）承载页面背景；**True Surface**（`surface`）承载主要内容；**Subtle Surface**（`surface-subtle`）承载输入、骨架和局部选中层。
- **Near-black Ink**（`ink`）用于主要阅读；**Slate Ink**（`ink-muted`）用于仍需阅读的元数据。Slate Ink 在白色上为 5.07:1、在画布上为 4.66:1，不能继续变浅。
- **Quiet Separator**（`separator`）只用于需要解释结构的 1px 分隔，不为每个区域描边。

### Semantic

- **Danger / Success / Warning** 仅表达失败、成功与待处理状态；必须同时配图标或文字，不能只靠颜色。

### Dark correspondence

暗色模式使用 `canvas-dark`、`surface-dark`、`surface-subtle-dark`、`ink-on-dark`、`ink-muted-on-dark`、`separator-dark` 与 `rose-soft-dark` 的一一对应关系。它是相同层级的暗色翻译，不是加霓虹、紫色渐变或更重玻璃。

**The Two-Rose Rule.** 深玫瑰负责可访问操作，亮玫瑰负责轻量情绪反馈；二者不可互换。

**The Content-Owns-Color Rule.** 照片、视频和用户创作可以鲜艳，系统 UI 保持中性；同一屏的系统强调色面积通常不超过 10%。

## Typography

**Display Font:** 不设独立展示字体；页面标题继续使用系统无衬线栈。
**Body Font:** Noto Sans SC / PingFang SC / Microsoft YaHei / system-ui。
**Character:** 像系统界面一样清晰、紧凑、稳定。层级依靠字号、字重、行高和留白，不依靠多字体拼贴。

### Hierarchy

- **Headline**（800，24px，1.25，-0.02em）：页面标题和详情主标题；移动端可降为 20px，但不使用流式夸张缩放。
- **Title**（700，17px，1.4，-0.01em）：标准卡标题、面板标题和重要作者名；紧凑发现卡使用 13–15px。
- **Body**（400，15px，1.65）：标准正文、讨论和引用；长正文行宽控制在 65–75 个字符。
- **Body Compact**（400，13px，1.6）：发现卡摘要、紧凑列表和次级说明。
- **Label**（600，12px，1.4）：时间、来源、计数、标签和控件文字；不使用全大写与宽字距制造“高级感”。

中文标点、英文账号、数字和 emoji 必须在同一行高下稳定对齐。用户名允许一行截断，正文不允许因作者名过长而挤掉时间和上下文；数字使用等宽数字特性时仅限计数和时长。

**The One-Family Rule.** 产品界面只用一套系统无衬线字体；任何展示字体都必须证明它服务内容，而不是装饰控件。

**The Reading-Before-Density Rule.** 紧凑只减少容器留白，不降低正文对比度，也不把可阅读字号压到 12px 以下。

## Elevation

系统默认扁平。层级首先由画布与表面的色调差、间距、裁切关系和 1px 分隔表达；阴影只用于确实离开文档流的浮层，或为白色卡片在同色背景上提供极轻的边界。常驻内容卡不得同时使用明显描边和宽模糊阴影。

### Shadow vocabulary

- **Card Ambient**（`0 1px 3px rgba(20, 25, 35, 0.05)`）：仅在白卡与浅画布边界不足时使用；与 1px 细线二选一，不能叠加成“幽灵卡”。
- **Dark Card Ambient**（`0 1px 3px rgba(0, 0, 0, 0.22)`）：暗色表面的轻微分离，不扩展为发光。
- **Floating Control**（`0 4px 8px rgba(20, 25, 35, 0.12)`）：菜单、媒体控制组和临时浮层；关闭后阴影随组件消失。
- **Focus Ring**（`0 0 0 3px rgba(217, 47, 104, 0.18)`）：键盘焦点的外圈，配合 2px 深玫瑰轮廓。

玻璃材质仅允许出现在图片或视频上的播放、音量、页码与更多操作，并使用内容自适应的深色半透明底。正文、侧栏、导航卡和输入框禁止默认玻璃化。

层级顺序固定为：内容 0 → 粘性导航 30/40 → 下拉与媒体浮层 60 → 模态背景 70 → 模态 80 → Toast 100 → Tooltip 110。禁止任意使用 999 或 9999。

**The Flat-by-Default Rule.** 静止卡片不漂浮；悬停最多上移 1px 或提升 2% 亮度，按下后立即回到物理接触感。

**The Glass-is-a-Control Rule.** 玻璃是覆盖媒体的临时操作材质，不是 LoveCards 的背景皮肤。

## Components

### Buttons

- **Shape:** 系统控制圆角（9px），常规高度 40px，移动端触控热区至少 44×44px；只有纯图标控制允许等宽方形。
- **Primary:** `primary-rose` 填充、白字、左右 16px 内边距。悬停通过降低约 2% 明度反馈，不能加宽阴影；按下使用 `scale(.97)`。
- **Secondary:** 白色或当前表面、近黑文字、1px 静默分隔线；同一区域不能与主按钮争夺视觉重量。
- **Ghost:** 透明底，默认使用次文本色；悬停出现低色度表面，不在静止状态绘制圆角胶囊。
- **Focus / Disabled / Loading:** 焦点使用统一 Focus Ring；禁用降低对比并取消动作；加载保留按钮宽度并把标签替换为进度语义，禁止左右跳动。

### Chips

- **Style:** 只有标签、筛选和短状态使用全圆角。未选中为中性表面或无底；选中使用 Rose Mist 与 Accessible Deep Rose。
- **State:** 筛选 Chip 可切换，动作 Chip 必须有动词；不可把所有元数据都做成彩色胶囊。

### Cards / Containers

- **Corner Style:** 信息流卡使用 `14px` 桌面 / `11px` 手机，详情媒体使用 `12px`，常规大面板使用 `14px`。内外嵌套遵守同心几何：内层圆角等于外层圆角减去可见内边距。
- **Loading geometry:** 加载面必须继承完成态最近一层内容表面的圆角、宽度和裁切。信息流骨架与 `FeedStory` 同构；通用资源加载面使用 `14px` 桌面 / `11px` 手机。全屏媒体以及手机端明确贴边的详情或编辑器保持 `0`，不得被通用加载圆角覆盖。
- **Background:** 普通内容使用不透明白色或极浅语义材质；引用、讨论和社区邀请通过色调、裁切或内容比例形成不同轮廓，不靠额外套一张内卡。
- **Shadow Strategy:** 默认用色调或 1px 分隔；Card Ambient 只作边界补偿。
- **Internal Padding:** 紧凑卡 10–12px，标准卡 14–16px，详情 20–24px；媒体可贴边，但文字与操作必须回到统一内容轴。

### Inputs / Fields

- **Style:** 高度 44px，10px 圆角，Subtle Surface 背景，1px 静默分隔。占位文字也必须达到 4.5:1；提示不能替代 Label。
- **Focus:** 边界切换为 Accessible Deep Rose 并出现 Focus Ring，不改变尺寸。
- **Error / Disabled:** 错误就地显示原因与恢复动作；禁用仍保留可读标签，禁止只把透明度降到无法阅读。

### Navigation

桌面保持顶部栏与频道栏；移动端保持顶部语境和四到五个稳定底部入口。当前频道使用文字加短下划线或 Rose Mist 背景，不能只靠颜色。粘性导航允许 96–97% 不透明材质，但文字区域必须保持清晰，不能变成装饰玻璃条。

### Feed Card Family

所有信息流卡共享作者/来源、内容、媒体和操作语义，但使用不同轮廓：文字卡强调阅读留白；单图卡让媒体成为主表面；图集卡使用同心拼贴；视频和 GIF 只在媒体上叠加系统控制；引用卡使用低饱和材质；讨论卡显示参与关系；创作者/社区卡以身份和明确行动为主。完整尺寸、媒体比例和状态矩阵见 `standards/CARD-SYSTEM.md`。

### State and motion

常规状态过渡为 150–250ms，使用 `cubic-bezier(0.22, 1, 0.36, 1)`；按下反馈为 100–140ms。喜欢、收藏、播放和展开必须保留组件身份，展开卡优先使用匹配位置与匹配圆角的连续转换。所有动画可被中断；`prefers-reduced-motion: reduce` 下取消位移、缩放和路径动画，只保留即时状态或短交叉淡化。

**The Matched-Identity Rule.** 卡片进入详情时保持媒体、作者与操作的视觉身份，不闪白、不从无关方向飞入，也不让用户丢失原信息流位置。

## Do's and Don'ts

### Do:

- **Do** 保留 `canvas`、`surface`、`ink` 与玫瑰状态轴作为全端共同基底。
- **Do** 让文字、单图、图集、视频、GIF、引用、讨论和社区邀请拥有可辨识但同源的轮廓。
- **Do** 使用 Accessible Deep Rose 承担按钮和普通字号玫瑰文字，使用 Signal Rose 承担喜欢等小面积高光。
- **Do** 保证主要正文至少 4.5:1 对比度、键盘焦点清晰、触控热区至少 44×44px，并为媒体提供替代文本、字幕或文字说明。
- **Do** 在桌面端利用宽度承载多列发现与上下文侧栏，在移动端用双列发现和单列讨论保持任务清晰。
- **Do** 使用结构匹配的 Skeleton、局部可恢复错误和有下一步的空状态。

### Don't:

- **Don't** 做“以粉红、爱心和暧昧文案为主导的恋爱社交 App”；玫瑰色是状态语言，不是主题装饰。
- **Don't** 做“高刺激、强占屏、依赖连续自动播放的短视频娱乐平台”；视频默认不抢夺声音与焦点。
- **Don't** 做“充满面板、指标和企业术语的 SaaS 后台界面”；辅助栏只提供上下文，不把社区变成仪表盘。
- **Don't** “通过密集等级、弹窗奖励、炫光和数值膨胀制造强手游化体验”。
- **Don't** “直接复制小红书或 Twitter/X 的品牌颜色、图标和页面外观”；只吸收发现效率、关系结构和讨论上下文。
- **Don't** 把所有内容做成相同尺寸、相同白底、相同标题和按钮排列的万能卡片。
- **Don't** 把玻璃、背景模糊、渐变文字、彩色侧条或 24px 以上过度圆角作为常驻装饰。
- **Don't** 在同一常驻卡片上叠加明显 1px 描边与 16px 以上宽模糊阴影。
- **Don't** 用弹跳、橡皮筋、无法中断的页面入场动画，或忽略减少动态效果设置。
