# 通信运行时集成交接

- 日期：2026-11-06
- 基线设计：`COMMUNICATION-RUNTIME-DESIGN-2026-11-05.md`
- 独立进程设计：`REALTIME-GATEWAY-ARCHITECTURE-2026-11-06.md`
- 当前代码分支：`feat/realtime-gateway-foundation`

## 已完成的基础能力

1. `apps/realtime-gateway` 可独立启动，提供带内部认证的 `/internal/health`、`/internal/signals` 与一次性 ticket 授权的 `/stream`。
2. 后端 `GET /me/notifications/stream-ticket` 签发 HMAC ticket，SDK 已生成 `notifications.streamTicket(): Promise<RealtimeStreamTicket>`。
3. 通知首次入库后发送 recipient 范围内的 best-effort `notifications` signal；dedupe 命中不重复发送；Publisher 失败不影响通知写入。
4. Runtime facility 已由旧 `realtime.sse/fpm-sse` 改为 `realtime.gateway/realtime-gateway`，探测内部认证、协议和能力字段；探测失败保持 `polling` fallback。
5. 旧 PHP 单次快照伪 SSE 路由、SDK 方法和错误格式测试已删除。浏览器仍使用现有轮询，直到统一通信客户端完成。

## 主开发 Agent 的独立任务包

### A. Runtime 控制面

- 按基线设计新增 `runtime_config_state` 单例表、revision CAS、审计和白名单参数校验。
- 后台仅可设置 `desired_mode=polling|realtime`。`effective_mode` 由 Event Worker 心跳与 Gateway capability probe 推导；Realtime 不 ready 时保持 polling，并显示 degraded 原因。
- 新增 Runtime 配置/状态 API、`operations.runtime.configure` 权限和管理端 Runtime 页面。Scheduler/Supervisor 按部署策略独立展示，不能误设为 Realtime 通用硬依赖。
- `.env` 只承担 bootstrap/紧急禁用；不能覆盖已经持久化的后台选择。
- `streamTicket` 仅在 `effective_mode=realtime` 时签发；Polling 或 Gateway 降级时使用现有 `503/9012` 能力不可用响应。未配置 Gateway 密钥时已返回该响应，不能恢复为 500。
- `HttpRealtimePublisher` 在 `effective_mode=polling` 时不发 HTTP signal，避免每条通知承担无用的网络开销；模式判断由 Runtime Public 能力提供，通知模块不直接读配置表。

### B. 通知策略控制面

- 策略在 Outbox/Delivery 入队时冻结版本，Delivery 重试不得重新解释当前策略。
- 强制安全/审核通知不可关闭；可配置通知关闭时留下 `skipped/no-op` 可观测结果。
- 后台策略页与权限、审计、迁移、契约生成同步完成。

### C. 主题统一通信客户端

- 由同一个客户端管理通知未读、通知列表和私信的 `polling|realtime`；移除 `InboxPage` 的 20 秒与 `AppShell` 的 30 秒固定 interval，并让私信在两种模式下都能更新。
- Polling 频率来自 Runtime API，按服务端范围限制；页面隐藏暂停、失败退避、恢复可见后立即回源。
- Realtime 模式先调用 SDK `streamTicket()`，再用 `EventSource` 建立可取消连接。每次重连重新获取 ticket；监听 `ready`、`signal`、`shutdown` 事件，`signal` 的 `data.topic` 区分通知和私信；事件只触发 API 回源，不直接并入 SSE payload。
- Gateway signal 可能在业务事务提交前到达，也可能丢失；Realtime 模式仍须有低频回源补偿。通知与私信按稳定 ID 去重，游标原样传递，不把数据库自增 ID 当不透明 cursor。
- 模式变更、Gateway 故障和登录退出时关闭旧连接，使用最后一次 API 游标补齐。

### D. 消息信号

- 当前 Gateway 支持 `messages` topic，但后端尚无 `message.sent` 到 `messages` signal 的接线。沿现有事件 Handler/持久化边界接入，成功保存消息后发送 recipient signal；失败仍由低频回源补偿。

## 最终联调 Gate

1. 最小环境：不启动 Worker/Gateway，后台与主题只显示/使用 Polling，通知和私信仍正确。
2. Realtime 健康：Worker 新鲜心跳 + Gateway capability probe 通过后，effective mode 才能变为 realtime。
3. Realtime 故障：断开 Gateway 或使 Worker 心跳过期，effective mode 降为 polling，浏览器无需重新登录即可继续回源。
4. 授权隔离：过期、重放、错误 audience/recipient/topic 的 ticket 不能收到其他用户信号；内部路由不能由公网直接访问。
5. 事件补偿：在推送丢失、事务提交前信号、浏览器隐藏/重连、Worker 重试和通知 dedupe 情况下，列表最终与 MySQL 一致且没有重复项。
6. 生成链：路由源文件变更后依次运行 `export:routes`、`audit:api`、`export:api`、`generate:sdk`、`build:sdk`、`sync:sdk-theme`；不要手改生成物。
7. 发布前执行 `npm run check`、Gateway 测试、真实 SDK integration、Admin/Theme E2E；云端 dev 的现有端口和服务不重启，联调需另行安排独立端口和进程托管。
