# LoveCards Media Fit / Content Mode 规范

> v1.0 · 信息流、详情拼图和媒体查看器共享的媒体画框规则。

## 1. 目标

媒体原始比例不可控，但页面画框必须稳定。Media Fit 只决定图片、GIF、视频在固定画框中的呈现方式，不改变媒体资源、卡片类型、上传协议或瀑布流布局。

```text
Media resource
  ├─ intrinsic size       width / height
  ├─ frame               feed / detail / viewer
  ├─ requested fit       media.fit 或 card.data.mediaFit
  └─ resolved fit        cover / contain / center-crop / blur
```

## 2. 模式

| 模式 | 视觉结果 | 使用场景 |
|---|---|---|
| `smart` | 根据媒体与画框比例自动选择具体模式 | 默认值，适用于未知来源内容 |
| `cover` | 等比放大并裁剪铺满；允许使用焦点坐标 | 人像、封面、作者明确允许裁切的媒体 |
| `contain` | 完整显示，剩余区域使用稳定深色背景 | 海报、截图、文字图片、不能裁切的视频 |
| `center-crop` | 等比放大、强制中心裁切 | 没有可靠焦点信息但必须无黑边的封面 |
| `blur` | 同一媒体生成模糊铺满背景，前景完整显示 | 横竖比例差异较大的图片或带海报视频 |

`smart` 不是第五种 CSS `object-fit`，而是决策层：

1. 查看器固定使用 `contain`，保证原始内容可完整查看。
2. 媒体比例与画框接近时使用 `cover`。
3. 比例差异明显且存在可用图片/视频海报时使用 `blur`。
4. 无法生成模糊背景时使用 `contain`。
5. 缺少宽高信息时，图片默认 `cover`；无海报视频默认 `contain`。

## 3. 数据契约

卡片级默认值位于扩展字段：

```json
{
  "mediaFit": "smart"
}
```

当前后端无需修改即可通过 `data` 为多媒体逐项覆盖；数组顺序与规范化后的媒体顺序一致：

```json
{
  "mediaFit": "smart",
  "mediaFits": ["cover", "blur", "contain"],
  "mediaFocalPoints": [
    { "x": 0.52, "y": 0.34 },
    null,
    null
  ]
}
```

单个媒体可以覆盖卡片默认值：

```json
{
  "media": [
    {
      "url": "https://cdn.example.com/photo.webp",
      "type": "image",
      "width": 1080,
      "height": 1440,
      "fit": "cover",
      "focal_x": 0.52,
      "focal_y": 0.34
    }
  ]
}
```

- `fit` 只接受本规范登记值，未知值降级为 `smart`。
- `focal_x`、`focal_y` 范围为 `0..1`，只影响 `cover`；`center-crop` 永远使用中心点。
- `width`、`height` 用于智能决策，不可信或缺失时不得破坏渲染。
- 媒体级配置优先于卡片级配置。
- 后端尚未保存媒体单项展示字段时，前端使用 `data.mediaFits[]` 与 `data.mediaFocalPoints[]` 兼容；正式媒体契约开放后可迁移到媒体单项字段。
- 该字段是展示声明，不是文件归属、安全或审核边界。

## 4. 固定画框

- 信息流单图和视频：`4:5`。
- 信息流图集：整体 `1:1`，内部按 2/3/4 图模板分格。
- 详情单媒体：桌面 `16:10`，移动端 `4:3`。
- 详情多媒体：沿用 2/3/4 图固定拼图。
- 全屏查看器：使用可用视口，不裁切原始内容。

## 5. 质量与无障碍

- 模糊背景必须是装饰层、不可获得焦点、不可被读屏重复朗读。
- 模糊背景使用低清海报或现有图片，不复制播放视频，避免双重解码。
- 前景媒体失败时保留稳定背景和媒体类型信息，不让画框塌陷。
- 视频缩略图不自动播放；详情查看器保留原生播放控制。
- Media Fit 切换不使用布局动画，避免 Masonry 重排和媒体裁切同时运动。
- `prefers-reduced-motion` 下关闭缩放过渡。

## 6. 完成门槛

- 五种声明均有测试，`smart` 的比例决策可独立验证。
- 图片、GIF、视频和海报路径共享同一套解析规则。
- Feed、Detail、Media Viewer 不再各自写一套 `object-fit`。
- 320/390/768/1440 下画框比例稳定，无黑边泄漏、背景越界或媒体重叠。
