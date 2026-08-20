# LoveCards Component Foundation Decision

> 状态：Astryx 继续作为行为与 Primitive 基底；Blueprint 01 正式 React 施工已开始。调研日期：2026-08-12，施工状态更新：2026-08-15。

## Decision

首选：**Astryx Core + LoveCards Theme + LoveCards Community Patterns**。

- Astryx 负责可访问行为、通用 Primitive、Overlay、表单和升级。
- LoveCards Theme 负责颜色、字体、圆角、间距、明暗模式和公开组件 override。
- LoveCards Patterns 负责 Feed Card、Thread、Creator、Community 和匹配展开。
- 默认不改 `node_modules`、不复制 Core 源码、不 swizzle。

## Comparison

| 方案 | 优点 | 代价 | 结论 |
|---|---|---|---|
| Astryx | 依赖式组件、主题 CSS Token、150+ 组件、公开 override、CLI upgrade/doctor | Beta、React 19+、需管理 CSS layer | 首选 |
| shadcn/ui | 源码完全可见、深度定制、生态大 | 源码进入项目，上游升级要 diff/merge | 不作为主基底 |
| Base UI direct | 无样式依赖、可访问、CSS 完全自由 | 所有 Primitive 视觉和组合由项目维护 | Astryx P0/P1 退路 |
| Radix direct | 无样式、成熟、可访问 | 需要补齐更多组合和视觉层 | 备选行为层 |

Astryx 官方资料明确其需要 React 19+，主题通过 CSS custom properties 提供 Token，支持 plain CSS/className/Tailwind/StyleX，并提供 CLI 的升级 codemod：[Getting Started](https://astryx.atmeta.com/docs/getting-started)、[Theme](https://astryx.atmeta.com/docs/theme)、[Styling](https://astryx.atmeta.com/docs/styling)、[CLI](https://astryx.atmeta.com/docs/cli)。其当前仍标注 Beta，施工前必须接受受控升级策略：[Astryx GitHub](https://github.com/facebook/astryx)。

shadcn 官方将其定义为“组件代码分发系统”而非传统组件库，适合项目拥有源码但不符合本项目“主要改 CSS、通用行为跟随上游”的维护目标：[shadcn Introduction](https://ui.shadcn.com/docs)。Base UI 是无样式、依赖式 React 组件，作为行为退路：[Base UI About](https://base-ui.com/react/overview/about)。

## Ownership and theme

```text
LoveCards screens
  ↓
Community Patterns: FeedCard / Thread / Creator / Community
  ↓
Primitive policy: Button / Field / Tabs / Dialog / Overlay / Feedback
  ↓
Astryx Core behavior
  ↓
React 19 + Web platform
```

以 Astryx Neutral 的结构为起点，生成拥有版权和命名权的 LoveCards Theme；不直接使用 Neutral 的品牌视觉。Deep Rose 映射主行动和焦点，Signal Rose 只映射 Like，Canvas/Surface/Subtle/Ink/Separator 映射现有 Token。生产使用预编译主题，开发可运行时注入。

CSS layer 顺序：reset → Astryx base/theme → LoveCards theme → Primitive adapter → Community Pattern → layout utilities。禁止 `!important`、私有 StyleX 原子类和任意 z-index。

## Upgrade policy

Beta 阶段锁定 lockfile，升级前阅读 changelog/migration，运行 `astryx upgrade`/`doctor`，验证 Primitive smoke test、亮暗主题、键盘、360/760/1050/1440px 和 Text/Image/Video/Discussion 四类卡片。上游改动只允许影响 Core、Theme 和 adapter，不改变 LoveCards Pattern 契约。

若 React 19 不可用，Astryx 决策立即暂停，不能在其他框架中伪造；转评估对应生态的无样式依赖。若 Astryx smoke test 出现 P0/P1，评估 Base UI direct。shadcn 只有在团队接受本地源码自行合并上游时才重新评估。

## Construction gate

基础确认已完成：React 19、Astryx Beta 受控升级和 `apps/default-theme` 运行边界均已落实；设计真源独立保存在 `apps/default-theme/ui`。正式页面按 Blueprint 01–09 施工，Astryx 默认外观不得替代 LoveCards 设计真源；若出现 P0/P1 阻塞，评估 Base UI direct。
