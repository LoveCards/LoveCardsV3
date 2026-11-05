# Runtime PR #48 与 Gateway PR #49 集成复审

- 日期：2026-11-06
- 审查基线：`feat/runtime-control-plane` @ `5785c55`；`feat/realtime-gateway-foundation` @ `f14fa97`
- 结论：PR #48 的控制面结构可继续使用，但 Gateway 契约尚未对齐。完成下列阻断项与回归后再进行端到端联调。

## 必须先修正

1. **Gateway Probe 协议不匹配（阻断 Realtime）**：PR #48 的 `RealtimeGatewayProbe` 使用 `LC_REALTIME_GATEWAY_URL`、`LC_RUNTIME_INTERNAL_TOKEN`，期待 `protocol=lc-realtime/1`、顶层 `ready/cursor/auth/transport`。当前 Gateway `/internal/health` 使用 `LC_REALTIME_INTERNAL_URL`、`LC_REALTIME_INTERNAL_SECRET`，返回 `status=ready`、`service=realtime-gateway`、`protocol=lc-realtime-1` 与 `capabilities.sse/cursor/last_event_id/topics`。以已实现的 Gateway 契约为准修改 Probe；验证正确内部凭据可判 ready，错误凭据、错误协议或缺 topic 均降级。不要增加兼容双协议的分支。
2. **实施契约 §4 与当前实现冲突**：`COMMUNICATION-RUNTIME-CONTRACT-2026-11-06.md` 仍要求 Redis pub/sub、Gateway 直接 `source-poll`、数据库 `stream_tickets` 与后端验票接口。原设计 §4.3 明确本轮使用带内部凭据的 HTTP Publisher，Redis 仅在测出瓶颈后评估。Gateway 不读 MySQL、不消费 Outbox。把 §3、§4、§7、§8 中对应条目改为 PR #49 的 HMAC ticket、`GET /me/notifications/stream-ticket`、内部 HTTP signal 和客户端低频 API 补偿；运行环境统一 Node 22+。不创建未实现的 `source-poll` 能力。
3. **模式门控缺失**：让 ticket 签发和 `HttpRealtimePublisher` 通过 Runtime Public 能力读取 `effective_mode`。Polling、Gateway 不可用或 Worker 心跳过期时，ticket API 使用现有 `503/9012` 能力不可用响应，Publisher 不发 HTTP 请求。模式判断只放在通信基础设施/Host 边界，不进入通知业务规则。覆盖切换、降级和恢复测试。
4. **主题所需状态 API 缺失**：设计中的 `/runtime/communication`（或等价的认证主题 API）仍未实现。主题必须读到 `effective_mode`、轮询频率、`revision` 与降级原因，才能统一驱动通知、未读数和私信；Admin 专用 `operations.runtimeConfig()` 不能代替用户端契约。由主开发明确所有权并生成 SDK 类型。

## 代码与文档修正

5. `SchedulerRunProbe` 只按最新非 running 记录的时间判断 ready，因此最近一次 `outcome=failed` 仍会显示 ready。以最新一次运行结果为准，失败应显示不可用与明确原因；Scheduler 仍为 advisory，不阻断 Realtime。
6. `UpdateRuntimeConfig::apply()` 的无效输入调用 `recordApply()` 改写配置行的 `last_apply_*` 和 `updated_at`，但不增 revision、也不审计；并发管理员可能看到另一个请求的失败状态。建议 422/409 不修改配置行，失败尝试若需留痕写独立审计。`CommunicationSettings::validateInput()` 也应自己校验必填 `desired_mode/expected_revision` 和严格正整数 revision，不能只依赖路由 Validate。
7. 实施契约 §2.3 称 `20261106000003.sql` 是通知策略迁移，但 PR #48 的 000003/000004 实际是角色授权差分。按已提交迁移修正文档；策略迁移另选尚未占用的序号，避免账本和后续 Phase 3 冲突。
8. `CommunicationControlPlaneBaseline.php` 的架构守卫扫描整个 `Platform` 模块，禁止出现 `effective_mode` 等字面量。完成第 3 项门控时，通信基础设施必须能读取 Runtime 快照；把守卫限定到业务 Application/Domain，明确允许 Eventing adapter 和 Host 入口访问模式，不能为让测试通过而把判断散落到前端或跳过门控。

## 集成与验收顺序

1. 在独立集成分支合并 PR #48、#49；人工解决 `provider.php`、`RUNTIME-ARCHITECTURE.md`、契约与 SDK 生成物冲突，随后运行完整生成链。不要直接修改生成文件。
2. 本地启动 Gateway，用 PHP Probe 和 Runtime 快照验证 `polling → realtime → Gateway/Worker 故障回退 → 恢复`；验证通知首次写入 signal、dedupe 不重发、消息 signal 与客户端 API 回源。Gateway 的一次性 HMAC ticket 已有 PHP/Node 互通验证。
3. 执行 `npm run check`、Gateway 测试、PHP 语法检查、真实 SDK integration 与主题/Admin E2E。云端 dev 现有服务不重启；需要部署新进程时使用独立端口并先安排联调窗口。

PR #49 的交接任务清单见 `COMMUNICATION-RUNTIME-INTEGRATION-HANDOFF-2026-11-06.md`。
