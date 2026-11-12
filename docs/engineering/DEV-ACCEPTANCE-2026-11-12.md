# dev 部署与验收记录

日期：2026-11-12。范围：平台事件、前端通信、安装与升级生命周期收敛。功能部署提交 `beb304e0`，服务器分支 `dev-sse-preview`。未碰生产站点、未改防火墙、未重启 6001/20080/20088/20089。

## 已完成

- 本地根 `npm run check` 通过：后端 111/111、Gateway 18/18、默认主题 308/308，架构、路由、管理授权、SDK 生成和类型门禁全绿；136 个受影响 PHP 文件 lint 无错误；管理端生产构建通过。
- 先前隔离 MariaDB 基线：干净安装与旧版迁移终态 schema 一致；签名 Release 升级 E2E 完成 22 次逐单元推进、Contract 独立批准、Pointer 激活和最终健康；冷启动数据库备份和安装 root 密码持久化测试通过。
- S11 候选 PR #57 的 GitHub CI [43177057837](https://github.com/LoveCards/LoveCardsV3/actions/runs/43177057837) 全绿：MySQL 8 的新装/升级终态 schema 对比、签名升级 E2E、PHP 8.1/8.2、前端综合检查、管理端构建、SDK/管理端/主题 E2E 和根构建均通过。S11 只在隔离环境验证，未部署共享 dev。
- 隔离 SDK 集成 169/169；管理端全量浏览器 179 项中，178 项首跑通过、1 项 Nuxt 文档请求超时后重试通过，最终 0 失败。重试属于测试服务不响应，未发现对应页面断言失败。
- dev 部署前检查为 `cc8b36fc`，唯一既有未提交文件是 `packages/sdk/test/TEST_REPORT.md`。暂停现有 cron、`lovecards-dev-event-worker.service` 和 `lovecards-dev-realtime-gateway.service` 后，通过 HTTPS fetch 快进到 `beb304e0`；服务与 cron 已恢复。
- dev 使用现有 `LocalBackupArtifactStore` 生成并校验升级前数据库备份，Artifact 位于实例持久目录 `shared/backups/dev-pre-convergence-20261111174655.sql`，大小 1,042,019 字节，SHA-256 为 `cee9489a54110bf57b9b00485538cdcf00999d3787b50ff6b54ab53ff6060b246`。数据库恢复仍需经运维流程人工执行。
- dev 迁移文件 49 份、账本 49 条，全部 `complete`，checksum 逐文件一致，失败数 0；定时消费者手动运行成功（本次领取 0）。`lc:runtime:status` 显示事件 `scheduled/ready`、通信 `polling/ready`，Redis、Worker、Gateway、Scheduler 探测均就绪。API 6001 的公开认证策略返回 200、受保护通信配置返回 401；主题 20088 和管理端 `/admin/` 20089 返回 200。20080 在 localhost 无匹配 Host 时返回 421，未作为业务健康证明。
- dev 临时切到 `resident + realtime` 后，经公共同源 `/stream` 成功建立 SSE。临时用户 A 通过 PHP API 发私信给 B，B 的 SSE 连接收到 `messages` 和 `notifications` 信号；临时账号已软删除且会话已撤销。停止 Gateway 的故障注入期间，持久选择仍为 `realtime`，健康变为 `degraded/gateway-unreachable`，没有自动切回 `polling`；重启后恢复 `ready`。验收结束后已切回原有 `scheduled + polling`，cron、Worker 和 Gateway 均已恢复。
- 两个独立浏览器（应用内浏览器与 Chrome）在 dev 用户前端分别以 `admin123`、`USER3A72E` 登录。通过管理端先切 `resident` 再切 `realtime`，前者显示正常、后者显示已选择实时且正常。`admin123` 从用户主页发送一条测试私信后，`USER3A72E` 已打开的消息页在未刷新情况下出现会话、正文及未读提示。之后通过管理端先恢复 `polling` 再恢复 `scheduled`，两个页面均显示正常。测试私信保留在 dev 测试账号会话中。
- 追加双浏览器验收：`USER3A72E` 回发私信，`admin123` 的会话列表自动出现最新摘要，打开会话后正文可读；dev `conversation_members.last_read_message_id` 对本会话两位成员均推进到最新消息 17。`USER3A72E` 公开发布测试卡片 35 时，另一浏览器停留在最新信息流，出现“有新内容，点击刷新”，点击后列表出现该卡片。再次停止 Gateway 后，用户页显示“实时更新暂不可用；手动刷新仍可查看最新内容”，管理页保持已选择实时并明确提示 Gateway 不可达；服务重启后管理页恢复正常，用户页故障提示消失。所有模式再次恢复为 `scheduled + polling`。两条测试私信与卡片 35 留在 dev 测试账号下。
- 服务器原有 `TEST_REPORT.md` 未被覆盖，部署前后 SHA-256 都是 `9733d2af7b2cb9d007ee26a45a151fa3c278160a7677e74b41785facd6b0bffe`。本地相同路径的既有未提交内容曾被第一次 SDK 集成运行误覆盖，原文无法恢复；它未提交和部署，后续集成通过 `LOVECARDS_SDK_TEST_SKIP_REPORT=1` 禁止再写报告。

