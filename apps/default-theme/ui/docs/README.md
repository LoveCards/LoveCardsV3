# 设计文档导航

## 系统入口

- [`../standards/SYSTEM-ARCHITECTURE.md`](../standards/SYSTEM-ARCHITECTURE.md)：规范权威顺序、设计层级、组件选择和治理流程。
- [`../DESIGN.md`](../DESIGN.md)：视觉 Token 与总体语言。
- [`../standards/FOUNDATIONS.md`](../standards/FOUNDATIONS.md)：基础值、布局断点、材质和动效。
- [`../standards/PRIMITIVES.md`](../standards/PRIMITIVES.md)：LoveCards 通用组件行为。

## 注册表

- [`registries/BLUEPRINTS.md`](./registries/BLUEPRINTS.md)：蓝图状态、入口及 React 路由映射。
- [`registries/PAGES.md`](./registries/PAGES.md)：页面与状态覆盖范围。
- [`registries/CARD-PATTERNS.md`](./registries/CARD-PATTERNS.md)：卡片表现、数据与交互模式。
- [`registries/SDK-CAPABILITIES.md`](./registries/SDK-CAPABILITIES.md)：当前用户前端可使用的 SDK 能力。
- [`registries/ASTRYX-COMPONENTS.md`](./registries/ASTRYX-COMPONENTS.md)：Astryx 全组件用途、采用状态和 LoveCards 约束。
- [`registries/PATTERNS.md`](./registries/PATTERNS.md)：业务复合 Pattern、变体和页面所有权。

## 交互规范

- [`specifications/INTERACTIONS.md`](./specifications/INTERACTIONS.md)：搜索、评论与移动壳层交互。
- [`specifications/DIALOGS.md`](./specifications/DIALOGS.md)：Dialog、Modal、Alert、移动 Sheet、焦点与滚动边界。
- [`specifications/COMPOSE.md`](./specifications/COMPOSE.md)：发布形态与媒体工作区。
- [`specifications/MEDIA-FIT.md`](./specifications/MEDIA-FIT.md)：图片、视频与 GIF 的容器适配。
- [`specifications/SAVE-ACTION.md`](./specifications/SAVE-ACTION.md)：收藏动作与失败回滚。
- [`specifications/ERROR-FEEDBACK.md`](./specifications/ERROR-FEEDBACK.md)：表单、上传、加载与即时动作的错误翻译和恢复规则。
- [`specifications/FEEDBACK-AND-STATUS.md`](./specifications/FEEDBACK-AND-STATUS.md)：FieldStatus、Banner、Toast、Empty、ResourceState 和加载状态决策。
- [`specifications/FORMS-AND-UPLOADS.md`](./specifications/FORMS-AND-UPLOADS.md)：字段、校验、异步提交、文件上传和头像编辑任务。
- [`specifications/LAYOUT-RESPONSIVE.md`](./specifications/LAYOUT-RESPONSIVE.md)：页面容器、Feed gutter、响应式重排和 Skeleton 几何。
- [`specifications/NAVIGATION.md`](./specifications/NAVIGATION.md)：路由、Tab、选择、筛选、返回和焦点规则。
- [`specifications/SETTINGS.md`](./specifications/SETTINGS.md)：设置分类、SettingsGroup、扩展门槛和响应式布局。

## 落地资料

- [`implementation/COMMUNITY-SDK-MAP.md`](./implementation/COMMUNITY-SDK-MAP.md)：社区页面与 SDK 用例映射。
- [`implementation/BACKEND-HANDOFF.md`](./implementation/BACKEND-HANDOFF.md)：需要后端正式能力支持的交互。
- [`implementation/MIGRATION-PLAN.md`](./implementation/MIGRATION-PLAN.md)：从设计真源到 React 的分阶段迁移顺序与完成门槛。

## 设计决策

`decisions/` 只保存仍影响当前设计的决策；已完成的阶段计划和审阅镜像放在 `archive/`。决策不重复规范正文，只解释为什么采用当前方向。
