# LoveCards Foundations

> v1.0 · 施工前冻结。本文把 `DESIGN.md` 展开为布局、Token、媒体、图标、层级与动效规则；不包含运行时代码。

## 规范优先级

[`../PRODUCT.md`](../PRODUCT.md) 定义产品与反例；[`../DESIGN.md`](../DESIGN.md) 的 frontmatter 是视觉 Token 真源；本文定义基础使用方式；[`PRIMITIVES.md`](./PRIMITIVES.md) 定义通用组件；[`CARD-SYSTEM.md`](./CARD-SYSTEM.md) 定义社区卡片。

## Token 规则

页面只使用语义 Token，不散落原始色值、圆角、阴影或时长。命名描述用途：`surface-canvas`、`surface-content`、`text-primary`、`text-muted`、`action-primary`、`signal-like`、`border-subtle`。暗色模式复用同一语义名，不写页面级独立 dark 修补。

### 颜色

| 角色 | 亮色 | 用途 |
|---|---|---|
| Canvas | `#f5f5f7` | 页面背景 |
| Surface | `#ffffff` | 内容与表单 |
| Subtle Surface | `#f0f1f4` | 输入、Skeleton、局部层次 |
| Primary Ink | `#1d1d1f` | 标题、正文 |
| Muted Ink | `#6e6e73` | 时间、来源、次级文字 |
| Separator | `rgba(60,60,67,.16)` | 必要的 1px 结构线 |
| Deep Rose | `#c92c60` | 主行动、焦点、选中、可读玫瑰文字 |
| Signal Rose | `#e63d63` | 喜欢和小面积高光 |
| Rose Mist | `#fff0f4` | 选中背景、引用、社区邀请 |

Deep Rose/white 对比度 5.23:1；Signal Rose/white 为 4.03:1，Signal Rose 禁止普通正文和白字按钮。暗色映射见 `DESIGN.md`，不增加霓虹、紫色渐变或发光。

### 尺度

间距：4/8/12/16/24/32/48px。控制圆角以 9px 为基准，字段可为 10px，媒体 12px，内容面板 14px，标签和头像使用全圆/50%。文字角色为 24/21/17/15/13/12/11px，正文行宽 65–75ch。固定层级：Content 0、Sticky 30/40、Overlay 60、Backdrop 70、Modal 80、Toast 100、Tooltip 110。

## Responsive layout

| 视口 | gutter | 发现流 | 关系/讨论流 | 辅助栏 |
|---|---:|---|---|---|
| 320–359px | 12px；Compact Feed 可为 8–9px | 单列安全回退 | 单列 | 无 |
| 360–760px | 12–16px；Compact Feed 固定 9px | 双列 Compact，8–10px gap | 单列 Standard | 流内或入口 |
| 761–1050px | 20px | 三列 | 单列，≤680px | 隐藏/折叠 |
| 1051–1439px | 20px | 3–4 列 + 280px | ≤680px | 固定 280px |
| ≥1440px | 居中 | 仍最多 4 列 | ≤680px | 不继续扩张 |

全局容器最大 1240px；详情 680–760px；编辑器 ≤860px；认证表单 ≤420px。顶部栏桌面 68px/移动 56px，频道栏桌面 52px/移动 44px，移动底栏 64px 加 safe-area。小屏不靠缩小正文维持多列。

## Typography and iconography

统一系统中文无衬线栈：Noto Sans SC、PingFang SC、Microsoft YaHei、system-ui。标题最多 `-0.02em`，正文不低于 15px，Compact 摘要不低于 13px。图标采用同一套圆端线性图标：18px 默认、20px 主要动作、24px 导航，约 1.75px 描边；激活使用同源填充，不混用图标风格。

图标必须有可访问名称或与可见文字关联。Like 使用 Signal Rose，Save 使用 Ink，Reply/Share 使用 Muted Ink。禁止把心形当成所有入口的默认品牌图标，禁止手绘草图、3D 彩色图标和不一致笔画。

## Media and material

支持 4:5、1:1、3:2、16:9；加载前声明比例，失败态保留比例。服务端焦点或作者裁切优先，默认居中仅为回退。视频默认不出声；GIF 尊重减少动态和低数据模式。媒体上的文字使用自适应深色底。

常驻内容使用不透明 Surface/Subtle Surface。玻璃仅用于图片或视频上的播放、音量、页码和更多操作；可读性不能依赖模糊。卡片默认使用色调或 1px 分隔，阴影只允许 Card Ambient `0 1px 3px rgba(20,25,35,.05)`，常驻卡禁止宽模糊阴影和描边叠加。

## Motion

| 状态 | 时长 | 规则 |
|---|---:|---|
| Press | 120ms | `scale(.97)`，不改布局 |
| State | 180ms | Hover、Focus、Like、Save |
| Control | 220ms | 媒体控制淡入 |
| Matched expand | 300ms | 卡片到详情 |

统一 `cubic-bezier(.22,1,.36,1)`，动画可中断，不动画布局属性。`prefers-reduced-motion: reduce` 下取消位移、缩放、GIF 自动播放和 Skeleton 闪烁，只保留即时状态或短淡化。

## Foundation 验收

- 所有新增值均映射到命名 Token。
- 320/360/760/1050/1440px 无横溢出、遮挡和不可达操作。
- 亮暗模式保持同层级；正文/占位 ≥4.5:1，图标/焦点 ≥3:1。
- 媒体有比例、失败态和替代内容；Overlay 不被 `overflow` 裁切。
- 页面容器、Feed gutter 与 edge-to-edge 例外遵循 [`../docs/specifications/LAYOUT-RESPONSIVE.md`](../docs/specifications/LAYOUT-RESPONSIVE.md)。
