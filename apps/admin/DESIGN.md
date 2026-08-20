---
name: LoveCards Admin
description: 可信、克制且可追溯的社区治理与系统运维后台
colors:
  primary-indigo: "#3F51B5"
  secondary-amber: "#FFC107"
  accent-rose: "#FF4081"
  success-green: "#43A047"
  warning-orange: "#FF9800"
  error-red: "#F44336"
  info-blue: "#2196F3"
  surface: "#FFFFFF"
  surface-muted: "#F5F5F5"
  divider: "#E0E0E0"
  ink: "#212121"
typography:
  headline:
    fontFamily: "Roboto, system-ui, sans-serif"
    fontSize: "2rem"
    fontWeight: 700
    lineHeight: 1.25
  title:
    fontFamily: "Roboto, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.35
  body:
    fontFamily: "Roboto, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Roboto, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.35
rounded:
  sm: "4px"
  md: "8px"
  lg: "12px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary-indigo}"
    textColor: "{colors.surface}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
  button-danger:
    backgroundColor: "{colors.error-red}"
    textColor: "{colors.surface}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "16px"
---

# Design System: LoveCards Admin

## Overview

**Creative North Star: "可追溯的控制台"**

LoveCards Admin 是在桌面工作环境中长时间使用的任务型界面。它继承现有 Vuetify 与 Indigo 主题，依靠熟悉的导航、表格、标签页、抽屉和确认对话框组织高密度信息。视觉层级服务于判断：现在是什么状态、为什么、下一步能做什么、操作会留下什么记录。

系统拒绝装饰性 SaaS 数据大屏、任意浏览用户隐私的监控台和伪装成功的降级状态。移动端可以完成查询和轻量操作，高风险批处理与复杂诊断以桌面端为主要场景。

**Key Characteristics:**

- Indigo 主色维持现有身份，语义色只表达状态。
- 表格和详情分层，不使用层层嵌套卡片。
- 关键状态同时使用文字、图标和颜色。
- 高风险操作始终显示影响范围、原因输入和审计提示。
- 150–250ms 状态过渡；减少动态效果时立即切换。

## Colors

主色负责导航、当前选择与主要动作；玫红强调色只用于有限的重点操作和现有品牌提示；绿、橙、红、蓝严格保留给语义状态。

### Primary

- **控制台靛蓝**：应用栏、主要动作、当前导航和可见焦点。

### Secondary

- **状态琥珀**：需要注意但未失败的状态，不作为大面积背景。
- **品牌玫红**：更新提示和少量强调动作，禁止装饰性泛用。

### Neutral

- **任务白面**：页面和内容表面。
- **冷静浅灰**：导航头部、工具栏和次级分区。
- **结构分隔灰**：表格边界与分隔线。
- **高对比墨色**：正文和数据。

**The Semantic Color Rule.** 红、橙、绿、蓝只能表达错误、警告、成功和信息，不能拿来装饰模块。

## Typography

**Display Font:** Roboto（回退到 system-ui）
**Body Font:** Roboto（回退到 system-ui）

**Character:** 单一无衬线字体维持后台工具的熟悉感。字号层级紧凑，数据密度高但标签和正文仍保持清晰。

### Hierarchy

- **Headline**（700，2rem，1.25）：页面标题，仅一处。
- **Title**（600，1.25rem，1.35）：详情分区、对话框和抽屉标题。
- **Body**（400，1rem，1.5）：说明、错误与上下文，长文本控制在 65–75ch。
- **Label**（500，0.875rem，1.35）：字段、表头、芯片和操作说明。

**The One Family Rule.** 管理端不引入展示字体或等宽字体作为普通标签；日志标识可以使用系统等宽回退。

## Elevation

默认依靠背景层级、分隔线和 Vuetify 的低层级 elevation 表达结构。阴影只用于应用栏、浮层、菜单和对话框，不给每个内容块同时添加边框与宽模糊阴影。

**The Flat-By-Default Rule.** 常驻内容表面保持平整；只有悬浮层和正在交互的元素获得提升。

## Components

### Buttons

- **Shape:** 熟悉且紧凑的轻微圆角（4px）。
- **Primary:** Indigo 实底用于当前页面唯一主动作。
- **Hover / Focus:** 150–250ms 色阶变化；键盘焦点必须可见。
- **Danger:** 红色只用于确认阶段，触发按钮优先使用中性或文字样式。

### Chips

- **Style:** 小尺寸、短文案，同时展示明确状态名称。
- **State:** 颜色与图标是辅助信息；文案是最终语义。

### Cards / Containers

- **Corner Style:** 轻微圆角（8px，复杂面板最多 12px）。
- **Background:** 白色主表面、浅灰次级分区。
- **Shadow Strategy:** 常驻内容无装饰阴影。
- **Border:** 表格或需要明确边界的内容使用单一浅灰描边。
- **Internal Padding:** 默认 16px，复杂详情分区 24px。

### Inputs / Fields

- **Style:** Vuetify outlined/underlined 体系，密度与相邻页面保持一致。
- **Focus:** Indigo 或品牌强调色焦点，必须有可见轮廓。
- **Error / Disabled:** 错误显示字段级中文说明；禁用态同时降低交互与视觉权重。

### Navigation

使用现有顶部应用栏与能力过滤后的侧边导航。当前项由选中背景和文本共同表达；窄屏折叠抽屉，页面内容不依赖侧栏保持可理解。

### Operational Timeline

举报、升级、投递和审计使用统一时间线：事件名称、操作者、时间、状态变化与必要备注按时间排序，敏感 payload 默认折叠并脱敏。

## Do's and Don'ts

### Do:

- **Do** 复用 Vuetify、`@lovecards/sdk`、统一通知和 capability 导航。
- **Do** 为加载、空数据、错误、降级和无权限提供不同状态。
- **Do** 在危险操作确认中显示资源、影响范围、原因和审计说明。
- **Do** 让表格在窄屏横向滚动，并保留可访问名称。
- **Do** 对私信、草稿、收藏和历史默认只展示聚合信息。

### Don't:

- **Don't** 把后台做成消费者内容流或装饰性 SaaS 数据大屏。
- **Don't** 建立任意浏览用户隐私的监控台；敏感内容只在举报或明确授权下展开。
- **Don't** 用成功提示掩盖降级、积压、失败或权限不足。
- **Don't** 为每个模块重复发明表格、状态时间线和危险操作。
- **Don't** 使用渐变文字、玻璃拟态、彩色侧边粗条、32px 以上卡片圆角或边框加宽模糊阴影的幽灵卡片。
