# LoveCards 卡片模式与交互注册表

> v1.3 · UI 设计、前端实现与后端协作的唯一入口。卡片不是一个万能容器，也不是把所有逻辑塞进 `data.kind` 的条件分支。

## 1. 先分开四件事

一张卡片由四层组成，任何一层都不能替代另一层：

```text
Card resource
  ├─ Pattern（视觉轮廓）        data.kind / media
  ├─ Payload（内容与媒体）      content / media[]
  ├─ Interaction adapter        comments.thread.v1
  └─ Runtime capability/state   SDK 权限、登录、审核、结果、加载状态
```

- **Pattern** 决定卡片的密度、媒体轮廓、正文位置和操作槽位；不代表谁能写入。
- **Payload** 是要展示的内容；媒体归属、审核和顺序来自资源契约，不来自 `data`。
- **Interaction adapter** 只描述共享评论线程如何接入；它不能打开权限，也不承载业务规则。
- **Runtime capability/state** 由 SDK、会话和服务端响应决定。没有能力时必须显示只读或“暂未开放”，不能假装成功。

因此这里使用“注册表 + 适配器”，而不是继承一堆卡片类：数据驱动的注册表更容易被 Feed、Detail、Search 和未来主题复用，也不会把 React 组件生命周期和后端资源生命周期绑在一起。

## 2. 表现层注册表

实现位置：`src/features/feed/card-patterns.js`。

```ts
type CardPatternDefinition = {
  kind: string
  label: string
  family: 'content' | 'media' | 'conversation' | 'commerce'
  layout: 'note' | 'lead' | 'tile-a' | 'tile-b' | 'quote' | 'link' | 'product'
  presentation: {
    media: 'none' | 'single' | 'collection'
    showKicker: boolean
  }
}
```

当前登记的视觉模式：

| kind | 家族 | 轮廓 | 允许的内容形态 | 当前状态 |
|---|---|---|---|---|
| `text` | content | 阅读型正文面 | 纯文字、短想法、长文摘要 | 已接入 |
| `image` | media | 单图主表面 | 一张图片或 GIF | 已接入 |
| `gallery` | media | 2/3/4+ 同心拼贴 | 有序多媒体图集 | 已接入 |
| `video` | media | 海报 + 原生控制 | 单视频、海报、字幕入口 | 已接入（依赖媒体契约） |
| `discussion` | conversation | 问题 + 讨论轨道 | 讨论、评论线程 | 已接入 |
| `quote` | content | 引用来源 + 正文 | 引用关系 | 已登记，关系 API 未开放 |
| `link` | content | URL 预览 + 摘要 | 外链 | 已登记，预览元数据未开放 |
| `product` | commerce | 商品信息 + 行动 | 未来商城扩展 | 已登记，交易能力未开放 |

未知 `kind` 安全降级到 `text`。新增模式必须先更新本表和蓝图，再写组件；页面不得自行发明同名模式。

### 信息流统一呈现

`image`、`gallery` 和 `video` 都属于 `media` 家族：`image` 是单媒体状态，`gallery` 是多媒体拼贴状态，`video` 是带播放语义的媒体状态。它们在信息流共享同一单元结构，只保留媒体表面和底部作者/互动元信息；话题与完整正文只在详情页展开。

`text` 是无媒体的 `content` 家族状态，在信息流显示文字内容块，并复用同一底部作者/互动元信息。`discussion` 使用同源文字内容面；完整评论与回复只在详情线程中展开，不在信息流追加选择控件。

### 加载态几何契约

信息流骨架不是通用横线占位，而是 `FeedStory` 的同构状态：媒体骨架复用 `story-media` 的内容比例，随手记与讨论骨架复用 `story-copy` 的内容面，作者与互动区复用 `story-meta` 的高度。卡片圆角由 `--lc-radius-feed` 统一控制：桌面为 `14px`，手机为 `11px`；真实卡片和骨架都不得单独写另一组圆角值。骨架填充层必须继承圆角并明确裁切，避免占满层在浏览器中渲染成方角。加载态只替换颜色和内容，不改变卡片轮廓、列宽或响应式断点。

## 3. 交互适配器注册表

实现位置：`src/features/feed/card-interactions.js`。

```ts
type CardInteractionDescriptor = {
  id: string
  type: 'thread'
  adapter: 'comments.thread.v1'
  status: 'declared' | 'available' | 'unavailable'
  trust: 'server-resource'
}
```

- `comments.thread.v1` 复用现有评论和 `parent_id`，能力状态仍由 SDK 决定。
- 当前注册表不接受结构化选择声明；历史 `data.interactions[]` 不能生成额外控件。
- 适配器不能打开 `cards.update`、`likes` 或任意其他写权限。每次评论写入前仍需检查登录、SDK capability、卡片可见性和互动状态。

## 4. 卡片数据边界

```json
{
  "kind": "discussion",
  "topics": [
    { "id": 13, "name": "社区讨论" }
  ]
}
```

- Card 的唯一用户正文是资源顶层 `content`，上限 1000 字；普通 Card 不提供标题字段。
- `kind`、`layout` 和 `alt` 是声明/展示数据；人类可读话题来自 CardResource 顶层 `topics[]`，详情最多展示当前允许的 3 项。历史 `data.topic` 不得覆盖关系数据或显示数字 ID。
- 历史 `data.title` 只允许在 `content` 缺失的异常旧记录中作为只读回退，不与正文并列显示，也不进入发布、编辑或搜索契约。
- `cover`、`pictures`、未来 `media[]` 是媒体来源；真实 MIME、文件归属、排序和审核状态只认媒体契约。
- `data` 不保存喜欢状态、评论结果、作者权限、审核结果或浏览者身份。
- 未登记或已移除的 `kind` 安全降级为 `text` 或按真实媒体数量推导为媒体类型，不生成额外互动。
- 评论正文始终是用户内容，不作为前端协议事件解析。

## 5. 共享渲染模型

Feed、Detail、Search 接收同一个 `CardViewModel`：

```ts
{
  id, kind, pattern, author, meta, content, topics,
  mediaItems, mediaFit, interactions, replies,
  source: 'live' | 'preview'
}
```

Pattern 组件只负责轮廓和状态；评论线程、媒体查看器和喜欢由共享 feature 提供。一个新模式应优先复用这些槽位，而不是复制一套评论或弹窗。

媒体画框与裁切策略统一遵循 [`../specifications/MEDIA-FIT.md`](../specifications/MEDIA-FIT.md)。Pattern 只声明是否存在单媒体或媒体集合，不自行决定 `object-fit`。

## 6. 完成门槛

- 每个模式有默认、加载、空媒体、媒体失败、不可用和错误状态。
- 320/390/768/1440 通过布局回归；正文、媒体和操作不互相覆盖。
- 视觉模式和评论适配器保持独立，未知模式安全降级。
- 当前范围只允许普通评论线程；结构化互动不得借用评论正文绕过资源契约。
- 新能力先更新 UI 蓝图、注册表和后端 handoff，再进入业务组件。
