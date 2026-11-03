# LoveCards Blueprint → React 产品前端施工计划

> 历史归档，不是当前事实源。现行卡片类型以 [`../registries/CARD-PATTERNS.md`](../registries/CARD-PATTERNS.md) 为准，交互范围以 [`../specifications/INTERACTIONS.md`](../specifications/INTERACTIONS.md) 为准。

版本：2026-08-16
范围：`apps/default-theme` 内的用户前端，以及 `apps/default-theme/ui` 中的正式蓝图与设计规范。SDK 之外的后端、管理端和 C2C 交易链路不在本轮范围内。

## 1. 已确认的施工边界

- Blueprint 01–10 是页面外观、信息层级、内容密度和响应式结构的真源。
- React `/` 是唯一正式产品前端；蓝图索引只是设计审阅入口。
- Astryx 只提供可升级的行为与基础 Primitive。LoveCards Token 和页面 CSS 决定最终风格，组件库默认外观不得覆盖蓝图。
- 交互表面先在 Blueprint 10 冻结：搜索统一走 Typeahead，评论统一走 ChatComposer，移动端“我的”不与顶栏头像重复，卡片模式统一走注册表。
- SDK 只决定数据与操作能力。已有能力真实接入；没有能力的操作禁用并显示“暂未开放”，不得用 fixture 伪造成功。
- 桌面、平板和手机使用同一语义结构，但允许按蓝图发生结构性重排；不能简单缩放桌面页面。

## 2. 清理后的保留边界

保留：React 入口、Router、Provider、主题、Token、SDK client、资源状态、图标、FeedStory 语义骨架、通用测试框架、正式设计规范和 Blueprint 01–10。

删除：旧单文件原型、探索版归档、阶段审计日志、构建产物、`/ui-kit` 工程验收台、专用 fixture/测试断言和已经失效的 Phase 文档。

旧工程验证业务页已全部移除。`/` 使用 Blueprint 01 的正式 React 首页；尚未施工的路由只显示透明的 Blueprint 边界，不展示示例业务数据或模拟操作。每个页面必须按下表重新施工后才能标记完成。

## 3. Blueprint、路由与 SDK 对应

| Blueprint | React 路由 | 正式页面 | SDK 状态 |
|---|---|---|---|
| 01 · 首页信息流 | `/`、`/?feed=following`、`/?channel=:type` | 推荐、关注、频道、右侧上下文 | 内容列表与点赞可接；关注关系暂未开放 |
| 02 · 发现与搜索 | `/explore`、`/search`、`/search/:scope`、`/topic/:slug` | 发现、搜索起始/结果、话题详情 | 内容搜索与标签可接；关注话题/创作者暂未开放 |
| 03 · 内容详情 | `/post/:id`、`/post/:id/thread/:commentId`、`/media/:id` | 详情、评论线程、媒体查看器 | 详情、评论增删改与举报可接；稳定媒体 ID 仍受 SDK 契约限制 |
| 04 · 发布 | `/compose`、`/drafts`、`/drafts/:id` | 发布器、媒体上传、草稿与恢复编辑 | 内容创建/编辑、文件上传、云端草稿与自动保存可接 |
| 05 · 用户中心 | `/me`、`/user/:handle` | 本人主页、他人主页、内容分区 | 当前用户与公开资料可接；公开用户内容列表仍缺专用 SDK 查询 |
| 06 · 身份与关系 | `/me/edit`、`/user/:handle/following`、`/user/:handle/followers` | 编辑资料、关注/粉丝、举报状态 | 资料、关系、关注、拉黑与举报可接；黑名单列表仍未开放 |
| 07 · 消息 | `/inbox`、`/inbox/notifications`、`/messages`、`/messages/:conversationId`、`/messages/requests` | 通知、私信列表、会话、请求 | 通知与私信可接；通知流通过 SDK 快照并自动降级轮询；消息请求分类未开放 |
| 08 · 资料库与设置 | `/me/library/:section`、`/settings`、`/settings/:section` | 喜欢、收藏、历史、安全、隐私、偏好、外观 | 喜欢、收藏、历史、隐私、资料/密码/邮箱可接；单项取消收藏受 SDK 参数缺口限制 |
| 09 · 认证 | `/auth/:mode`、`/auth/recovery` | 登录、注册、游客、恢复流程 | 登录、注册、游客、会话与账号恢复可接 |

## 4. 工程结构目标

