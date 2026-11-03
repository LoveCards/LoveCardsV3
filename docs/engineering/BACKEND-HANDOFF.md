# 后端接手说明

本文件面向继续开发 LoveCardsV3 后端的 Agent/工程师。当前代码是 ThinkPHP 上的模块化单体：PHP-FPM + MySQL 是最小可运行形态，Worker、Redis wakeup、Scheduler 与 SSE 是可裁剪增强；业务模块不得根据部署形态维护两套事实源。

## 已形成的公共底座

- 身份与授权：JWT Session ledger、security version、capability + scope、owner/recipient/resource 范围。
- API 契约：Route Meta/Contract -> Validate scene -> Resource -> API audit/OpenAPI -> 生成 SDK -> 主题公开 bundle。
- 可靠异步：事务内 MySQL Outbox、显式 Handler Registry、delivery claim/retry/dead-letter；请求预算与常驻 Worker 共用处理器。
- 生命周期：安全 ZIP、签名与文件 hash、不可变 Release、迁移 phase/cursor、全局 Lease、三类备份 Gate、Contract 独立批准、原子 pointer 与受限恢复。
- 文件媒体：`file_references`、稳定 `/media/:hash`、公开性同步、purge 状态机、本地 HEAD/Range。
- 可观测性：PSR Logger 到 ThinkPHP 的适配、request/correlation context、统一脱敏、独立审计表。
- 主题运行时：SPA-only Theme Gateway、Host Binding、Bootstrap、History fallback 与不可变 Release assets。

## 社区模块与数据归属

| 能力 | 模块 | 主要数据 |
| --- | --- | --- |
| 草稿 | `Platform/Drafts` | `card_drafts`，按 owner 软删除与 cursor 分页 |
| 关注/粉丝 | `Platform/Social` | `user_follows`，唯一有向边 |
| 通知 | `Platform/Notifications` | `notifications`，recipient 范围、dedupe、read 状态 |
| 私信 | `Platform/Messaging` | conversation/member/message 三表，成员范围和 client id 幂等 |
| 账号恢复 | `Platform/Auth` | `security_challenges`、`auth_sessions`、security version |
| 收藏/历史 | `Platform/Library` | `library_items`、`view_history`，共享 ResourceRef 语义但分表 |
| 举报/隐私/拉黑 | `Platform/Safety` | preferences、relations、reports/events，状态流与证据快照 |
| 文件/媒体 | `Platform/Files` | `files`、`file_references`、purge ledger |

点赞支持 card 与 comment，但仍不应为了表面统一把点赞、收藏、历史、关注、拉黑和举报合并到一张互动表。

## 已固定的安全与一致性约束

1. Controller 读取 query/body/file 只能调用当前路由拥有的 `validated()`；`$all_scene` 决定运行时字段白名单，公开 schema 决定 SDK 签名。无业务参数的 scene 不能退回验证完整规则集。
2. 公开评论详情只返回已发布记录。回复插入前锁定父评论，并验证同卡、未删除、公开；管理详情使用独立入口。根评论删除后，公开线程可使用 `deleted=true` 的安全占位。
3. 卡片写入使用 `media_file_ids` 绑定已完成、正确 scene/MIME、具有所有权的文件；响应返回有序 `media[]`。列表整页只查询一次引用账本和一次文件记录，测试会阻止 N+1 回归。
4. 稳定媒体 URL 只解析安全公开文件。卡片隐藏、删除、恢复、审核状态变化会同步引用公开性；本地 Range 只接受单段合法范围，非法或多段请求返回 416。
5. 全局只允许一个未完成升级计划。活动计划会阻止手动迁移；手动入口永远不能执行 Contract，旧 `allow_contract=true` 只会 fail-closed。
6. `backup_gate` 在任何迁移 phase 之前，必须同时验证数据库、默认文件存储和 active pointer Artifact。普通 `recover` 只恢复代码 pointer，不伪装成数据库/文件自动回滚。
7. E2E 数据库必须是 localhost、名称符合 `lovecards_e2e_*` 且本次调用新建；同名数据库已存在时拒绝并保留，不得清理共享或碰撞数据库。
8. 新增用户可感知的安全、治理、互动或平台状态转换前，必须按 `NOTIFICATIONS.md` 完成通知判定；业务模块不直接写通知表，前端不补发通知。

## 修改接口的标准顺序

1. 先判断状态变化是否影响用户安全、权限、可见性或后续行动；若不通知，必须在设计或测试中记录明确理由。
2. 在所属模块完成 Domain/Application/Contract/Infrastructure；Controller 只做适配。
3. 更新 Route access 与 executable contract，再更新 Validate scene 和 Resource 白名单。
4. 先补行为、权限、事务/幂等、通知判定和 Resource 测试。
5. 执行 `npm run build:sdk` 和 `npm run sync:sdk-theme`，从 Route Contract 生成 OpenAPI/SDK、构建 dist 并同步主题公开 bundle；不手改这些生成产物。
6. 补 SDK transport/真实 JWT HTTP 用例；涉及页面再补 Admin 或 Theme E2E。
7. 执行根 `npm run check`、生产构建、PHP lint 和对应真实 E2E。

完整通知规则见 `NOTIFICATIONS.md`；命令与隔离环境变量见 `BACKEND-TESTING.md`；最终实测数字见 `IMPLEMENTATION-AUDIT-2026-08-20.md` 和 `packages/sdk/test/TEST_REPORT.md`。

## 容量边界

MySQL Outbox、FPM 下受限 SSE、PHP 逻辑数据库备份和本地文件 ZIP 是正确性基线，不代表无限容量。先测 pending age、claim 冲突、连接数、备份时长、临时磁盘和恢复目标；达到阈值后替换对应 Port/Adapter，而不是让业务模块直接依赖 Redis、MQ 或云厂商 SDK。
