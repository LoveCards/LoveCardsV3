# 管理端重构发布与回滚清单

> 适用范围：管理端规范路由、角色模板、角色化工作台、高风险操作审计、配置页保护、列表视图和运维详情。
>
> 原则：先验证契约和授权，再切换流量；回滚应用版本不得破坏已经执行的数据库迁移。

## 1. 发布前冻结

- [ ] 冻结 `contracts/openapi.json`、`contracts/route-manifest.json`、`contracts/api-inventory.json` 与生成 SDK，确认工作树中的生成文件来自同一次导出。
- [ ] 执行数据库全量备份，并记录备份标识、校验值、恢复命令和负责人。
- [ ] 在与生产同版本的 MySQL 上演练全部待执行迁移，记录耗时、锁等待和磁盘增量。
- [ ] 确认迁移是向前兼容的：旧应用版本可以忽略新增表、列和角色；不得依赖发布时立即删除旧列或旧路径。
- [ ] 核对环境标识、站点 URL、代理头、Cookie、CORS、存储、发信、验证码、Worker、SSE 与通知投递配置。
- [ ] 管理端 production build/generate 使用 `/api` 或批准的 HTTPS API 基址；生成 HTML 不得包含 localhost、127.0.0.1 或 development 标记。
- [ ] 执行 `npm run generate:admin` 后运行 `npm run check:admin-bundle`，确认后端托管的 `public/admin` 与 `.output/public` 文件清单及 SHA-256 完全一致。
- [ ] Secret 只通过部署环境或服务端密钥存储注入；不得写入前端构建产物、日志、截图、审计记录或版本库。

## 2. 权限与角色

- [ ] 执行角色模板幂等种子/迁移；确认只补缺失模板和 grant，不覆盖显式追加的 grant。
- [ ] 确认模板没有自动写入 `user_roles`，新职责必须由超级管理员显式分配。
- [ ] 对 `root`、`admin`、`content_operator`、`community_operator`、`moderator`、`security_admin`、`operations_admin`、`auditor` 逐一核对导航、工作台、页面读取、动作按钮和数据范围。
- [ ] 验证非 root 不能修改 root、自己或超出自身授权上限的目标；任何操作后仍至少保留一名可登录 root。
- [ ] 验证直接访问未授权 URL 返回明确 403，前端隐藏入口不作为安全边界。

## 3. 数据与审计

- [ ] 卡片、评论、标签、用户、文件和角色的管理端编辑、删除与批量操作均不要求通用操作原因。
- [ ] 高风险动作使用影响说明和明确确认防误触；举报理由、审核结论等业务字段不与通用审计混用。
- [ ] 在隔离数据库中抽样核对审计记录包含 actor、action、resource、before/after、request/correlation id，且写入与业务变更处于同一事务。
- [ ] 验证失败或回滚的业务操作不留下伪成功审计；重复请求不会伪造多条实际变更记录。
- [ ] 核对审计与投递详情只返回白名单字段；IP、Token、密码、验证码、Secret 和原始敏感 payload 不可见。
- [ ] SDK debug 只允许方法、URL、request id、重试次数、状态和耗时等元数据；生产不得记录 request/response body。
- [ ] Card 创建、用户更新和管理端更新均验证 1–1000 字；1001 字返回 422 `errors.content`，历史长正文可读且不提交正文的部分更新仍可执行。

## 4. 前端与兼容入口

- [ ] 新导航只使用 `/apps/content`、`/apps/community`、`/apps/settings`、`/apps/operations`、`/apps/access` 规范路由。
- [ ] 所有旧 URL alias 保留 query 和 hash；详情页、静态路径和动态 `:slug` 不互相遮蔽。
- [ ] 列表搜索、筛选、排序、分页可由 URL 恢复；列设置和命名视图按当前管理员隔离持久化。
- [ ] 配置页离开、刷新和关闭标签页时均提示未保存变更；保存成功后不误拦截。
- [ ] 主题、存储、消息渠道和验证配置遵循“列表 → 详情 → 测试”层级，主题详情使用 `/apps/settings/appearance/themes/:slug`。
- [ ] 200% 缩放/窄屏、键盘焦点、跳过导航、减少动态效果和唯一一级标题验收通过。

## 5. 自动化门禁

按顺序执行，任何一步失败都停止发布：

```bash
npm run generate:sdk
npm run build --workspace @lovecards/sdk
npm run typecheck --workspace @lovecards/admin
npm run test:backend
npm run check:architecture
npm run check
npm run build --workspace @lovecards/admin
npm run generate:admin
npm run test:admin:e2e
```

- [ ] PHP lint 覆盖本次新增或修改的 PHP 文件。
- [ ] 契约工作流、SDK transport/integration、管理端 route/capability 门禁全部通过。
- [ ] 浏览器以生产等价 API 基址完成登录、角色切换、核心列表、配置 dirty guard、运维详情和高风险操作冒烟。

## 6. 发布步骤

1. 宣布维护窗口并暂停会与迁移冲突的后台批处理。
2. 保存数据库备份和当前 Release pointer。
3. 部署兼容后端并执行迁移；确认健康检查、数据库和 Worker 正常。
4. 部署同一契约生成的 SDK 与管理端静态资源。
5. 清理应用缓存，重启必要的 Worker/SSE 进程。
6. 用只读审计员、专项运营角色、普通管理员和 root 各完成一次冒烟。
7. 恢复后台任务，观察错误率、403 异常、投递积压、审计写入失败和数据库锁等待。

## 7. 回滚条件与步骤

出现以下任一情况立即停止继续发布并评估回滚：

- 管理端出现跨角色越权、敏感字段泄露或 root 可用性风险；
- 高风险业务变更成功但审计缺失，或审计成功而业务事务回滚；
- 规范路由/alias 导致关键页面不可达；
- 数据库迁移产生不可接受锁等待、错误率或数据不一致；
- Worker、通知投递或登录链路持续异常且无法在窗口内修复。

回滚顺序：

1. 暂停管理端写操作和相关 Worker，保留证据与 correlation id。
2. 回切上一版应用和前端 Release pointer；不要直接逆向执行数据库 DDL。
3. 仅在迁移文档明确支持且已演练时执行数据补偿；否则保持向前兼容 schema，并发布修复版本。
4. 若存在越权或 Secret 暴露，立即撤销会话、轮换相关凭据并按安全事件处理。
5. 验证登录、读取、旧路径 alias 和最小管理能力恢复后再开放流量。
6. 记录影响范围、决策、操作者、时间线、恢复结果和后续修复项。

## 8. 发布后观察

- [ ] 30 分钟、2 小时、24 小时分别复查 4xx/5xx、前端错误、慢查询、锁等待、Worker 延迟和通知积压。
- [ ] 抽样核对不同角色的导航和工作台没有缓存串用。
- [ ] 抽样核对审计 before/after、请求链路和资源链接可以从列表追到详情。
- [ ] 统计旧 alias 命中量；只有在连续版本无实际使用且另有迁移公告后才能考虑移除。
- [ ] 将发现的问题补入回归测试与架构门禁，不依赖人工记忆。
