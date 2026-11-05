# Realtime Gateway 独立进程技术架构

- 状态：Design baseline v1
- 日期：2026-11-06
- 适用项目：LoveCardsV3
- 实现目录：`apps/realtime-gateway`
- 当前实现：Gateway foundation、后端短期 ticket 签发和通知新建 signal 已完成；Runtime 模式切换、前端 EventSource 和云端进程托管仍需联调接入。
- 责任边界：本程序只维护浏览器长连接和低价值实时唤醒，不承载业务事实、不消费 MySQL Outbox、不写通知表。

## 1. 目标

Realtime Gateway 是一个独立的 Node.js 进程，负责：

1. 接受经过短期 ticket 授权的浏览器 SSE 连接；
2. 按 recipient scope 管理连接；
3. 接收 Event Worker 发布的低价值 realtime signal；
4. 向匹配连接发送标准 SSE 帧、心跳和重连提示；
5. 暴露可被 Runtime Probe 验证的 health/capability 协议；
6. 在进程退出、连接断开和信号丢失时保持可恢复。

## 2. 非目标

- 不直接读取业务数据库；
- 不直接消费 `outbox_events` 或 `event_deliveries`；
- 不创建或修改 `notifications`、`messages` 等业务记录；
- 不把推送 payload 作为权威业务数据；
- 不在 PHP-FPM 请求中模拟长连接；
- 不实现 Supervisor；Supervisor/systemd/容器编排由部署环境负责。

## 3. 进程边界

```text
PHP API / Event Worker
  -> Gateway Publisher Adapter
  -> POST /internal/signals
  -> Realtime Gateway
  -> GET /stream?ticket=...
  -> Browser EventSource
```

MySQL Outbox、Delivery 和通知/消息表仍是权威事实源。Gateway 信号只包含：

```json
{
  "signal_id": "opaque-id",
  "topic": "notifications|messages",
  "recipient_key": "user:123",
  "cursor_hint": "opaque-cursor",
  "occurred_at": "2026-11-06T00:00:00.000Z"
}
```

不允许携带密码、Token、完整消息正文或完整通知对象。

## 4. 连接协议

### 4.1 Health

```http
GET /health
```

返回 `200` 表示进程已启动且可以接受连接；返回体至少包含：

```json
{
  "status": "ready",
  "service": "realtime-gateway",
  "protocol": "lc-realtime-1",
  "version": "dev",
  "capabilities": {
    "sse": true,
    "cursor": true,
    "last_event_id": true,
    "topics": ["notifications", "messages"]
  },
  "limits": {
    "max_connections": 1000,
    "max_connections_per_recipient": 3
  }
}
```

`Runtime Probe` 必须验证协议版本、能力字段和内部认证配置，不能只探测端口。

### 4.2 Stream

```http
GET /stream?ticket=<short-lived-ticket>&after=<opaque-cursor>
Accept: text/event-stream
Cache-Control: no-cache
```

- ticket 只允许一次连接握手使用，TTL 默认 60 秒；
- ticket 绑定 recipient、topic scope、audience 和过期时间；
- 不接受长期 JWT 作为 query 参数；
- `after` 只作为回源游标提示，Gateway 不解释其业务语义；
- 浏览器重连时可使用 `Last-Event-ID`，Gateway 只把它作为 cursor hint 继续发送；
- Gateway 建立连接后发送 `event: ready`；
- 有新信号时发送 `event: signal`，`data.topic` 为 `notifications|messages`；客户端只用它触发 API 回源；
- 每 15 秒发送注释心跳 `: ping`，避免代理关闭空闲连接；
- 单条连接最长存活默认 15 分钟；到期发送 `event: shutdown` 后关闭，客户端重新取得 ticket 并通过 API 回源；
- 连接也可由客户端、Gateway 优雅停止或代理超时关闭。

### 4.3 Internal Signal

```http
POST /internal/signals
Authorization: Bearer <gateway-shared-secret>
Content-Type: application/json
```

请求体必须符合 Signal schema。无效 topic、recipient scope、过长 cursor、过期时间或重复 signal 必须返回 `422`；无效内部凭据返回 `401/403`。

信号是 best-effort：Gateway 重启或 Redis/HTTP 传输失败不会造成业务数据丢失。客户端收到信号后仍必须调用通知/私信游标 API 回源。

## 5. 认证与安全

