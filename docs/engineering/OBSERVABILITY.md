# 观测、日志与审计

LoveCards 在 ThinkPHP 日志能力上提供系统级适配，而不是让每个模块直接调用 Facade。业务代码面向 PSR `LoggerInterface`；组合根注入 `ThinkPhpLoggerAdapter`，并统一增加追踪上下文、脱敏和故障隔离。

## 技术日志链路

```text
业务/Application
  -> PSR LoggerInterface
  -> ThinkPhpLoggerAdapter
  -> SensitiveDataSanitizer
  -> LogContextProvider
  -> ThinkPHP logger/channel
```

每个请求创建 `request_id`，没有显式 `correlation_id` 时使用同一个值。异步事件把 correlation/causation 写入 EventEnvelope，Handler 日志继续使用该链路。业务传入的 `request_id`/`correlation_id` 会被丢弃，以平台上下文为准，避免伪造追踪链。

`ThinkPhpLoggerAdapter` 清洗后调用 ThinkPHP logger；底层技术日志渠道故障不会反向破坏普通业务流程。日志结构至少包含 level、message、request/correlation id、模块/动作、稳定资源标识和耗时，不记录任意 ORM 对象或完整请求体。

## 脱敏

`SensitiveDataSanitizer` 同时清洗结构化 context 和自由文本：

- password/passwd/pwd、secret、token、authorization、cookie；
- private/api/access key、recovery/verification code、OTP；
- `Bearer ...` 和文本中的常见 `key=value`/`key: value` 凭证；
- 二进制文本、过深结构、超长字符串和不支持的对象/资源。

异常只保留 class、清洗后的 message 和 code。任何新日志字段如果包含凭证、可恢复秘密或完整个人隐私，必须先扩充脱敏测试，不能依赖调用者“记得不写”。

SDK 的 `debug` 仅允许输出请求方法、URL、request id、重试次数、HTTP 状态和耗时等元数据；不得输出请求 body、query、响应 body 或完整 Axios 配置。生产环境必须关闭 SDK debug，若诊断需要临时开启，也只能使用元数据日志。

## 审计日志

审计与技术日志用途不同：高风险动作通过 `AuditLogger` 持久化到 `audit_logs`，字段包括 actor、action、resource type/id、request id、correlation id、IP hash、payload 和时间。原始 IP 不入库；`APP_AUDIT_HASH_KEY`/`app.audit_hash_key` 应配置为实例秘密，用于不可逆 HMAC。

必须审计的类别包括：

- 登录、账号恢复、凭证变化、Session 撤销；
- 角色/capability 变化和管理操作；
- 举报状态转换、隐私偏好与高风险内容治理；
- 事件手工重试、文件永久清理；
- Release 上传、升级推进、Contract 批准和恢复。

要求“业务写入与审计同成败”的用例必须在同一数据库事务中调用审计；审计写入失败时 fail closed。只读诊断或普通技术日志仍可 fail open。

管理端普通内容维护（卡片/评论/标签的编辑、审核、置顶与取消置顶）不要求操作者重复填写自然语言原因；服务端必须静默记录操作者、动作、资源、`before`、`after`、`request_id` 与 `correlation_id`。删除、恢复、永久删除、权限或角色分配、系统升级/恢复等高风险或不可逆动作仍必须携带非空 `reason`（最多 255 字符）。服务端只在真实状态发生变化时逐资源写入审计；重复提交或无变化操作不产生伪审计，业务失败时审计随同一事务回滚。请求标识只能来自平台请求上下文，客户端不能在 payload 中伪造。

审计记录面向调查，系统通知面向受影响用户；高风险用例经常需要两者同时存在，不能互相替代。哪些动作必须通知以及通知与运维告警的边界统一见 [NOTIFICATIONS.md](NOTIFICATIONS.md)。

管理端 `/apps/operations/audit` 通过 `client.operations.auditLogs()` 分页查看动作、资源、request/correlation id 和经过白名单的 payload。普通业务角色不能读取审计表，也没有审计删除 API。

## 运行与告警

最小环境可以使用 ThinkPHP 本地轮转文件和 MySQL 审计表；完整环境可把结构化日志采集到集中系统，但不能改变模块日志 API。建议至少监控：

- 5xx 与参数/权限错误率；
- Event delivery pending age、retry/dead-letter 数量；
- Worker 心跳过期、Lease 冲突；
- 数据库连接、慢查询、锁等待；
- 升级失败 step、恢复动作和备份状态。

日志保留、审计归档和隐私删除策略属于运维配置。审计不可由普通内容删除流程级联清除；涉及用户隐私删除时保留最小合规证据并去标识化。
