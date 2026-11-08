# 审计日志查询筛选方案

> 状态：已实现并在 dev 验收（2026-11-08）。范围仅为系统 → 审计日志列表；审计写入、详情白名单和用户端找回密码流程不变。

## 目标与现状

管理员应能回答“谁、何时、对什么资源做了什么”，并用请求标识追踪同一次操作。列表默认按 `id DESC` 展示最近记录，筛选后仍保留相同排序与分页。

改造前页面只传 `page`、`list_rows`。后端 `AdminReadModel::auditLogs()` 虽已处理部分字段，但公共 `AdminValidate::pagination` 未放行动作，SDK 也未声明，因此原页面不能直接接线。本次新增审计专用查询场景、服务端筛选和契约生成，页面不再发送无效的 `search_value`。

## 列表交互

使用现有 `AdminResourceTableShell` 的工具区，不做第二套筛选组件。常用筛选按“动作／资源／操作者”和“起止时间”两行排列：

| 控件 | 输入方式 | 请求语义 |
| --- | --- | --- |
| 动作 | 可输入的动作代码，提供常见动作建议，如 `auth.recovery.request`、`auth.recovery.failed`、`auth.recovery.reset` | `action` 前缀匹配；空值不传 |
| 操作者 | 正整数 ID；提供“系统/匿名”选项 | `actor_id` 精确匹配；系统/匿名需要独立 `actor=system`，不能用 `0` 代替 |
| 资源类型 | 从已有资源类型选项中选择，允许输入其他合法代码 | `resource_type` 精确匹配 |
| 时间 | 起止日期时间，默认不限制；明确标注“站点时间（Asia/Shanghai）” | `from` 含起点、`to` 不含终点，按站点时区的 `YYYY-MM-DD HH:mm:ss` 传输 |

“更多筛选”中提供资源 ID、Request ID 和 Correlation ID 的精确匹配。列表表头不再重复放大段筛选控件；筛选条件可在工具区清除，表格空态提示调整筛选。刷新保留条件。条件变化重置到第一页，URL 记录筛选值、页码和每页行数，浏览器前进/后退能恢复查询。无效 URL 参数忽略并显示一次轻量提示，不发送非法请求。

账号恢复排障可用动作筛选查看三类审计。`auth.recovery.request` 只记录账号哈希且 `actor_id` 为空；它不提供可反查的用户名或用户 ID。`auth.recovery.failed` 的资源是 challenge，`auth.recovery.reset` 的资源是 user。界面不得把这些记录错误合并成同一个用户轨迹，也不得展示验证码、原始账号或密码。

## 接口与查询改动

为审计列表建立专用查询场景，避免继续扩张其他管理列表共用的 `pagination` 场景。新增字段：`action`、`actor`、`resource_type`、`resource_id`、`request_id`、`correlation_id`、`from`、`to`；保留 `page`、`list_rows`。每项应有长度、格式和日期范围校验，非法输入返回 422。`actor=system` 表示 `actor_id IS NULL`，数字表示精确匹配操作者 ID；由于现有审计表不区分系统与匿名请求，界面统一标注“系统或匿名”。`actor_id` 可保留为兼容字段，但新页面只使用 `actor` 的明确语义。同步更新 OpenAPI、生成 SDK 与相关契约检查。

查询使用参数绑定。动作前缀匹配只允许动作代码字符，`_` 按 LIKE 字面量转义；资源类型、资源 ID 和追踪标识用精确匹配。时间过滤使用 `created_at >= from AND created_at < to`。审计写入使用应用默认时区 `Asia/Shanghai` 下的无时区 DATETIME，故查询值必须与这个存储时区一致，不能直接提交浏览器 UTC 时间。结束时间必须晚于开始时间；默认不对历史范围加隐式截断。继续按 `id DESC` 排序，避免同一时间戳下分页次序不稳定。

先用实际数据量和 `EXPLAIN` 检查现有 `idx_audit_actor_time`、`idx_audit_resource`。只有发现查询成本问题时再追加动作/时间或追踪标识索引；不预先堆索引。列表仅返回现有摘要字段，payload 留在受 `audit.read` 保护的详情页。

## 验收标准

1. 任一筛选请求与 URL 一致，刷新、翻页、前进/后退后结果保持；清除后恢复最新记录。
2. 动作、操作者、资源、时间和请求标识均由服务端筛选；非法输入明确 422；分页总数与筛选结果一致。
3. `auth.recovery.*` 可被检索，匿名申请不伪装成某个用户；详情仍只显示后端允许的字段。
4. 桌面和窄屏沿用管理端表格风格，加载、空态和错误可分辨；`audit.read` 权限仍由后端执行。

## 实施与验收记录

- 后端由 `AdminAuditQueryValidate::list` 校验查询参数，`AdminReadModel::auditLogs()` 在数据库完成筛选、计数和稳定排序。列表仅选取摘要字段；详情仍通过原有权限和资源白名单读取。
- 管理端将已应用条件、页码和每页行数写入 URL。输入先保存在草稿中，点击“查询”后提交；无效 URL 参数忽略并提示。筛选区、更多筛选对话框和列表均复用管理端原生组件。
- 云端 `npm run check` 全部通过；管理端类型检查通过。隔离浏览器测试覆盖查询参数、刷新、清除、浏览器历史和无效 URL。真实 dev API 用现有 99 条审计记录核对动作、操作者、资源、时间和 Request ID；非法条件返回 422，列表不含 `payload`。
- dev 查看入口：`http://45.207.204.11:20089/admin/apps/operations/audit`。现有 20089 开发服务直接读取本工作树，无需重启。
