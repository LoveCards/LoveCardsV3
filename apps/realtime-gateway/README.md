# LoveCards Realtime Gateway

这是 LoveCards 的独立 Realtime Gateway 原型与协议实现。

它只负责 SSE 长连接和 best-effort realtime signal，不读取业务数据库、不消费 Outbox、不写通知或消息数据。客户端收到 signal 后必须通过业务 API 的不透明游标回源。

## 本地运行

```powershell
$env:LC_REALTIME_TICKET_SECRET = 'local-ticket-secret'
$env:LC_REALTIME_INTERNAL_SECRET = 'local-internal-secret'
node src/server.mjs
```

默认监听 `127.0.0.1:7070`。

## 检查

```powershell
node --test test/*.test.mjs
```

详细协议见 [`docs/engineering/REALTIME-GATEWAY-ARCHITECTURE-2026-11-06.md`](../../docs/engineering/REALTIME-GATEWAY-ARCHITECTURE-2026-11-06.md)。
