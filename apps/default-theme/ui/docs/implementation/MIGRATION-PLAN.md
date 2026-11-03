# 默认主题设计系统迁移计划

> 状态：设计计划已冻结，运行迁移已完成主要页面切片；剩余 Toast 仅保留低风险成功/即时操作反馈，页面级资源状态与关键失败反馈已统一。迁移只按垂直切片进行；每个阶段完成后删除被替代的同义局部模式，不长期双轨。

## 当前施工进度（2026-08-22）

- Phase 1 已完成首个基础切片：运行层增加统一 scoped Banner adapter，`ResourceState` 已具备 empty、error、403、404、deleted、restricted、unavailable 和 offline 语义；个人内容 Tab 已迁入共享状态。
- Phase 2 的头像垂直切片已落地：本人头像打开 `FormDialog`，文件错误由 `FileInput` status 承担，任务错误使用 Dialog 内 Banner，成功关闭并 Toast；旧 `profile-avatar-feedback`、身份头上传说明和页面自造的隐藏原生上传入口已删除。
- Phase 1 页面状态已覆盖通知、主页、关系、隐私、资产、私信、草稿和会话；头像/用户名编辑、发布失败、会话失效也已接入统一反馈层级。低风险点赞、收藏、复制、分享等即时操作仍使用 Toast，符合“短暂、就地、无需恢复动作”的决策矩阵。

## 1. 目标状态

```text
ui/ design truth
  → tokens/theme
  → src/ui Primitive adapters
  → src/components + features Patterns
  → pages compose Patterns only
  → tests prevent local re-invention
```

本计划不授权修改 SDK 契约、手写请求、创建通知或伪造后端能力。数据边界仍以生成 SDK 和能力注册表为准。

## 2. 当前设计债务基线

运行实现已经存在可复用基础，但反馈和状态仍有多套局部表达：

- `ResourceState`、`profile-boundary`、`community-boundary` 和页面专属错误重复承担资源状态。
- `profile-avatar-feedback` 把上传错误注入身份头，破坏稳定布局。
- `community-inline-error`、`session-notice` 和多处 `<p role="alert">` 没有统一范围和组件语义。
- Toast 同时承担成功和部分错误，需要按反馈决策矩阵重新分类。
- Feed/Profile Skeleton 已有同源基础，但列表、关系、消息和设置仍需与完成态逐项对应。
- Blueprint 06 的独立资料编辑页与“主页直接编辑头像/姓名”的现行决策冲突。

这些是迁移输入，不在设计阶段顺手修改 `src/`。

## 3. 阶段顺序

### Phase 0 — 规范与登记表

交付：系统架构、Astryx 全组件登记表、反馈、表单上传、布局、导航、Pattern 注册表和头像任务蓝图。

退出条件：文档入口唯一、链接有效、术语一致，旧规范没有与新规则相反的要求。

### Phase 1 — Feedback 与 ResourceState

目标组件：`FieldStatus`、`Banner`、`Toast`、`EmptyState`、`ResourceState`、`Skeleton`。

迁移顺序：

1. 建立统一反馈 adapter 和 ResourceState 变体。
2. 替换页面专属 `community-boundary/profile-boundary` 的同义状态。
3. 统一 loading、empty、403、404、deleted、restricted、network 和 load-more。
4. 按矩阵审计所有 Toast；保留成功/短确认，迁出字段和关键失败。

退出条件：同一错误只出现一次；加载更多失败不清空内容；空态不吞 403/404；所有 live-region 播报不重复。

### Phase 2 — 表单与头像上传

目标组件：`FormDialog`、`FileInput`、`FieldStatus`、`AvatarUploadTask`。

迁移顺序：

1. 头像点击打开更换任务表面，不再在身份头插入反馈。
2. 文件类型/大小/损坏使用 FileInput status。
3. 网络、登录、权限和服务端失败使用 Dialog 内 scoped Banner。
4. 成功关闭 Dialog、更新头像并 Toast。
5. 复用同一上传规则审计发布媒体和消息附件。

退出条件：头像所有状态通过桌面/手机/键盘/读屏；移除 `profile-avatar-feedback` 及其测试依赖；身份头高度稳定。

### Phase 3 — AppShell、导航与 Tab

目标 Pattern：全局壳、真实当前用户头像、ProfileTabs、消息分类。

