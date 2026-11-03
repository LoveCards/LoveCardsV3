# LoveCards Design Context

LoveCards 是多前端仓库，各运行界面保留自己的设计真源，避免将管理端的密度和交互直接套用到社区主题。

## Admin

- 产品与交互原则：`apps/admin/PRODUCT.md`
- 视觉系统与组件规范：`apps/admin/DESIGN.md`
- 架构边界：`docs/engineering/ADMIN-ARCHITECTURE.md`

管理端使用现有 Vuetify 与 Indigo 身份，界面可信、克制、高效。所有页面必须覆盖加载、空数据、错误、权限不足和降级状态；高风险动作必须显示影响范围、原因与审计提示；私信、草稿、收藏和历史默认只展示聚合信息。

## Default Theme

默认主题的运行代码与设计参考分别位于 `apps/default-theme/src` 和 `apps/default-theme/ui`。运行代码不得引用 `ui`，主题的视觉规范由该应用内文档和 token 管理。

## Shared Rules

- 产品界面使用固定字号层级，不使用随视口缩放的展示字体。
- 状态不能只依赖颜色表达；焦点、键盘和减少动态效果必须可用。
- 复用现有图标、控件和设计 token，不为同类动作创造第二套交互。
- 禁止渐变文字、装饰性玻璃拟态、嵌套卡片和无法解释的动画。
