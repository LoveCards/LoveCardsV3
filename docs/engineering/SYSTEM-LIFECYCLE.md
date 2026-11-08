# 系统生命周期、安装与升级

LoveCards 的安装、数据库迁移、Release 导入、激活和恢复属于同一个系统生命周期。旧安装器只负责环境检查、收集首次安装配置并调用统一数据库入口；后续升级不得再通过“解压覆盖当前目录 + 临时执行 SQL”绕过账本、签名、备份和恢复协议。

## 2026-11-08 设计收敛：完整结构快照与审核迁移

安装和升级继续共用 `Operations/SystemLifecycle` 的 Release、迁移账本、升级计划、备份 Gate、Pointer 和恢复协议。此次收敛不新增第二套安装器、Release Manager、Migration Runner、Backup Manager 或升级状态机，也不改变 `apps/backend/app/modules` 的模块边界；`app/system` 仍然只是 Installer Host。

每个发行版可以携带一份完整的 `database/schema.sql` 作为新安装的目标结构，`database/seed.sql` 只保存系统默认数据。结构差异只在发行构建阶段由工具生成候选迁移，经过开发者审核后随发行包进入现有迁移账本。生产安装器不比较两个 schema，也不动态猜测并直接执行 SQL。字段重命名、数据转换、拆表合表、删除和其他无法由结构差异安全推断的操作，必须作为已审核迁移步骤声明。

`schema.sql` 与迁移不能成为两套手工维护的结构真源。发行构建必须从干净数据库执行当前迁移链并导出目标结构，然后校验新安装得到的结构与迁移后的结构一致。迁移 checksum、phase、cursor、revision、attempts 和 failure code 继续使用本节后文规定的现有账本字段。

用户只看到当前版本、目标版本、更新说明、上传包和升级状态；数据库版本、迁移编号和 SQL 计划属于诊断信息。手动上传包和未来的签名更新源只负责提供同一种 Release ZIP，下载或上传后必须进入同一个 `UpgradeCoordinator`。在线更新源不是安装升级核心链路的前置依赖。

安装流程是生命周期的 Bootstrap 特例：校验 Release、检查环境、写入实例配置、执行完整结构和系统种子、创建首个管理员、写入初始 Release/迁移账本、建立安装锁并执行健康检查。升级流程执行已有的持久计划和 phase 状态机。文件失败可以恢复 Release pointer；数据库恢复依赖升级前备份，不承诺通用的自动 SQL 回滚。

以下规则是本设计的低熵约束：业务模块只提供迁移内容和必要的数据转换，不编排升级；不创建每个版本的专用升级适配器；不创建第二套迁移账本；不在运行时使用 schema diff 代替已审核迁移；普通结构变更由构建工具生成候选，语义变更由明确迁移负责；升级兼容性由 Release Manifest 声明，不能承诺任意旧版本无条件直升。

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

上传接口受 `config/lifecycle.php` 的 ZIP 上限保护（默认 512 MiB）；PHP `upload_max_filesize` 至少应覆盖应用上限，`post_max_size` 与 Nginx `client_max_body_size` 还要为 multipart 开销保留余量。若 multipart 在 ThinkPHP 启动前已被 PHP 丢弃，或 PHP 为文件设置 size error，接口返回 413 与 `post_max_size`/`upload_max_filesize` 诊断，而不是把缺失的 `file` 误报成普通业务字段错误。小型虚拟机可以同步下调应用、PHP 和 Web Server 三层上限。

## 运维入口

管理端 `/apps/system/upgrade` 提供 Release ZIP 上传、计划列表/详情、逐步推进、Contract 批准、恢复和迁移账本视图。对应 SDK：

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

2026-08-20 的根质量门禁已通过，后端递归基线为 39/39。独立的 localhost/一次性数据库升级 E2E 使用 Builder 生成真实签名 ZIP，经真实 JWT multipart 上传后由 HTTP API 逐单元推进；21 次 `advance` 后完成 Contract 与激活，并验证了 active pointer、后续响应的 `X-LoveCards-Release-Id` 和持久化 `health_check` 成功状态。`/_lc/release-health` 的路由识别与 Release 标识由持久运行时基线覆盖。

当前生产迁移目录没有 `contract` phase。为真实验证通用 Gate，E2E 会在系统临时目录复制一份 Release source，并仅向该副本注入分段测试迁移；演练确认提前批准返回 400、计划进入 `contract_migrations` waiting、独立批准持久化、Contract step/milestone 成功且 SQL probe 得到预期标记，随后清除一次性数据库与临时 Release。激活后、Contract 前只能恢复一次仍由 `UpgradeCoordinatorBaseline`、`BehaviorBaseline` 与 `RecoveryBaseline` 的状态机测试保护；未来首次加入生产 Contract migration 时，仍须用实际迁移内容在专用可丢弃实例完成容量、锁影响和灾备恢复演练。

对中大型实例，当前 PHP 逻辑数据库备份与本地文件 ZIP 是正确性基线，不代表无限容量方案。上线前应按真实数据量测量备份时长、临时磁盘、锁/快照影响和恢复时间目标；达到阈值后，应通过 `BackupArtifactStore` 接入数据库原生备份及对象存储快照，而不是绕过 `backup_gate`。
