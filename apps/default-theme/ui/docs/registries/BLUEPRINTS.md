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
| 04 | `canonical` | [`blueprint-04-compose.html`](../../blueprints/blueprint-04-compose.html) | `/compose`、`/drafts*` | 发布器显式区分随笔、图文和视频；图文内部保留 image/gallery 数据状态，草稿只由发布页主动保存并恢复回同一编辑器，无独立标题或自动创建；发布页不提供信息流实时预览。 |
| 05 | `needs-sync` | [`blueprint-05-profile.html`](../../blueprints/blueprint-05-profile.html) | `/me`、`/user/*` | 已移除重复级联入口并统一命名；封面、身份区和资产侧栏仍需对照当前实现。 |
| 06 | `review-required` | [`blueprint-06-identity-relationships.html`](../../blueprints/blueprint-06-identity-relationships.html) | `/me/edit`、关系页 | 需重新核对当前编辑资料与移动端实现。 |
| 07 | `review-required` | [`blueprint-07-inbox-messages.html`](../../blueprints/blueprint-07-inbox-messages.html) | `/inbox*`、`/messages*` | 通知和私信能力已开放，需完成真实未读、错误、登录和发送失败状态对照。 |
| 08 | `review-required` | [`blueprint-08-library-settings.html`](../../blueprints/blueprint-08-library-settings.html) | `/me/library/*`、`/settings*` | 需与当前收藏、历史、喜欢、评论和设置页面逐项同步。 |
| 09 | `review-required` | [`blueprint-09-auth.html`](../../blueprints/blueprint-09-auth.html) | `/auth/*` | 需核对账号恢复和真实错误状态。 |
| 10 | `canonical` | [`blueprint-10-interaction-surfaces.html`](../../blueprints/blueprint-10-interaction-surfaces.html) | 共享组件 | Dialog/Modal 三行骨架、Typeahead、连续评论和移动壳层原则有效；弹窗细则见 [`DIALOGS.md`](../specifications/DIALOGS.md)。 |
| X | `review-required` | [`supplemental-content-compose-community.html`](../../blueprints/supplemental-content-compose-community.html) | 补充任务 | 草稿主线已迁入 Blueprint 04；仍含旧社区状态，只能按能力逐项采用。 |
| S | `canonical` | [`system-state-boards.html`](../../blueprints/system-state-boards.html) | 共享状态 | 加载、空、错误、权限和响应式状态基线。 |

## 已确认的结构债务

1. Blueprint 01 与 04 使用独立入口；重复的 03/05 片段不得重新合并。
2. Blueprint 03 与 05 不得依赖 `blueprints/archive/` 中的 CSS。
3. 正式蓝图文件名不携带 `final`、`v2` 或 `set`；状态由本注册表维护。
4. `blueprints/index.html` 只读取本表中的入口和状态，不再自行声明“全部正式完成”。

## 归档边界

`blueprints/archive/` 中的内容统一为 `archive`。正式页面引用归档文件属于阻塞问题，而不是兼容方案。