迁移顺序：桌面账号入口 → 手机唯一导航入口 → Profile Tab URL 状态 → 旧资产路由收口。

退出条件：本人主页 Tab 不重建壳；前进/后退恢复状态；顶部和底部无重复入口；真实头像/Fallback 一致。

### Phase 4 — Feed、主页与关系页布局

目标 Pattern：`ProfileCardFeed`、`RelationshipList`、共享容器 Profile。

迁移顺序：首页容器基线 → 本人/他人主页 → 喜欢/收藏/历史 → 关注/粉丝 → 搜索与话题。

退出条件：同一 Card 使用同一 FeedStory；所有 Feed 外 gutter 一致；320px 不贴屏、不裁切；Skeleton 与最终列数和比例一致。

### Phase 5 — 详情、评论和媒体

目标 Pattern：`CommentThread`、`AuthorIdentity comment`、`MediaViewer`、详情 ResourceState。

退出条件：作者/正文/动作热区独立；评论头像可导航且不改行布局；深链、删除占位、上传/发送失败可恢复。

### Phase 6 — 通知与私信

目标 Pattern：`NotificationItem`、`ConversationRow`、`MessageThread`。

退出条件：消息主入口指向 `/messages` 且私信只使用 Messages SDK；通知只保留 `/inbox` 的 `engagement` 与 `/inbox/system` 的 `system`，旧细分类路由完成重定向；不透明复合游标原样传递，当前分类贯穿 list/unread/readAll，返回项保留真实 category；action_url、actor/resource 状态、加载更多和发送失败全部覆盖，不猜路由、不解析提及。

### Phase 7 — 发布、设置、认证与长尾

目标 Pattern：发布表单、SettingsGroup、Auth ResourceState、危险操作。

退出条件：字段/任务/页面反馈分层一致；设置不再局部自造控件；认证错误可恢复；所有 Blueprint 状态完成对照。

### Phase 8 — 治理收口

- 删除已替代的局部 CSS 和组件。
- 给禁止模式增加静态检查或源代码测试。
- 更新 Blueprint 注册状态和页面矩阵。
- 完成全主题视觉、键盘、主题、响应式和构建检查。

## 4. 每个切片的固定步骤

1. 在规范和 Blueprint 中确认完成态与边界状态。
2. 写或更新测试锁定语义、键盘和结构。
3. 迁移一个 Pattern 及其首个页面消费者。
4. 迁移剩余同义使用点。
5. 删除被替代的局部实现。
6. 运行默认主题完整检查并进行浏览器视觉验收。

不能只增加新组件而保留所有旧 class；那会制造第三套体系。

## 5. 验证矩阵

| 维度 | 必检 |
|---|---|
| 路由 | `/`、`/me` 各 Tab、`/user/:handle`、详情、`/messages*`、`/inbox`、`/inbox/system`、旧通知分类重定向、发布、设置、认证 |
| 状态 | loading、empty、error、403、404、deleted、restricted、offline、load-more、success |
| 输入 | valid、invalid、disabled、submitting、server error、preserved input |
| 上传 | type、size、unreadable、uploading、network、permission、success |
| 视口 | 320、390、768、1050、1440px |
| 访问 | mouse、touch、Tab/Enter/Space/Escape、读屏名称、200% zoom |
| 偏好 | light、dark、system theme、reduced motion |
| 工程 | lint、unit tests、build、无控制台嵌套链接/水合警告 |

## 6. 优先级与依赖

P0：Feedback/ResourceState 与头像上传，因为它们会影响所有后续页面状态和当前用户可见问题。

P1：导航、Profile Tab、Feed/关系布局，建立跨页面一致性。

P2：详情评论、通知私信、发布设置和认证，迁移到已稳定的基础体系。

任何需要新后端能力的设计保持诚实占位，不能阻塞无关视觉迁移，也不能由前端发明兼容接口。

## 7. 完成定义

只有同时满足以下条件，迁移才算完成：

- 页面不再拥有与 Primitive/Pattern 同义的局部组件。
- 所有状态使用正确反馈层级，错误包含恢复动作。
- Feed、身份、关系、列表和 Skeleton 跨页面一致。
- 规范、蓝图、注册表、测试和实际页面同步更新。
- `npm run check:default-theme` 通过，浏览器无警告，移动和键盘验收通过。
