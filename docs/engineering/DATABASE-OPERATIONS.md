# 数据库与迁移运维

LoveCards 以 MySQL/InnoDB 为唯一事务基线。数据库变更既服务首次安装，也服务跨版本升级；`database/schema.sql` 是新装目标结构，`database/seed.sql` 只保存系统默认数据，`database/migrations/*.sql` 是旧实例到当前结构的幂等演进路径。当前静态 `SchemaContract` 仍核对历史 `data.sql` 夹具；新旧路径的结构一致性由独立隔离数据库验证，不能把静态检查误当作实际 schema 相等。

## 当前公共表组

| 领域 | 表 | 关键约束/索引 |
| --- | --- | --- |
| 身份与安全 | `auth_sessions`、`security_challenges` | token expiry、renewable window、security version、撤销原因；挑战按 purpose/subject/expiry 查询 |
| 社交 | `user_follows` | follower/followee 唯一边，双方时间索引 |
| 草稿 | `card_drafts` | owner + updated_at + id cursor；`deleted_at` 软删除 |
| 收藏/历史 | `library_items`、`view_history` | `(user_id, resource_type, resource_id)` 唯一；用户时间游标索引 |
| 隐私/安全 | `privacy_preferences`、`user_safety_relations` | 每用户一行偏好；block 等关系唯一 |
| 举报 | `reports`、`report_events` | 状态/更新时间索引；状态变化保留事件，evidence snapshot 不依赖目标继续存在 |
| 私信 | `conversations`、`conversation_members`、`messages` | direct key 唯一；成员未读索引；sender/client_message_id 幂等 |
| 通知 | `notifications` | recipient、read/status、created/id 查询；dedupe key 防重复 |
| 事件 | `outbox_events`、`event_deliveries` | event/handler 唯一投递；queue/mode/status/available_at claim 索引 |
| 文件 | `files`、`file_references` | 稳定 hash、软删除、purge 状态；resource/purpose 与 file 反查 |
| 运行时 | `runtime_workers`、`system_leases` | worker 心跳状态索引；具 TTL 的唯一 Lease |
| 生命周期 | `schema_migrations`、`system_upgrade_steps`、`system_releases`、`system_upgrade_plans`、`system_upgrade_plan_steps`、`system_backups` | checksum、phase/cursor/revision；Release/plan/backup 唯一标识和状态索引 |
| 审计 | `audit_logs` | actor/time 与 resource/time 索引，payload 白名单 |

这些表不是一个通用 polymorphic “互动大表”。收藏和历史可以共享 `resource_type/resource_id` 语义，但写放大、保留周期、唯一性和查询顺序不同，因此分表；点赞通过 `ref_type/ref_id` 支持 card 与 comment；举报另有状态流、证据快照与审计事件。

## 迁移规则

- 每个 SQL 文件有稳定 migration id 和内容 checksum；已应用文件不得原地修改。
- 结构创建使用 `IF NOT EXISTS` 或 information_schema guard；已有列/索引升级必须检查当前结构。
- 新字段先 expand 为兼容形态，分批 backfill 并 verify，最后才允许 contract。
- 禁止迁移中直接 `DROP TABLE`、`TRUNCATE` 或无条件全表 destructive rewrite。
- Backfill 用稳定主键/cursor、小事务和确定排序；失败后从账本 cursor 继续。
- 所有外键、唯一键和高频筛选必须在新装基线与迁移路径中同时存在。
- 安装和 SystemLifecycle 共用的基础 SQL 执行器只运行已明确提供的语句，支持 MySQL `DELIMITER`，每条语句结束后关闭 PDO cursor，避免 pending result sets；运行时不得生成或执行 schema diff。

## 事务与一致性

- 业务写入与需要可靠投递的 Outbox 写入同一事务；Redis publish 不属于提交条件。
- 账号恢复、Session 撤销、举报状态变化和永久文件清理等高风险写入与审计同事务。
- 文件 purge 先锁定文件与引用关系，再改变 purge 状态，避免“检查无引用”后的并发新增引用。
- 卡片列表的媒体装配按整页批量读取 `file_references` 和 `files`，固定为两次查询并保持引用顺序；禁止在逐卡循环中恢复 N+1 查询。
- 消息发送以 `client_message_id` 做幂等；direct conversation 以 `direct_key` 防重复会话。
- 资源所有权是查询条件的一部分，不能读出记录后仅在 Controller 判断。

## 发布与恢复操作

升级计划的 `backup_gate` 位于任何迁移 phase 之前，必须为数据库、默认文件存储和 active Release pointer 分别生成并校验 Artifact；任一项未 ready 时禁止继续迁移或激活。全局只允许一个未完成升级计划，且活动计划存在时手动迁移入口会被拒绝；判定在取得共享生命周期 Lease 后执行，升级内部迁移复用同一个 holder，不能并行写迁移账本。升级过程用：

```bash
php think lc:system:migrate
php think lc:system:upgrade run <plan-id>
```

生产大表 backfill 应在低峰执行并观察锁等待、redo、连接数、复制延迟和队列积压。contract 前确认所有运行实例已切到兼容 Release，并通过独立权限批准。

## 验证

`npm run test:backend` 包含安装基线与历史 Schema Contract，检查表、列类型、索引、迁移幂等守卫、敏感默认值和模型字段。`npm run test:schema:e2e` 在两个一次性数据库分别运行 `schema.sql` + `seed.sql` + 安装账本标记与旧基线 + 历史迁移，再比较表、列、索引和外键；必须先设置 `LOVECARDS_SCHEMA_E2E_ISOLATED=1` 与本地数据库连接环境变量。测试数据库名与安全门禁规则见 `BACKEND-TESTING.md`。支持旧版本的真实数据副本仍需单独演练数据转换和锁影响。
