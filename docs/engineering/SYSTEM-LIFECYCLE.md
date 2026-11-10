# 系统生命周期、安装与升级

LoveCards 的安装、数据库迁移、Release 导入、激活和恢复属于同一个系统生命周期。当前安装 Host 仍负责首次数据库连接、配置与密钥写入、目标 schema 和默认数据导入及安装锁；历史迁移身份由 SystemLifecycle 记入账本，旧版本结构变更由该模块执行。后续升级不得通过“解压覆盖当前目录 + 临时执行 SQL”绕过账本、签名、备份和恢复协议。

## 安装输入与 Schema 一致性

新安装的输入固定为 `database/schema.sql` 与 `database/seed.sql`：前者是发行版声明的完整目标结构，后者只包含系统默认数据。安装 Host 不再导入旧的组合数据文件，也不在运行时比较两个 schema 或根据 diff 猜测 SQL。

干净安装在导入前断言目标数据库没有数据表；显式 `force` 路径会先执行原有清空操作，再进行同一空库断言。`schema.sql` 和 `seed.sql` 均导入成功后，安装 Host 调用 `Operations/SystemLifecycle/Public/CleanInstallReceipt`，在现有 `schema_migrations` 表以 `__clean_install_schema__` 为键记录当前 `schema.sql` 文件 SHA-256 与按 ID 排序的受审迁移目录 `id => checksum` 映射的组合指纹。已有相同凭据可幂等复用；已有不同凭据或非空 `system_upgrade_steps` 时拒绝补记。SystemLifecycle 获取共享 Lease 后先校验凭据与当前包完全匹配，再将历史迁移记为 `complete`；凭据缺失、文件或目录变化、账本不一致时失败。安装锁写入前再次校验凭据与完整迁移账本。凭据只绑定“安装 Host 已完成本次导入流程”与包文件身份，**不检查数据库的实际结构**；它不执行运行时 schema diff，也不能代替发行阶段干净安装与历史迁移终态一致性测试。旧版本升级仍通过账本推进 phase/cursor、记录失败并由 `UpgradeCoordinator` 编排。在线更新源目前尚未实现；未来接入时必须走同一入口。

发行构建阶段可运行 `node scripts/generate-schema-diff-candidate.mjs --from <old-schema.sql> --to <schema.sql> --output <candidate.json>` 生成结构变更候选清单。工具只输出表、列、索引和外键的 added/removed/changed 记录，明确标记 `review_required`，不生成 SQL、不连接数据库、不写迁移账本，也不被生产安装器调用。字段重命名、数据转换、拆表、合表、删除和其他语义变化必须由维护者写成明确迁移步骤；该候选清单不能直接执行。干净安装与旧版本迁移后的 schema 一致性由隔离数据库验证固定；安装、迁移和健康检查失败不得标记成功。

隔离数据库验收运行 `npm run test:schema:e2e`（先设置 `LOVECARDS_SCHEMA_E2E_ISOLATED=1` 和本地数据库环境变量）；它创建新装和旧版迁移两条路径，再用 `scripts/verify-schema-consistency.php` 比较表、列、索引和外键，忽略自增计数，不读取或输出密码。

事件消费策略单例行 `eventing_consumer_state`、新装默认 `scheduled`/`scheduled_trigger=none` 及 `scheduler_runs.trigger_source` 属于结构与系统默认数据变更：新增结构必须同时进入完整安装 schema 与受审迁移账本；新装默认行进入 `seed.sql`，旧站点由向前迁移按既有通信模式初始化（既有 realtime 取 resident，其余取 scheduled/cli）。不能通过运行时 schema diff 或模块启动时的隐式 DDL 补表。旧 polling 站点切换到停止请求内消费的新 Release 前，`UpgradeCoordinator` 在 pointer 激活前调用 Eventing Public 门禁，要求声明的 CLI 或 HTTP 定时入口最近一小时有成功运行记录；旧版本 CLI 台账的 `legacy` 来源只兼容迁移后的 `cli` 声明。最近运行失败、卡住或来源不匹配时拒绝激活并保留旧 Release。近期运行不能证明未来定时服务一定持续，部署方仍须监控。

