# LoveCardsV3 基础设施与扩展实现审计

审计日期：2026-08-20

## 结论

当前架构已经能够自然演进草稿、社交关系、通知/私信、账号恢复、收藏/历史、举报/隐私、媒体深链和评论定位，不需要先把 ThinkPHP 改造成常驻内存 Web，也不需要强制引入 Redis/RabbitMQ。关键公共能力已经从单项功能中抽出：可执行 API 契约、用户/资源范围、MySQL Outbox、可裁剪运行时、系统生命周期、日志审计、稳定媒体标识和 SPA Theme Gateway。

本轮不是“所有需求共用一张表”，而是模块化单体：共享身份、ResourceRef、事件、审计、SDK 和运行时协议；对访问模式和生命周期不同的数据使用独立表。这个边界能够服务中大型 Web 社区，并允许在测量到瓶颈后替换唤醒/队列/存储适配器，而不重写业务模块。

## 交付范围

### 社区八项能力

| 能力 | 状态 | 主要证据 |
| --- | --- | --- |
| 草稿箱 | 已实现 | Drafts Application/Repository、`card_drafts`、5 条 API、默认主题草稿页 |
| 他人主页、关注与粉丝 | 已实现 | Social Application/Repository、`user_follows`、6 条 API、公开主页/关系列表页 |
| 通知、私信 | 已实现 | Notifications/Messaging、Outbox Handler、8 条 API、Inbox/Messages 页面、可选 SSE |
| 账号恢复 | 已实现 | Recovery challenge、security version、Session 全撤销、2 条公开恢复 API、恢复页面 |
| 收藏与浏览历史 | 已实现 | Library、两张独立表、6 条 API、收藏/历史页面；DELETE query 契约已修复 |
| 举报、隐私偏好、拉黑 | 已实现 | Safety/Reports、5 条成员 API、3 条管理 API、隐私页与举报治理页 |
| 独立媒体深链 | 已实现 | `/media/:hash` 稳定公开文件；有序 `media[]`；HEAD/Range；状态撤权；SPA `/media/:cardId?index=n` 查看器 |
| 评论深链及自动定位 | 已实现 | `/post/:cardId/thread/:commentId`、公开详情、父评论同卡/状态校验、安全删除占位、展开与 scrollIntoView |

详细数据归属与边界见 `COMMUNITY-CAPABILITIES.md`。

### 公共基础设施

- SystemLifecycle：离线签名 Release Builder、安全 ZIP 导入、不可变 Release、持久升级计划、Lease、备份 Gate、迁移 phase/cursor、Contract 独立批准、原子 current pointer 与恢复。
- Runtime：PHP-FPM/MySQL 最小形态，Redis 实时信号、Worker、Scheduler、SSE 按所选策略启用；MySQL 是可靠状态源。
- Eventing：EventEnvelope、显式 Handler Registry、Outbox/delivery 状态机、claim lease、retry/dead-letter、request-safe/worker 双执行模式。
- Observability：PSR Logger -> ThinkPHP Adapter，request/correlation context、统一脱敏和独立 MySQL 审计。
- API/SDK：Route Meta/Contract -> Validate scene -> Resource -> audit/OpenAPI -> 25 个生成 Resource -> SDK dist -> 主题公开 bundle。
- Theme：SPA-only、安全 ZIP、不可变主题 Release、Host Binding、Bootstrap、History fallback、Release asset/ETag/immutable cache。

### 管理端 1–6

1. 举报列表、详情、状态转换和处理审计。
2. 恢复挑战查看、用户安全概览、单/全部 Session 撤销。
3. Outbox 投递状态、通知统计和失败投递重试。
4. 结构化审计日志与 request/correlation 追踪。
5. Runtime Facility/Worker 状态、迁移账本、Release 上传/计划/推进/Contract/恢复。
6. 草稿、收藏和历史的隐私保护聚合统计。

这些入口都在 `config/navigation.ts` 声明 capability，并由后端 Route access 作为最终授权边界。

## 契约与验证证据

| 验证 | 2026-08-20 结果 | 说明 |
| --- | --- | --- |
| Route/API audit | 171 routes；P0=0、P1=0、P2=0 | `contracts/route-manifest.json`、`contracts/API-AUDIT.md` |
| 后端测试 | 39/39 passed | 行为、授权、数据库、Theme、Eventing、Lifecycle、上传边界和 UTF-8 Migration 解析等递归测试入口 |
| PHP 语法 | 541/541 passed | PHP 8.0.2 本地兼容回归与 PHP 8.2 门禁均由 `rg --files apps/backend -g '*.php'` 枚举并逐文件 lint；正式支持基线仍为 PHP 8.1–8.x，忽略目录不计入 |
| SDK integration | 133 passed、0 failed、24 skipped | 真实 JWT/HTTP；Skip 来自关闭 Visitor Mode、无 active theme 或隔离库没有可用内容/文件 fixture；另验证了手动 Contract 直跑被拒绝；逐项见 SDK TEST_REPORT |
| 默认主题 | 20 files、113 tests passed；build passed | 包含社区 service、深链、媒体与 UI 完整性 |
| 管理端 E2E | 28 passed | 3 项认证场景和 25 个固定/动态管理路径 |
| Theme 真实 E2E | passed | SPA entry fallback、公开 API、深层路由、Bootstrap/Release 资源、登录、发布和评论 |
| 根质量门禁 | `npm run check` exit 0 | 后端 39/39、171 routes、217 admin grants/23 navigation entries、API P0/P1/P2=0、26 个 SDK 生成文件、26 个 transport 请求、类型与默认主题 |
| 管理端构建 | passed | Nuxt/Nitro production build；沙箱外运行以允许构建工具读取运行时目录 |
| 系统升级真实 E2E | passed | 一次性数据库、真实 JWT、签名 ZIP multipart 上传；21 次单元推进覆盖 waiting、独立批准、Contract SQL、激活、pointer、Release header 与 health |