```text
src/
├─ app/          # 路由、Provider、SDK 初始化
├─ layouts/      # ProductShell、FocusShell、SettingsShell、AuthShell
├─ pages/        # 按 Blueprint 页面家族拆分
├─ features/     # feed、search、comments、compose、profile、auth 等交互
├─ components/   # 导航、媒体、身份、反馈等跨页面模式
├─ ui/           # Astryx 适配与无业务视觉原子
├─ services/     # SDK adapter、能力判断、错误归一化
├─ fixtures/     # 仅开发和测试显式使用
├─ styles/       # Token、基础样式、共享模式
└─ test/         # 路由、契约、状态、a11y 与视觉门禁
```

约束：不为单页创建抽象；不把不同页面套进同一个万能 `PageFrame`；不在组件内混入大段 fixture；不让服务层在 API 缺失时返回假成功。

## 5. 施工批次与门槛

### A. 产品壳层与数据边界

- 按 Blueprint 01 建立桌面顶栏、频道栏、移动底栏和内容宽度系统。
- 将 SDK、能力判断、显式 fixture 分离；消除 preview/fallback 假成功。
- 建立四类页面壳：内容流、聚焦详情、设置、认证。

验收：320/390/768/1024/1440 无横向溢出；产品导航无工程入口；未配置 API 时不会显示成功操作。

### B. Blueprint 01 + 03 · 内容主闭环

- 重做首页卡片家族、右侧上下文、频道切换和内容状态。
- 完成详情、评论线程、媒体查看器与 Feed → Detail → Back 上下文保持。
- 评论线程使用 `parent_id` 真实回复；投票先以 `data` 规则 + 评论过滤适配器实现，安全唯一投票另列后端能力。
- 先用这一批冻结 FeedCard、Identity、Media、ActionBar、CommentRow 的正式 API。

验收：桌面和移动与蓝图结构一致；卡片不再是万能白卡；列表/详情/点赞/评论按 SDK 真实工作。

### C. Blueprint 02 + 04 · 发现、搜索与发布

- 完成发现、搜索起始/结果、话题详情。
- 完成发布、上传、编辑、云端草稿恢复、自动保存与错误保留。

验收：搜索查询进入 URL；键盘可完成搜索和发布主路径；上传失败可恢复；没有云端草稿时明确提示。

### D. Blueprint 05 + 06 · 用户中心与身份

- 完成本人/他人主页、内容标签、编辑资料和关系页。
- 有 SDK 的资料字段完整保存；关注、粉丝、举报与屏蔽真实接入，公开用户内容缺口不展示假数据。

验收：本人/他人权限清晰；长用户名、空主页、私密/不存在状态完整；Edit Profile 桌面与手机均通过。

### E. Blueprint 07–09 · 消息、资料库、设置与认证

- Blueprint 07 真实接入通知与私信，通知流不可用时自动轮询；消息请求分类保留明确能力边界。
- Blueprint 08 真实接入喜欢、收藏、历史、隐私、资料、密码和邮箱；单项取消收藏显示 SDK 契约边界。
- Blueprint 09 真实接入登录、注册、游客、退出、会话与账号恢复。

验收：任何不可用功能都不能产生可点击假成功；认证错误、加载和成功跳转完整；设置项只在能够保存时可编辑。

### F. System Boards 与全站验收

- 覆盖 loading、empty、error、disabled、permission、offline、deleted、restricted。
- 亮/暗/系统主题，键盘、触控、读屏、减少动态效果和中文长内容回归。
- 每个正式路由生成 390px 与 1440px 对照截图，与对应 Blueprint 审阅。

验收：lint、test、build 全部通过；无 P0/P1；WCAG AA 核心路径通过；所有页面在 `PAGE-MATRIX.md` 中有明确完成证据。

## 6. 单页完成定义

一个页面只有同时满足以下条件才算完成：

1. 结构、密度、视觉语义与对应 Blueprint 一致。
2. 390px 与 1440px 无错位、遮挡、溢出或异常合团。
3. SDK 已有能力真实可用；SDK 缺失能力明确禁用。
4. default、loading、empty、error、disabled/permission 至少覆盖适用状态。
5. 唯一 H1、landmark、可见焦点、44×44px 触控热区、AA 对比度和减少动态效果通过。
6. 路由可刷新、前进/返回保持上下文，且没有死按钮或工程说明文案。
7. 自动测试与桌面/移动截图审阅均通过。