旧 realtime 站点按 resident 初始化；新 Worker 无法在 pointer 激活和 reload 之前宣称与目标 Release/Schema/Catalog 匹配，因此事件消费策略门禁不把此作为激活前判定。激活后的 `health_check` 另执行事件消费运行门禁：定时模式要求激活时间之后出现来源匹配、成功且新鲜的 CLI/HTTP 运行；常驻模式要求 Worker 心跳匹配当前 Release、Schema 和 Handler Catalog。若站点选择实时通信，还须在此时通过 Redis、Gateway、Worker 等实时依赖检查。未满足时计划保持失败/待重试状态，不得报告升级成功；管理员修复后重试健康检查或按现有 pointer 恢复协议处理。

## 持久目录与不可变 Release

`config/lifecycle.php` 通过 `LC_INSTANCE_ROOT` 指定实例持久目录。Release、升级包、备份、配置、密钥、运行日志和 active pointer 均位于不可变 Release 之外；未显式配置时，兼容默认值是仓库 `apps/.lovecards-instance`。生产环境应显式设置绝对路径，把它挂载到持久磁盘并纳入主机级备份。

```text
LC_INSTANCE_ROOT/
├─ current                    # 原子写入的 active Release ID
├─ releases/<release-id>/     # 已校验、已解压的不可变 Release
├─ bootstrap/                 # 实例级启动资产预留目录
├─ recovery/                  # 恢复状态预留目录
└─ shared/
   ├─ config/                 # 数据库等持久配置
   ├─ keys/                   # JWT/RSA 等实例密钥
   ├─ state/                  # install.lock 等状态
   ├─ storage/                # 托管模式下的本地媒体
   ├─ themes/                 # 主题 Release
   ├─ runtime/                # ThinkPHP 运行时文件
   ├─ logs/                   # 运行日志
   ├─ backups/                # 数据库、文件、pointer Artifact
   └─ upgrade/<plan-id>/      # 持久化的原始 package.zip
```

`RecoveryKernel` 只接受合法 Release ID、真实 Release 目录和匹配 Manifest 的指针；写指针采用同目录临时文件、flush/fsync 与原子 rename。恢复也必须校验备份路径位于备份根目录内且 SHA-256 匹配。

`public/index.php` 与 `think` 在 Composer/ThinkPHP 启动前调用 `ReleaseBootstrap`。没有 pointer 的旧安装/开发目录继续从当前源码运行；一旦进入托管模式，非法 pointer、symlink、缺失 Release 或 Manifest 不匹配会 fail-closed（Web 返回 503，CLI 以失败退出），不会静默退回旧代码。托管 Release 的响应携带 `X-LoveCards-Release-Id`，`GET /_lc/release-health` 返回当前启动 Release，供升级健康检查使用。

## Release ZIP 信任边界

Release ZIP 根目录必须包含 `release.json` 和 `handler-catalog.json`。包由受信任公钥校验签名，并同时检查：

- ZIP 总大小、条目数、单文件大小、解压后总大小与 Manifest 大小上限；
- 绝对路径、`..`、反斜杠穿越、NUL、symlink 和越出 staging 根目录；
- Manifest 中所有文件的 SHA-256，以及文件集合中没有未声明内容；
- `release_id`、sequence、兼容 PHP/应用版本和 schema 范围；
- Handler Catalog 的规范化 JSON hash 与 Manifest 声明一致。

Handler Catalog 对对象键递归排序、保留数组顺序，并以 `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` 编码后计算 SHA-256。新增、删除或变更异步 Handler 时必须重新生成 Catalog、更新 `file_hashes` 并重新签名；运行中的旧 Registry 不能替代目标 Release 的 Catalog。

