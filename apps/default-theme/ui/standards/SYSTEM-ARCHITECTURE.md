# LoveCards 设计系统架构

> 状态：v1.0，作为默认主题设计治理的总入口。本文定义规范如何分层、组件如何选型、页面如何组合以及设计如何进入运行实现；不复制各专项规范的数值与细节。

## 1. 目标

LoveCards 默认主题采用“基础 Token → 通用 Primitive → 社区 Pattern → 页面 Blueprint”的单向设计架构。设计系统服务真实内容、关系和任务，不把每个页面做成独立作品，也不把组件库默认外观当成产品设计。

设计完成必须同时满足四件事：

1. 同一语义只有一种交互和状态表达。
2. 桌面和手机共享语义，但可以结构性重排。
3. 加载、空、失败、权限、删除和成功与正常状态同等完整。
4. `ui/` 先冻结设计事实，`src/` 再按垂直切片迁移；生产代码不得导入 `ui/`。

## 2. 权威顺序

出现冲突时按下列顺序裁决，低层文档不得重新定义高层规则：

1. [`../PRODUCT.md`](../PRODUCT.md)：用户、产品目标、品牌性格和反例。
2. [`../DESIGN.md`](../DESIGN.md)：机器可读 Token 与总体视觉语言。
3. 本文：设计系统层级、所有权和治理方式。
4. [`FOUNDATIONS.md`](./FOUNDATIONS.md)：颜色、尺寸、布局、动效和主题基础。
5. [`../docs/registries/ASTRYX-COMPONENTS.md`](../docs/registries/ASTRYX-COMPONENTS.md)：组件用途与采用状态。
6. [`PRIMITIVES.md`](./PRIMITIVES.md)：项目级通用组件行为。
7. 专项规范：表单、反馈、导航、布局、媒体、Dialog、发布和交互。
8. 业务 Pattern：卡片、作者身份、评论线程、通知项、会话行和资源状态。
9. 蓝图注册表与可视化蓝图：页面组合和最终审阅样例。
10. 迁移计划：只记录实现顺序，不改变设计规则。

同一规则只能在一个位置定义。其他文件必须链接到真源，禁止复制后稍作改写。

## 3. 四层模型

```text
Page Blueprint
  └─ Community Pattern
      └─ LoveCards Primitive policy / adapter
          └─ Astryx Core behavior + Web platform
```

### Foundation

负责语义颜色、字体、间距、圆角、阴影、断点、层级、动效和主题映射。页面不能新增无名数值；确有新需求时先证明现有 Token 无法表达，再更新 Foundation。

### Primitive

负责可访问行为和跨业务复用，例如 Button、Field、Tab、Dialog、Banner、Toast、Skeleton。默认复用 Astryx，不复制其键盘、焦点、Portal、验证和异步行为。

### Pattern

负责 LoveCards 的业务语义，例如 `AuthorIdentity`、`FeedStory`、`CommentThread`、`NotificationItem`、`ResourceState`。Pattern 可以组合 Primitive，但不能覆盖其核心语义。

### Blueprint

负责页面信息架构和响应式组合。页面只能选择 Pattern 的密度和顺序，不能穿透 Pattern 内部重新排列作者、卡片媒体、状态反馈或交互热区。

## 4. 组件选择程序

新增界面前必须按顺序回答：

1. 用户正在完成什么任务？
2. 这是导航、动作、输入、状态、内容、布局还是浮层？
3. 信息影响一个字段、一个区域、一个页面还是整个会话？
4. 状态是短暂、持续到下一步，还是必须持续到问题解决？
5. Astryx 已有组件是否完整覆盖语义和无障碍行为？
6. LoveCards 是否已经存在对应 Primitive 或 Pattern？

只有前五步均无法满足时才允许增加组件。新抽象必须消除至少一个真实重复点，并在组件注册表登记用途、禁用场景、状态和替代方案。

### 采用状态

- `adopt`：直接使用 Astryx 行为，LoveCards 只提供主题和薄适配。
- `compose`：由 Astryx Primitive 组合成 LoveCards 业务 Pattern。
- `defer`：组件有效，但当前默认主题没有真实场景，不进入生产包袱。
- `avoid`：与当前产品结构冲突或容易制造错误语义，除非重新决策不得使用。

`defer` 不等于不存在，`avoid` 也不等于删除依赖；二者都是避免页面临时选型的治理状态。

## 5. 状态是组件的一部分

所有交互与数据表面都必须在设计阶段定义：

- default、hover、focus-visible、pressed；
- selected、disabled、read-only、loading（适用时）；
- empty、partial、error、permission、offline；
- deleted、restricted、unavailable（资源表面适用时）；
- success 和撤销路径（写操作适用时）。

Skeleton 必须使用完成态同一布局轨道；错误不能替换仍然可用的内容；加载更多失败不能清空已经加载的列表。

## 6. 页面组合规则

- 页面壳只负责全局导航、内容容器、safe-area 和页面级状态。
- Section 用间距和标题组织页面，不能下意识套 Card。
- Card 只用于可独立选择、移动、删除或比较的对象；禁止嵌套 Card。
- 同一资源跨首页、搜索、主页、收藏、历史和详情保持同一 Pattern 身份。
- 本人主页与他人主页共享身份头和内容画布；本人私有资产通过同页 Tab 切换。
- 导航、筛选、模式选择和动作使用不同组件，不能只因为外观相似都做成胶囊。

## 7. 响应式与主题

响应式以容器和任务为中心，不以设备名称为中心。断点变化允许：隐藏辅助栏、改变列数、把锚定浮层转为全屏任务、调整内容顺序；不允许通过缩小文字、触控目标或删除状态信息硬塞桌面结构。

亮暗主题使用相同语义 Token。Portal、Dialog、Toast、Tooltip、媒体 Overlay 和 Skeleton 必须在两个主题下同时审阅。系统强调色面积通常不超过一屏的 10%，媒体内容拥有页面主要色彩。

## 8. Astryx 版本与升级

默认主题当前锁定 `@astryxdesign/core@0.3.0`。官网用于理解组件意图，本地 `node_modules/@astryxdesign/core/**/*.doc.mjs` 是本次实现 API 的事实源。

升级必须作为独立任务：更新组件登记表，检查 breaking changes，运行组件 smoke test，并验证亮暗主题、键盘、320/390/768/1050/1440px 和减少动态。禁止在页面迁移中顺带升级或 swizzle Core。

## 9. 设计变更流程

1. 在注册表记录受影响页面、Pattern 和 Primitive。
2. 在专项规范冻结正常态和边界状态。
3. 更新对应 Blueprint 或状态板。
4. 明确 SDK 能力与诚实降级，不伪造写入或成功。
5. 以一个垂直切片迁移 `src/`，完成后删除被替代的局部模式。
6. 更新测试与治理检查，防止旧模式回流。

禁止长期维护新旧两套同义组件。过渡期必须写明删除条件，并在同一迁移阶段收口。

## 10. 设计完成门槛

- 组件用途登记表包含所采用 Astryx 版本的全部用户界面组件。
- 每个核心页面在页面矩阵中有正常、加载、空、错误、权限与移动状态。
- 每种反馈都能根据范围、持续时间和用户下一步选择正确组件。
- 320px 到 1440px 无横向溢出、遮挡、不可达操作或骨架错位。
- 键盘、读屏、200% 缩放、减少动态和亮暗主题都有明确验收项。
- Blueprint、规范、注册表和实现计划不存在相互矛盾的“最终版本”。