- Stream ticket 使用 HMAC SHA-256 签名；密钥只通过环境变量注入；
- ticket payload 至少包含 `aud`、`recipient_key`、`topics`、`iat`、`exp`、`jti`；
- Gateway 必须拒绝错误 audience、过期、未来时间过远、重复 jti 和不支持 topic；
- internal signal 使用独立 shared secret，不复用用户 JWT；
- `/health` 不暴露 secret、连接明细、用户数据或内部错误堆栈；
- 生产环境由 Nginx/网关限制来源、TLS、请求体大小和内部路由可达性；
- 单 recipient 连接数和全局连接数都有上限；超限返回 `429`；
- 只记录 hash 后的 recipient、signal_id 和错误摘要，不记录 ticket 原文。

## 6. 生命周期与故障

### 启动

1. 读取并校验环境变量；
2. 校验 ticket secret 和 internal secret 非空；
3. 创建 HTTP server；
4. 进入 `ready`；
5. Runtime Probe 通过 `/health` 判断能力。

### 优雅停止

收到 `SIGTERM`/`SIGINT` 后：

1. 停止接受新连接和内部信号；
2. 向已有连接发送 `event: shutdown`；
3. 设置短暂 drain deadline；
4. 关闭所有连接和 HTTP server；
5. 进程退出码为 0。

### 传输故障

- 内部信号发送失败只影响实时延迟，不影响业务事务；
- 客户端断线后由前端按 cursor 回源；
- Gateway 重启不会恢复旧连接，前端必须重连；
- Gateway 不负责补发历史信号；历史数据由 API 游标补偿。

## 7. 背压与资源控制

- 每条连接只保存连接元数据，不保存业务对象；
- 单次 signal 广播到连接时使用固定帧大小上限；
- 写缓冲积压超过阈值时关闭慢连接，让客户端通过 cursor 补偿；
- 广播遍历不得阻塞其他 recipient；
- 单个请求 body 默认不超过 16 KiB；
- 连接、signal、认证失败和慢连接都提供计数器。

## 8. 配置

| 环境变量 | 默认值 | 说明 |
|---|---:|---|
| `LC_REALTIME_HOST` | `127.0.0.1` | 监听地址 |
| `LC_REALTIME_PORT` | `7070` | 监听端口 |
| `LC_REALTIME_VERSION` | `dev` | 发布版本 |
| 协议版本 | `lc-realtime-1` | 当前固定值，升级时须同时更新后端 Probe 与 ticket 签发器 |
| `LC_REALTIME_TICKET_SECRET` | 无 | 必填 |
| `LC_REALTIME_INTERNAL_SECRET` | 无 | 必填 |
| `LC_REALTIME_HEARTBEAT_SECONDS` | `15` | 5..120 |
| `LC_REALTIME_TICKET_TTL_SECONDS` | `60` | 15..300 |
| `LC_REALTIME_MAX_CONNECTIONS` | `1000` | 1..100000 |
| `LC_REALTIME_MAX_CONNECTIONS_PER_RECIPIENT` | `3` | 1..20 |
| `LC_REALTIME_MAX_CONNECTION_SECONDS` | `900` | 60..3600，单连接存活上限 |
| `LC_REALTIME_DRAIN_SECONDS` | `5` | 1..30 |

配置解析失败必须拒绝启动，不能悄悄使用不安全默认 secret。

## 9. 与 LC Runtime 的接入顺序

1. 先部署 Gateway 并通过 `/health` 能力探测；
2. 后端 Runtime 将 Gateway 标记为 ready；
3. 后端签发短期 stream ticket；
4. 前端用 ticket 建立 EventSource；
5. Event Worker 通过 Publisher Adapter 发送 signal；
6. 前端收到 signal 后按 cursor API 回源；
7. Gateway 不可用时 Runtime 保持 polling effective mode。

当前后端已提供 `GET /me/notifications/stream-ticket`，由 `HmacRealtimeTicketIssuer` 签发一次性短期 ticket；通知首次持久化后由 `HttpRealtimePublisher` 发送低价值 signal。通知 signal 暂不携带 cursor hint，浏览器收到后需调用通知 API 回源；不得把通知自增 ID 伪装成不透明 cursor。两者都采用 best-effort 语义，不改变业务事务结果。

后端 ticket 与通知 Publisher 已接入，但 Runtime 模式切换、私信 signal、主题 EventSource 和云端进程托管尚未完成；不得将当前基础接入视为完整业务链路。

## 10. 验收

- health 返回协议和能力字段；
- 无 secret、过期 ticket、错误 audience、越权 topic 不能建立 stream；
- SSE 帧使用真实 CRLF/空行分隔，ready、ping、signal、shutdown 均可解析；
- 内部信号必须鉴权且只投递到匹配 recipient/topic；
- 重复 signal 不导致连接异常或业务重复；
- 慢连接被限制后可通过游标恢复；
- SIGTERM 能在 drain deadline 内关闭；
- 测试不连接云端、不写真实数据库、不修改现有 dev 服务。
