# Blueprint 02 发现与搜索页面家族

日期：2026-08-12
状态：设计首版完成，作为 Set 02 当前基准。

## Page family

- 02A 发现首页：编辑精选、兴趣轨道、趋势、创作者与高密度媒体流。
- 02B 搜索起始：最近搜索、实时趋势和推荐创作者。
- 02C 搜索结果：综合/内容/用户/话题/媒体切换，最佳匹配优先。
- 02D 话题详情：话题身份、代表内容与持续动态同页呈现。

## Structural rules

- 发现页不复制推荐首页，通过兴趣轨道和编辑精选支持主动探索。
- 搜索类型切换只出现在有查询结果的页面。
- 话题详情不拆分多余二级页面，代表内容和动态连续出现。
- 桌面端允许上下文侧栏；移动端重组成不透明、贴边的纵向区段。
- 移动端主要触控命中区至少 44px；底部导航固定 64px 并预留内容空间。

## Visual language

- 继承 Blueprint 03 Airy Solid 的视觉方向：白色内容表面、冷调浅灰画布、玫瑰选中态和克制圆角。
- 发现页允许媒体形状更有张力；搜索页使用更平直的工具化列表结构。
- 透明深色材质仅用于媒体类型、时长等临时覆盖控件。

## Files

- Source：`blueprints/blueprint-02-discovery-search.html`
- Style：`blueprints/blueprint-02-discovery-search.css`
- References：`assets/blueprints/02a-explore-responsive.png` 至 `02d-topic-detail-responsive.png`