## 持久化升级计划

一次升级由 `system_upgrade_plans` 与 `system_upgrade_plan_steps` 持久化，`system_releases` 记录 Release，`system_backups` 记录备份，`system_leases` 防止并行升级。`UpgradeCoordinator` 每次只推进一个步骤，副作用后以 revision 做 CAS checkpoint；PHP-FPM 请求中断后可以从当前步骤幂等重试。

真实顺序如下（HTTP `advance` 每次只完成一个步骤，迁移步骤内部也只推进一个 phase 或一个 backfill batch）：

```text
environment_probe
-> import_release
-> verify_release
-> stage_release
-> schema_check
-> backup_gate
-> preflight_migrations
-> expand_migrations
-> backfill
-> verify_migrations
-> drain_runtime       # 无常驻运行时时可跳过
-> activate_release
-> reload_runtime      # 无常驻运行时时可跳过
-> health_check
-> contract_migrations   # 仅需 Contract 且显式批准时执行
```

升级以 MySQL Lease 串行化，默认 TTL 为 900 秒并在长步骤前后续租。计划仓储会对全部未完成记录做全局判定，创建第二个计划、交错推进/批准/恢复另一个计划都会被拒绝，不依赖“最近若干条”扫描。升级计划和手动迁移共享 `system.lifecycle.upgrade` lease；手动入口在取得同一 Lease 后再次检查活动计划，关闭“先检查、后加锁”的竞态窗口。升级计划执行迁移时复用并续租外层 holder，不做会自冲突的嵌套 Lease 获取。存在未完成升级计划时不能手动迁移，避免两条迁移账本并发。失败会持久化当前 step、attempts 和稳定 failure code；不会因为 HTTP 请求结束而把计划伪装成成功。

## 数据库迁移与 Contract Gate

迁移账本 `system_upgrade_steps` 记录 migration id、checksum、phase、status、cursor、revision、attempts 与 failure code。迁移阶段为 `preflight / expand / backfill / verify / contract`，每次只推进一个 phase 或 backfill 批次：

- checksum 变化视为迁移完整性错误，不允许覆盖旧记录；
- expand/backfill 必须兼容旧代码，contract 可能移除旧结构；
- 只有目标 Release 确认需要 contract 且计划显式批准后，才可继续；
- 大表回填用稳定 cursor 和批次，不把一次 FPM 请求变成长事务；
- `verify_migrations` 必须确认 Manifest 要求的迁移全部完成。

Contract 批准是独立权限 `system.update.contract`；普通升级权限不能隐式批准。数据库规则与表级约束见 `DATABASE-OPERATIONS.md`。

## 备份、激活和恢复

`backup_gate` 必须为数据库、默认文件存储和 active pointer 各生成并校验一个 Artifact，并在激活前再次断言 ready。当前 `LocalBackupArtifactStore` 的能力边界是：

- 数据库：PHP 逻辑 SQL 备份，可校验完整结束标记；应用内没有自动 SQL 导入能力；
- 默认本地存储：带逐文件 hash Manifest 的 ZIP 备份，并提供原子目录交换的恢复原语；
- pointer：普通文件 Artifact，恢复前重新检查根目录、checksum 与 Release ID；
- 非本地默认存储：没有对应云厂商快照适配器时 fail-closed，升级不会继续。

`activate_release` 先原子切换 pointer，再更新 Release 状态；状态写入失败时尝试用已校验的 pointer 备份回滚。健康检查验证：

1. active pointer 指向目标 Release；
2. 新运行时 Handler Registry hash 与目标 Manifest 一致；
3. 当前运行时健康检查通过。

恢复 API/命令只允许在 Release 已激活、有旧 Release、Contract 尚未开始时执行，并且只能成功一次。它恢复计划对应的旧 pointer、更新 Release 账本、按配置 reload/health-check，并记录 `recovered`、`recovered_at`、`restored_release_id`。

