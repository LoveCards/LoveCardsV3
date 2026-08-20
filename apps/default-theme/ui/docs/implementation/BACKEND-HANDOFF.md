# 后端协作需求 · 交互与媒体

> 这是给后端/SDK Agent 的契约清单，不是前端绕过权限的方案。前端已经按现有能力施工；只有下列能力完成后，UI 才会移除“暂未开放”或只读边界。

## P0 · 评论线程

1. `POST /cards/:id/comments` 接受 `parent_id` 时，必须校验父评论属于同一张卡片、未删除且对当前用户可见；跨卡片 ID 返回稳定 4xx。
2. `GET /cards/:id/comments` 返回稳定的 `parent_id`、`created_at`、作者标识和状态；列表顺序至少保证根评论与回复可重建线程。
3. 删除根评论时保留可识别的关系占位，或提供 `deleted` 状态，避免回复变成无主评论。
4. 评论创建失败不能增加卡片 `comments` 计数；重复提交要有幂等/限流策略。
5. 评论正文长度与主题统一为 140 个字符（当前后端校验为 140）；若产品要放宽，需同步更新 SDK、验证器和主题，不要只改前端计数。

## 当前范围 · 不提供投票能力

投票卡片和基于评论正文的选择兼容层已从当前产品范围移除。后端与 SDK 不需要为本阶段新增 Interaction 资源、选择结果或相关权限；前端也不会读取 `cards.data` 中的历史选择声明。

- `discussion` 继续复用普通评论资源和 `parent_id`，不承担结构化选择或结果统计。
- 历史 `data.kind` 未命中当前卡片注册表时由前端安全降级，不应改变服务端权限或数据归属。
- 评论正文始终按用户内容处理；不要把 marker、选项或结果写入评论来模拟独立资源。
- 未来若重新启动结构化互动，需要另立产品决策和契约评审；本文件不保留待施工的投票接口方案。

## P0 · 媒体响应

列表、热门、搜索、详情统一返回有序 `media[]`：

```json
{
  "file_id": 123,
  "type": "image|video",
  "url": "…",
  "mime_type": "image/jpeg",
  "size": 1024,
  "width": 1200,
  "height": 900,
  "duration_ms": null,
  "poster_url": null,
  "is_animated": false,
  "position": 1
}
```

`media[]` 是唯一权威展示来源；兼容期可保留 `cover/pictures`。创建/更新接受有序 `media_file_ids[]`，事务内验证所有权、`scene=card`、完成状态、MIME、数量和生命周期。视频另需海报、时长、正确 MIME 与 Range 206。

## P1 · 前端策略接口

建议提供 `GET /me/files/policy?scene=card`，返回 `allowed_mime_types/max_files/max_size/supports_video/supports_direct_upload`。前端当前最多 9 个文件，后端策略和上传限流必须与之统一。