真实 SDK 回归已验证社区列表在不传可选参数时成功；一个 scene 不再要求另一个 scene 的字段。空消息只返回 `body` 错误、空已读只返回 `ids`、非法举报只返回举报字段错误。密码变更后旧 JWT 401、重新登录恢复；无参配置读取成功。

阶段封存前又在 phpStudy 的 PHP 8.0.2 + MySQL 5.7 实例完成兼容回归：修复 Route Contract 中 PHP 8.1 关联数组展开、数字 Migration ID 严格类型和 UTF-8 SQL/phase 拆分问题；正式迁移入口已将开发库账本推进到全部 complete。`/api/session/check` 与 `/api/me` 未登录返回 401，`/api/cards` 返回 200，三者均不再返回 500。本地数据库凭据保存在 Git 忽略的实例持久配置中，不进入仓库。

系统升级还通过 Release 签名/文件 hash、Zip Slip/symlink、迁移与 Coordinator 行为测试，管理 API 的列表、404 和非法 multipart 错误也经过真实 HTTP。真实升级演练在临时 `LC_INSTANCE_ROOT` 与一次性数据库中完成，没有把开发工作区当作目标实例。

当前生产迁移文件没有 `contract` phase。为验证通用机制，真实 E2E 在临时复制的 Release source 中注入 E2E-only 分段迁移，实际覆盖提前批准拒绝、waiting、独立批准持久化、Contract step/milestone 与 SQL probe；生产 migration 未被修改。激活后/Contract 前的一次性 pointer 恢复仍由专项状态机测试覆盖；尚未声称真实 HTTP 演练自动恢复数据库或文件。数据库 Artifact 当前只能由维护窗口中的人工流程导入，普通 `recover` API 只回切 Release pointer 并执行 reload/health-check。

## 风险与后续容量工作

### P0/P1

本次 API audit 未发现 P0/P1/P2 契约问题；已执行门禁没有失败项。

### 非阻断技术债（P2 运维/性能）

- 默认主题主 JS 约 800.62 KB（gzip 约 240.75 KB），构建触发 500 KB chunk warning；建议按社区页/管理较重区域做路由级懒加载并测量首屏。
- 管理端主 client chunk 约 512.76 KB，CSS entry 约 636.57 KB；应以真实性能采样决定 Vuetify import 与页面拆包优化。
- 构建出现依赖包 trailing-slash export 的 Node deprecation warning；不影响当前产物，升级依赖时处理。
- MySQL Outbox 的上限应以 pending age、claim 冲突、数据库负载和 retry rate 测量。尚无证据要求立即引入专用 MQ。
- SSE 在 PHP-FPM 中适合受限连接；高并发实时订阅达到容量阈值后，可增加独立 realtime adapter，HTTP 刷新/轮询契约继续保留。
- 当前升级 Gate 的数据库备份是 PHP 逻辑导出，本地文件备份是带 Manifest 的 ZIP；大数据量上线前必须实测耗时、临时磁盘和恢复目标，并在需要时为 `BackupArtifactStore` 增加数据库原生备份/对象存储快照适配器。
- 当前 `recover` 是代码 pointer 恢复，不是数据库/文件自动回滚。通用 Contract waiting、批准和 SQL 执行已由真实 HTTP E2E 覆盖；首次引入生产 Contract migration 前，仍必须用实际迁移内容在可丢弃实例补做容量、锁影响及数据库/文件灾备恢复操作手册演练。

卡片列表媒体已改为整页一次读取引用账本、一次读取文件记录，并由专项查询次数测试保护；它不再列为 N+1 技术债。

## 两个核心问题

### 1. 需要先做架构升级吗？

关键架构升级已经完成，现在可以在当前模块化单体上自然演进。后续功能应继续通过现有 Port、Outbox、Lifecycle、Route Contract 和 SDK 链扩展，而不是先做框架重写。只有容量数据证明 MySQL Outbox、FPM SSE 或当前存储驱动成为瓶颈时，才替换对应 Infrastructure adapter。

### 2. 哪些公共能力必须保持统一？

后续功能不得各自实现以下能力：

1. 身份、Session security version、capability + scope 和 owner/recipient/resource 范围；
2. Route Contract、Validate scene、Resource、OpenAPI、生成 SDK 与 transport 检查；
3. 事务内 Outbox、Handler Registry、幂等、claim/retry/dead-letter 和 Worker 心跳；
4. PSR 日志适配、request/correlation context、敏感数据清洗和审计；
5. Migration checksum/phase/cursor、Release 签名、备份 Gate、Contract 批准和恢复；
6. 文件引用、稳定 public hash、公开性检查、purge 状态机和媒体 URL；
7. Theme Bootstrap、Host Binding、History fallback 与 SDK 同源访问；
8. 管理端 capability 导航、共享状态组件、危险操作确认与统一通知。

以上公共能力先于单项功能存在，正是避免草稿、消息、举报、升级等各维护一套队列、日志、权限、请求解析和恢复机制的边界。
