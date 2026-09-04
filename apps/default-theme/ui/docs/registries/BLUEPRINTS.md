# LoveCards 蓝图注册表

本文件是蓝图状态和入口的唯一事实源。审计基准为当前 `apps/default-theme/src` 实现与现行设计规范。

## 状态

- `canonical`：可直接作为视觉与结构施工依据。
- `needs-sync`：已确认与当前实现或现行规范冲突，必须先更新蓝图。
- `review-required`：页面已实现，但尚未完成桌面/移动逐项对照，不能默认视为最终版本。
- `archive`：仅供追溯，不得作为施工入口。

## 页面蓝图

| 编号 | 状态 | 入口 | React 路由 | 审计结论 |
|---|---|---|---|---|
| 01 | `needs-sync` | [`blueprint-01-home-feed.html`](../../blueprints/blueprint-01-home-feed.html) | `/` | 已拆为独立入口；仍需继续对照当前 Masonry 实现完成密度审计。 |
| 02 | `canonical` | [`blueprint-02-discovery-search.html`](../../blueprints/blueprint-02-discovery-search.html) | `/explore`、`/search*`、`/topic/*` | 发现、搜索与话题结构继续有效。 |
| 03 | `needs-sync` | [`blueprint-03-content-detail.html`](../../blueprints/blueprint-03-content-detail.html) | `/post/*`、`/media/*` | 已合并历史 CSS 级联并移除归档依赖；仍需继续对照评论输入实现。 |
| 04 | `canonical` | [`blueprint-04-compose.html`](../../blueprints/blueprint-04-compose.html) | `/compose`、`/compose?edit=<cardId>`、`/drafts*` | 发布器显式区分随笔、图文和视频；手机发布、草稿恢复和已发布编辑统一使用 `immersive` Shell，无全局 TopNav/BottomNav，固定发布栏是唯一底部任务栏。桌面和手机使用同一连续编辑轴；字段错误就近、任务错误进入 scoped Banner。草稿箱使用 `task` Shell 并回到同一编辑器。 |
| 05 | `canonical` | [`blueprint-05-profile.html`](../../blueprints/blueprint-05-profile.html) | `/me`、`/user/*` | 主页是统一卡片流；本人内容/喜欢/收藏/历史在同页选项卡切换，内容总数写在内容 Tab；获赞只作为资料卡只读统计；他人仅显示内容；桌面单画布、移动页面画布内双列并保留 9px 内容 gutter。关注/粉丝二级任务的视觉真源统一归 Blueprint 06B。 |
| 06A | `canonical` | [`blueprint-06-avatar-edit.html`](../../blueprints/blueprint-06-avatar-edit.html) | 本人主页头像编辑任务 | 头像在主页直接触发；文件错误用 FieldStatus，任务错误用 Dialog 内 scoped Banner，成功用 Toast；不再扰动身份头。 |
| 06B | `canonical` | [`blueprint-06-identity-relationships.html`](../../blueprints/blueprint-06-identity-relationships.html) | 本人/他人关注与粉丝、举报与屏蔽 | 已移除失效的独立资料编辑板；本人和他人的关注/粉丝列表统一使用移动 `task` Shell，返回对应主页且无 BottomNav。举报与屏蔽继续使用独立治理任务语义。 |
| 07 | `canonical` | [`blueprint-07-inbox-messages.html`](../../blueprints/blueprint-07-inbox-messages.html) | `/messages*`、`/inbox`、`/inbox/system` | 方案 A 已落地并完成消息行精修：统一“消息中心”壳与“私信 / 通知”一级 Tab；桌面私信双栏、手机列表进入无 BottomNav 的独立会话页；会话行消费 `last_message_body/last_message_at/unread_count`，手机不默认选中第一条；通知使用连续流，仅保留互动消息（`engagement`）与系统通知（`system`），沿用分类游标、真实未读、错误、登录和发送失败状态。旧细分类路由只重定向。 |
| 08 | `canonical` | [`blueprint-08-library-settings.html`](../../blueprints/blueprint-08-library-settings.html) | `/settings*` | 已移除废弃个人资产板与无契约假设置；手机整个设置家族使用独立 `task` Shell，无全局顶栏或 BottomNav：总览返回 `/me`，分类返回 `/settings`。桌面保留稳定侧栏；当前真实分类与能力边界继续有效。 |
| 09 | `review-required` | [`blueprint-09-auth.html`](../../blueprints/blueprint-09-auth.html) | `/auth/*` | 需核对账号恢复和真实错误状态。 |
| 10 | `canonical` | [`blueprint-10-interaction-surfaces.html`](../../blueprints/blueprint-10-interaction-surfaces.html) | 共享组件 | Dialog/Modal 三行骨架、Typeahead、连续评论和移动壳层原则有效；弹窗细则见 [`DIALOGS.md`](../specifications/DIALOGS.md)。 |
| X | `review-required` | [`supplemental-content-compose-community.html`](../../blueprints/supplemental-content-compose-community.html) | 补充任务 | 草稿主线已迁入 Blueprint 04；仍含旧社区状态，只能按能力逐项采用。 |
| S | `canonical` | [`system-state-boards.html`](../../blueprints/system-state-boards.html) | 共享状态 | 加载、空、错误、权限和响应式状态基线。 |

## 已确认的结构债务

1. Blueprint 01 与 04 使用独立入口；重复的 03/05 片段不得重新合并。
2. Blueprint 03 与 05 不得依赖 `blueprints/archive/` 中的 CSS。
3. 正式蓝图文件名不携带 `final`、`v2` 或 `set`；状态由本注册表维护。
4. `blueprints/index.html` 只读取本表中的入口和状态，不再自行声明“全部正式完成”。
5. 页面不得为同一 Card resource 创建页面专属内容卡；Blueprint 01、02、03、05 必须按 `CARD-SYSTEM.md` 的 Compact → Standard → Detail 密度连续变化。
6. 头像上传只以 Blueprint 06A 为准；本人/他人关系二级页只以 Blueprint 06B 为准，不在 Profile 蓝图复制任务壳。

## 归档边界

`blueprints/archive/` 中的内容统一为 `archive`。正式页面引用归档文件属于阻塞问题，而不是兼容方案。