这里的“恢复”当前是 **Release pointer 恢复**，不是整站自动回滚：普通恢复请求不会导入数据库 SQL，也不会调用文件 ZIP 的恢复原语。数据库回滚必须在维护窗口由受审计的 DBA/运维流程执行；文件恢复原语也应由明确的灾备编排调用。Contract 一旦进入 running/failed/succeeded，禁止仅回切代码，以免旧代码读取已经收缩的 schema。

## Release 构建与上传限制

签名 Release 由构建机执行，不能在 Web 请求中生成私钥或把私钥放入代码目录：

```bash
php think lc:release:build /absolute/out/release.zip \
  --source /absolute/backend \
  --release-id 2026.08.20-1 \
  --release-version 1.2.0 \
  --build ci-123 \
  --sequence 42 \
  --private-key /secure/keys/release-private.pem \
  --key-id production-2026
```

`lc:release:build` 要求输出 ZIP 和 PEM 私钥都在 source 之外；source 必须是已经安装生产依赖、包含 `vendor/autoload.php`、启动入口和至少一个迁移的完整 backend。Builder 不打包 `.env`（保留 `.env.example`）、实例目录、运行时、测试、源码内 RSA、`public/storage` 或主题 Release；它生成 Handler Catalog、迁移 checksum、所有文件 hash 与 Manifest 签名，最后原子发布 ZIP。私钥口令只从 `LC_RELEASE_SIGNING_PASSPHRASE` 读取。

Builder 还要求 `database/schema.sql` 和 `database/seed.sql`。若 Release source 根目录包含 `release-notes.md`，Builder 会把最多 16 KB 的更新说明纳入签名 Manifest；管理端只显示该 Manifest 的目标版本和更新说明，缺失时明确显示未提供。签名包的来源不会改变 `UpgradeCoordinator` 的执行路径。

上传接口受 `config/lifecycle.php` 的 ZIP 上限保护（默认 512 MiB）；PHP `upload_max_filesize` 至少应覆盖应用上限，`post_max_size` 与 Nginx `client_max_body_size` 还要为 multipart 开销保留余量。若 multipart 在 ThinkPHP 启动前已被 PHP 丢弃，或 PHP 为文件设置 size error，接口返回 413 与 `post_max_size`/`upload_max_filesize` 诊断，而不是把缺失的 `file` 误报成普通业务字段错误。小型虚拟机可以同步下调应用、PHP 和 Web Server 三层上限。

## 运维入口

管理端 `/apps/operations/upgrade` 展示当前版本、目标版本、更新说明、ZIP 上传和计划状态，提供单步推进、Contract 批准与 pointer 恢复动作。`/apps/system/upgrade` 是兼容路由；迁移账本仍由 API/CLI 提供，当前升级页不展示其明细。对应 SDK：

```typescript
client.operations.upgrades({ limit: 50 })
client.operations.upgrade(planId)
client.operations.createUpgrade(formData)
client.operations.advanceUpgrade(planId)
client.operations.approveUpgradeContract(planId)
client.operations.recoverUpgrade(planId)
client.operations.migrations()
client.operations.runMigrations()
```

`runMigrations` 只用于受 `operations.migrate` 授权保护的非-Contract 迁移；请求中的
`allow_contract: true` 会被明确拒绝。Contract 必须经过已激活、已通过 `backup_gate` 的升级计划，
并由拥有 `system.update.contract` 的管理员调用 `approveUpgradeContract`，不能通过手动迁移入口绕过审批。
`lc:system:migrate --contract` 同样 fail-closed；服务器 CLI 也不能成为第二条 Contract 旁路。

CLI 与诊断入口：

```bash
php think lc:system:upgrade create /absolute/release.zip
php think lc:system:upgrade advance <plan-id>
php think lc:system:upgrade approve <plan-id>
php think lc:system:upgrade run <plan-id> --contract
php think lc:system:upgrade recover <plan-id>
php think lc:system:migrate
php think lc:runtime:status
php think lc:release:build /absolute/out/release.zip --release-version 1.2.0 ...
```

