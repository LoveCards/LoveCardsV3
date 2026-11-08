---
name: LoveCards Admin
description: 可信、克制且可追溯的社区治理与系统运维后台
colors:
  primary-indigo: "#3F51B5"
  secondary-amber: "#FFC107"
  accent-indigo: "#3F51B5"
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
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.3
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
- 高风险操作显示影响范围、可恢复性和审计提示；仅举报结论等真实业务字段收集原因，不增加通用原因输入。
- 150–250ms 状态过渡；减少动态效果时立即切换。

## Style lock v1（2026-08-23）

图 1 工作台参考图是当前管理端唯一的视觉外壳基线：白色顶部应用栏、白色/冷灰侧栏、Indigo 当前项和主动作、平面白色内容表面、细分隔线、紧凑表格密度。其他参考图只补充列表、详情、审计和配置的结构，不覆盖图 1 的外壳配色或导航语义。

完整 token、尺寸、组件语法和偏离禁止项见 [ADMIN-UI-STYLE-LOCK.md](../../docs/engineering/ADMIN-UI-STYLE-LOCK.md)。施工阶段如需改变颜色、字体、圆角、密度或外壳布局，必须先更新该风格锁定文件。

## 原生组件统一规则（2026-09-17）

- 以 Vuetify 原生组件为设计基准，默认值集中在 `nuxt.config.ts`；不为普通页面重新设计控件。
- 常驻内容使用原生 flat + border 卡片（白底、轻描边）、默认圆角、无阴影；菜单与对话框保留原生 elevated 浮层，不叠加边框。
- 表单使用 outlined / comfortable；列表搜索和批量栏通过共享默认值使用 compact，表格使用 comfortable。
- 列表共享搜索、辅助操作、视图设置和按需出现的选中区；创建入口不依赖行选择。
- 配置对象卡片统一标题、说明、底部动作结构，默认或启用状态由文字标签表达，不染色整个卡片。
- 卡片、表单内边距使用原生 16px；按钮默认尺寸，主提交 flat、次动作 outlined、辅助导航 text；不添加页面级高度覆盖。
- 配置目录不套额外外框；设置表单只保留一层内容卡片。特殊数据展示才允许有独立组件设计。

## 内容列表的阅读层级（2026-09-18）

评论与卡片列表先呈现正文，作者作为正文下方的辅助信息；默认列为内容、发布时间、状态、操作。ID、计数、封面和其他诊断列仍可通过视图设置选择，已有列偏好不清空。正文占主要宽度、最多预览两行，不使用整行加粗或彩色框强调普通内容。正常状态用文字，待审核／封禁才使用明显状态标签。每行保留一个文字主动作，其余动作进入原生菜单；危险操作仍使用原确认流程。

减少注意力竞争优先于增加装饰；本规则先应用于这两页，不扩大到无关页面。

验证：`npm run check`、`npm run build:admin` 通过；`content-hierarchy.spec.ts` 的 9 项浏览器回归在生产构建预览上通过，覆盖 1440px / 390px、已保存列、只读操作、删除确认和评论置顶资格。截图使用拦截的样例数据，未修改数据库；开发服务的首次浏览器验收因加载超时中止，未计为通过。

## 用户与恢复记录列表（2026-09-18）

用户列表默认合并头像、用户名和账号为身份列，角色、注册时间与状态作为辅助信息。正常状态使用文字；每行保留编辑或查看主入口，账号安全与删除进入原生菜单，并保持原有目标保护条件。已保存的独立头像、账号等列仍可使用，避免在合并身份列重复展示。

用户端找回密码保留；管理端不展示独立恢复记录列表。恢复申请和结果在审计日志中追溯，用户详情继续提供有权限的账号安全操作。

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

- **Headline**（600，1.5rem，1.3）：页面标题，仅一处；后台依靠上下文与间距建立层级，不使用展示型大标题。
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

使用现有顶部应用栏与能力过滤后的侧边导航。工作台是一级直达入口，其余页面归入“内容与社区、用户与权限、站点设置、系统运维”四个任务组；默认只展开当前组，不显示无独立页面的中间分区标题。当前项由选中背景和文本共同表达；窄屏折叠抽屉，页面内容不依赖侧栏保持可理解。

### Operational Timeline

举报、升级、投递和审计使用统一时间线：事件名称、操作者、时间、状态变化与必要备注按时间排序，敏感 payload 默认折叠并脱敏。

### Data Tables and Saved Views

资源列表统一由 `AdminResourceTableShell` 组合 Vuetify 数据表、结果栏和 `AdminTableViewControls`。默认保留 5–8 个任务核心列，资源身份与操作列固定不可隐藏。搜索、筛选、排序、分页进入 URL；列设置和最多 12 个命名视图按当前管理员 ID 存入浏览器，不允许不同账号共享同一个持久化键。

### List Interaction Consistency

- 表头与“搜索与排序”弹窗共享排序状态；应用或重置弹窗不清除页面上的状态、角色等独立筛选。
- 用户列表支持账号状态与角色的服务端筛选，正常状态 `0` 不能当作空值；恢复记录支持待验证、已使用、已过期筛选。条件进入 URL，刷新可恢复。
- 危险操作确认优先展示资源身份和实际影响。删除用户明确用户名、账号、撤销登录会话以及软删除限制；批量状态切换解释混合状态的结果。
- 卡片与评论的单向审核称为“通过审核”，不暗示存在取消审核；用户审核仍按实际双向切换规则说明。

### Configuration Sections

配置页使用 `AdminConfigSection` 按“基础、业务、高级”建立层级；Secret 由 `AdminSecretConfigField` 遮罩并遵循服务端受控写入语义。任何修改进入 dirty 状态后，站内导航、刷新或关闭页面都必须通过 `useAdminUnsavedChanges` 提示；保存成功恢复干净状态。

参数编辑页的保存范围与未保存提示集中到 `AdminConfigSaveBar`，不重复叠加顶部警告。独立保存范围的标签标记各自未保存状态；路由参数切换同样受离开保护，查询参数变化不强制中断编辑。

### Audit Reasons

管理端不收集通用“操作原因”。普通编辑与高风险动作均由后端自动记录操作者、动作、资源、变更前后快照和请求链路；危险操作仅通过影响说明与明确确认文案防误触。举报理由、审核结论等真实业务字段仍由各自业务模型负责，不得复用通用原因字段。

## Do's and Don'ts

### Do:

- **Do** 复用 Vuetify、`@lovecards/sdk`、统一通知和 capability 导航。
- **Do** 为加载、空数据、错误、降级和无权限提供不同状态。
- **Do** 在危险操作确认中显示资源、影响范围、可恢复性和审计说明。
- **Do** 让表格在窄屏横向滚动，并保留可访问名称。
- **Do** 对私信、草稿、收藏和历史默认只展示聚合信息。

### Don't:

- **Don't** 把后台做成消费者内容流或装饰性 SaaS 数据大屏。
- **Don't** 建立任意浏览用户隐私的监控台；敏感内容只在举报或明确授权下展开。
- **Don't** 用成功提示掩盖降级、积压、失败或权限不足。
- **Don't** 为每个模块重复发明表格、状态时间线和危险操作。
- **Don't** 使用渐变文字、玻璃拟态、彩色侧边粗条、32px 以上卡片圆角或边框加宽模糊阴影的幽灵卡片。
