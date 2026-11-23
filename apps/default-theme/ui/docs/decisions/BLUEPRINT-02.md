# Blueprint 02 发现与搜索页面家族

日期：2026-08-12
状态：历史设计记录。当前桌面和手机样例以[现行蓝图](../../blueprints/blueprint-02-discovery-search.html)为准。

## Page family

- 02A 发现首页：首版曾采用编辑精选、兴趣轨道、趋势、创作者与高密度媒体流；这些模块现须按数据契约逐项复审。
- 02B 搜索起始：首版曾采用最近搜索、实时趋势和推荐创作者；无真实来源时不得沿用相关标识。
- 02C 搜索结果：首版曾设计综合/内容/用户/话题/媒体切换；现行结果页开放综合、内容、用户与话题，真实查询契约见 [`SEARCH.md`](../specifications/SEARCH.md)。媒体不作为独立范围。
- 02D 话题详情：话题身份、代表内容与持续动态同页呈现。

## Structural rules

- 发现页承担主动探索；现行结构和标识以 [`DISCOVERY.md`](../specifications/DISCOVERY.md) 为准，旧的编辑精选方案只有策展契约具备时才能恢复。
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