管理 API 和 CLI 使用同一个 `UpgradeCoordinator`，不能维护两套升级规则。

## Release ZIP 上传边界与诊断

`ReleaseArchiveLimits` 当前默认限制为：ZIP 512 MiB、20,000 个条目、单文件 128 MiB、总解压体积 1 GiB、Manifest 4 MiB。这个应用层上限必须同时被 Web 入口承载：

- Nginx `client_max_body_size` 至少不小于 PHP `post_max_size`；
- PHP-FPM pool 的 `post_max_size` 建议为 `768M`，`upload_max_filesize` 建议为 `512M`；
- `post_max_size` 必须大于 `upload_max_filesize`，并为 multipart 边界留出余量；
- FPM 的 `php_admin_value` 会覆盖 `.env`，不能只修改应用配置文件。

本地 `npm run dev:backend`、Admin E2E 和升级 E2E 会在启动 PHP 时自动注入上述两个 `-d` 值。可通过
`LOVECARDS_DEV_PHP_POST_MAX_SIZE`、`LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE` 覆盖，或用 JSON
`LOVECARDS_DEV_PHP_INI`（数组 `["post_max_size=768M", "upload_max_filesize=512M"]` 或对象）追加/覆盖 PHP 指令。
这些变量只影响本地启动脚本，不会修改生产 FPM 配置。

生产部署应在 FPM pool 与 Nginx 中显式设置并重载服务，例如：

```ini
; FPM pool
php_admin_value[post_max_size] = 768M
php_admin_value[upload_max_filesize] = 512M
```

```nginx
client_max_body_size 768m;
```

诊断时分别执行 `php --ini`、`php -i | findstr /I "post_max_size upload_max_filesize"`（Unix 使用
`grep`），并检查 Nginx error log。若 `post_max_size` 被超过，PHP 会在 ThinkPHP 启动前丢弃整个
multipart body；诊断依赖请求的 `Content-Length` 与当前 PHP 指令。升级接口返回 HTTP 413，并指出是
`post_max_size` 还是 `upload_max_filesize`，避免误报为“file 不能为空”。Nginx 自己拒绝请求时，响应
由 Nginx 生成，应用无法附加上述 JSON 诊断。

## 已验证范围与尚未自动化的边界

2026-08-20 的历史验收记录为根质量门禁通过、后端递归基线 39/39；它不代表当前分支的测试数量或结果。独立的 localhost/一次性数据库升级 E2E 使用 Builder 生成真实签名 ZIP，经真实 JWT multipart 上传后由 HTTP API 逐单元推进，验证 active pointer、后续响应的 `X-LoveCards-Release-Id` 和持久化 `health_check` 成功状态。`/_lc/release-health` 的路由识别与 Release 标识由持久运行时基线覆盖。

当前迁移目录已有 `contract` phase。E2E 仍在系统临时目录复制 Release source，并仅向该副本注入独立分段测试迁移，验证提前批准被拒、计划等待批准、批准持久化和 Contract SQL probe；这不能代替对每个实际 Contract 迁移的数据量、锁影响与恢复演练。激活后、Contract 前只能恢复一次由 `UpgradeCoordinatorBaseline`、`BehaviorBaseline` 与 `RecoveryBaseline` 的状态机测试保护；生产变更仍需在可丢弃的旧版本数据副本上演练。

对中大型实例，当前 PHP 逻辑数据库备份与本地文件 ZIP 是正确性基线，不代表无限容量方案。上线前应按真实数据量测量备份时长、临时磁盘、锁/快照影响和恢复时间目标；达到阈值后，应通过 `BackupArtifactStore` 接入数据库原生备份及对象存储快照，而不是绕过 `backup_gate`。
