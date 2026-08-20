# LoveCards Content & Accessibility

> v1.2 · 施工前冻结。系统文案保持直接、真诚、可恢复，不替用户制造焦虑或手游化奖励。评论线程、搜索和移动壳层的完整交互见 [`../docs/specifications/INTERACTIONS.md`](../docs/specifications/INTERACTIONS.md)。

## 内容与术语

- 按钮写动作：“发布内容”“加入讨论”“保存修改”“重试”。避免“心动一下”“爆款秘籍”“领取成长值”。
- 统一使用：内容/动态、回复、引用、收藏、官方；推荐、关注、广告、商品和系统提示必须明确标注。
- 用户名显示名优先；账号、官方和创作者标识补充上下文。超长值截断但可从身份页或可访问名称获得。
- 推荐至少说明一种来源（关注、话题、兴趣、活动）；广告与商品不得伪装普通推荐。

## Text and counts

- Compact 标题最多 2 行、正文 4–6 行；Standard 正文 8–12 行后显示展开；详情控制 65–75ch。
- 在文字边界截断，不用渐变遮字；超长 URL、英文、emoji 安全换行。
- 小于 10,000 显示精确值，更大使用统一中文缩写；计数不跳动布局。
- Like、Save 和播放的状态同时有形状、文字或 pressed 状态，不只依赖颜色。
- 空流必须说明原因并提供关注话题、刷新推荐或发布内容中的一个下一步。

## Media

- 信息图片提供 alt，装饰图使用空 alt；图中关键文字在正文或替代内容中可读。
- 图集每张图有独立描述，并播报“第 n 张，共 m 张”。
- 视频默认不出声；有对白提供字幕，重要视觉信息提供摘要/转写；播放、进度、静音、字幕、全屏均可键盘操作。
- GIF 有 GIF 标记和暂停；减少动态或低数据模式显示静态首帧。
- 敏感内容遮罩说明原因和查看动作；遮罩后保留作者、来源和举报入口。
- 图片/视频失败保留比例，提供替代文本和重试；失败不让整张卡消失。

## Keyboard and semantics

- 页面提供 Skip to main；唯一 main、带名称的 nav/search/aside/feed landmark。
- Article 的可访问顺序为作者/来源 → 标题/正文 → 媒体 → Actions；整卡进入详情时不嵌套内部按钮。
- Like/Save 是 button 并暴露 pressed；菜单暴露 expanded/controls；字段使用 label、describedby、aria-invalid。
- Tabs/Menu/Listbox/Radio/Toolbar 按对应 WAI-ARIA 键盘模式；Escape 关闭最上层 Overlay 并回到触发器。
- 评论线程使用 `article` + `aria-labelledby`；回复按钮明确指向目标作者，深链接回复保留明确焦点状态。
- 无限流新增内容不抢焦点；Skeleton 对阅读器隐藏，加载区域只播报一次“正在加载内容”。

## Contrast and motion

- 普通文字和 Placeholder ≥4.5:1，大字 ≥3:1，图标/焦点 ≥3:1；Error、Selected、Like 不能只靠颜色。
- 200%/400% 缩放允许重排，不允许裁切主要操作；高对比模式保留边界与焦点。
- `prefers-reduced-motion` 下取消位移、缩放、循环 GIF、Skeleton 闪烁；卡片展开即时切换或短淡化。

## States and moderation

- Loading：说明正在加载，保留最终结构；超时转可恢复状态。
- Error：说明原因和动作，如“图片加载失败。重试 / 查看替代文字。”不显示堆栈和内部术语。
- Deleted：独立内容移除，线程保留“原内容已删除”关系占位；Restricted/Under review 说明范围、原因和申诉入口。
- Blocked/Muted/Report 有明确关系状态和恢复路径，不泄露举报者信息。

## Internationalization

初始语言为简体中文，布局使用逻辑方向属性。日期、数字和相对时间由 locale 格式化；按钮和标签允许中文变长 30%；CJK、Latin、emoji、RTL 内容安全混排。图片文字、字幕和错误文案不能只存在于位图。

## QA matrix

每个 Primitive 和 Card Pattern 需验证键盘、阅读器、亮/暗/高对比、200%/400% 缩放、减少动态、软键盘、横竖屏、超长中文、英文 URL、无图、九图、视频/GIF、弱网、媒体失败、发送失败和恢复。