## 手工使用与验证

1. 打开现有 dev 管理端 `/admin/`。在「系统设置 → Redis」维护连接；保存配置不会自动启动 Redis。事件处理页 `/admin/apps/operations/eventing` 查看定时/常驻策略、触发来源、积压和 Worker 健康；前端通信页 `/admin/apps/operations/communication` 查看轮询/实时选择、Gateway 与 Redis 就绪。
2. 默认保持 `scheduled + polling` 时，用两个普通账号互发私信、触发通知和关注信息流，确认消息及小红点在轮询周期内更新；刷新页面后最终数据仍由 PHP API 读取。异步事件由现有 cron `lc:scheduler:run-once` 处理，不能仅靠浏览器轮询替代。
3. 需要试实时模式时，先在事件处理页切到 `resident`，等待 Worker 显示目标版本与目录兼容且心跳新鲜；再在通信页检查 Redis 与 Gateway 就绪并明确切到 `realtime`。两个账号分别打开浏览器，发私信、读私信、触发通知与关注信息流提示；验证新消息即时提示、已读更新、信息流点击刷新后拉取新内容。发送和列表仍走 PHP API。
4. 故障演练只能在安排好的 dev 维护窗口进行：停止 Gateway 或断开 Redis 后，所选实时模式应显示故障，浏览器不得自动切为业务轮询；API 手动刷新仍应读到持久数据。恢复依赖后确认原模式恢复。退出常驻前先在通信页手动切回轮询，再在事件处理页切回定时并确认 cron 新鲜运行。
5. 升级页 `/admin/apps/operations/upgrade` 只接收签名 ZIP，通过同一 `UpgradeCoordinator` 单步推进；Contract 需要独立权限与批准。Pointer 恢复不等于数据库自动回滚，升级前备份须保留。

## 未完成边界

- dev 公共同源 Gateway 反代、SSE 私信/通知信号和 Gateway 停机故障注入已通过接口级实测；两个独立浏览器的私信即时到达、已读游标推进、信息流提示与点击刷新、浏览器断线提示和管理端模式切换已通过 UI/数据库实测。当前私信界面不展示对方“已读”标记，因此本次验证的是已读游标及新信号驱动刷新，不宣称可见的对方已读徽标。
- dev 此次是源码工作树快进及受审独立迁移 CLI，不是通过签名 ZIP 在该站点跑 `UpgradeCoordinator`；统一升级路径已在隔离 E2E 验证。
- S11 的 `system`、`permissions`、`role_permissions`、`role_capabilities` 已在 dev 只读逐行核对为历史原始种子，当前权限读取 `role_grants`；未在 dev 执行 DROP。候选分支已新增受控退役与过程清理两份迁移，并从目标 schema 移除四表；本地签名包升级经独立 Contract 批准、失败修复重试后与干净安装 schema 一致。用户已决定 dev 保持 Git 工作树直接开发，S11 仅在隔离环境验收。不得将含新迁移的候选分支直接拉入共享 dev：目录与 49 条完整账本不一致会使事件消费失权。将来在托管 Release 环境落地 S11 时，仍须准备真实 drain、签名公钥、健康探测与受控 Contract 升级。
- 默认配置仍是 `scheduled + polling`；没有把「Redis/Gateway 已就绪」误报为「实时通信已开启」。
