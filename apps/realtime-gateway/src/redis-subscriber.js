// 极简 Redis RESP2 订阅器（仅 SUBSCRIBE + message 推送解析），零第三方依赖。
// 断线自动重连并汇报订阅状态；只有 SUBSCRIBE 确认后才视为可用。

import net from 'node:net'
import tls from 'node:tls'

const CRLF = '\r\n'

export function encodeCommand(args) {
  let out = `*${args.length}${CRLF}`
  for (const arg of args) {
    const value = String(arg)
    out += `$${Buffer.byteLength(value)}${CRLF}${value}${CRLF}`
  }
  return out
}

/**
 * 增量 RESP 解析器：push 任意 chunk，onReply 收到完整 reply。
 * reply 形式：'OK' 等简单字符串 / [error, msg] / [type, channel, payloadBuffer]
 */
export function createRespParser({ onReply }) {
  let buffer = Buffer.alloc(0)

  const tryParse = (start) => {
    // 返回 [value, consumedBytes] 或 null（数据不完整）
    const newline = buffer.indexOf(CRLF, start)
    if (newline === -1) return null
    const line = buffer.subarray(start, newline).toString('utf8')
    const type = line[0]
    if (type === '+') return [line.slice(1), newline + 2]
    if (type === '-') return [[true, line.slice(1)], newline + 2]
    if (type === ':') return [Number(line.slice(1)), newline + 2]
    if (type === '$') {
      const length = Number(line.slice(1))
      if (length === -1) return [null, newline + 2]
      const end = newline + 2 + length + 2
      if (buffer.length < end) return null
      return [buffer.subarray(newline + 2, newline + 2 + length), end]
    }
    if (type === '*') {
      const count = Number(line.slice(1))
      const items = []
      let cursor = newline + 2
      for (let i = 0; i < count; i += 1) {
        const parsed = tryParse(cursor)
        if (parsed === null) return null
        items.push(parsed[0])
        cursor = parsed[1]
      }
      return [items, cursor]
    }
    return [line.slice(1), newline + 2]
  }

  return {
    push(chunk) {
      buffer = Buffer.concat([buffer, chunk])
      for (;;) {
        if (buffer.length === 0) return
        const parsed = tryParse(0)
        if (parsed === null) return
        buffer = buffer.subarray(parsed[1])
        onReply(parsed[0])
      }
    },
  }
}

export class RedisSubscriber {
  constructor({ host = '127.0.0.1', port = 6379, database = 0, password = '', tls: useTls = false, tlsServerName = '', ca, channel, onMessage, onStatus, connectTimeoutMs = 1000, heartbeatIntervalMs = 15_000, pongTimeoutMs = 5_000 }) {
    this.host = host
    this.port = port
    this.database = database
    this.password = password
    this.useTls = useTls
    this.tlsServerName = tlsServerName
    this.ca = ca
    this.channel = channel
    this.onMessage = onMessage
    this.onStatus = onStatus ?? (() => {})
    this.connectTimeoutMs = connectTimeoutMs
    this.heartbeatIntervalMs = heartbeatIntervalMs
    this.pongTimeoutMs = pongTimeoutMs
    this.socket = null
    this.stopped = false
    this.retryMs = 500
    this.ready = false
    this.retryTimer = null
  }

  start() {
    this.stopped = false
    this.connect()
  }

  connect() {
    if (this.stopped) return
    // TLS 必须验证链与证书名称，握手失败后只重试 TLS，不降级到明文。
    const socket = this.useTls
      ? tls.connect({ host: this.host, port: this.port, servername: this.tlsServerName || this.host,
        rejectUnauthorized: true, ...(this.ca ? { ca: this.ca } : {}) })
      : net.connect({ host: this.host, port: this.port })
    this.socket = socket
    socket.setTimeout(this.connectTimeoutMs)
    // 限制完整认证/选库/订阅握手时间，不能靠零碎数据无限延长连接资格。
    const handshakeTimer = setTimeout(() => socket.destroy(), this.connectTimeoutMs)
    handshakeTimer.unref?.()
    let heartbeatTimer = null
    let pongTimer = null
    let pendingPing = null
    let pingSequence = 0
    const verifyLiveness = () => {
      if (pendingPing !== null || this.stopped || this.socket !== socket) return
      pendingPing = String(++pingSequence)
      socket.write(encodeCommand(['PING', pendingPing]))
      pongTimer = setTimeout(() => socket.destroy(), this.pongTimeoutMs)
      pongTimer.unref?.()
    }
    let stage = this.password ? 'auth' : this.database > 0 ? 'select' : 'subscribe'
    const subscribe = () => socket.write(encodeCommand(['SUBSCRIBE', this.channel]))
    const select = () => socket.write(encodeCommand(['SELECT', this.database]))
    const parser = createRespParser({
      onReply: (reply) => {
        if (Array.isArray(reply) && reply.length === 2 && reply[0]?.toString?.() === 'pong'
          && pendingPing !== null && reply[1]?.toString?.() === pendingPing) {
          clearTimeout(pongTimer)
          pongTimer = null
          pendingPing = null
          return
        }
        if (Array.isArray(reply) && reply[0] === true) {
          socket.destroy()
          return
        }
        if (stage === 'auth' && reply === 'OK') {
          stage = this.database > 0 ? 'select' : 'subscribe'
          if (stage === 'select') select(); else subscribe()
          return
        }
        if (stage === 'select' && reply === 'OK') {
          stage = 'subscribe'
          subscribe()
          return
        }
        if (Array.isArray(reply) && reply.length === 3) {
          const kind = reply[0]?.toString?.() ?? ''
          if (stage === 'subscribe' && kind === 'subscribe' && reply[1]?.toString?.() === this.channel && !this.ready) {
            this.ready = true
            clearTimeout(handshakeTimer)
            socket.setTimeout(0)
            heartbeatTimer = setInterval(verifyLiveness, this.heartbeatIntervalMs)
            heartbeatTimer.unref?.()
            this.retryMs = 500
            this.onStatus('up')
            return
          }
          if (this.ready && kind === 'message' && reply[1]?.toString?.() === this.channel && Buffer.isBuffer(reply[2])) {
            try {
              this.onMessage(JSON.parse(reply[2].toString('utf8')))
            } catch {
              // 非法 payload 忽略：信号是加速通道，事实以游标 API 为准。
            }
          }
        }
      },
    })
    socket.on(this.useTls ? 'secureConnect' : 'connect', () => {
      if (this.password) socket.write(encodeCommand(['AUTH', this.password]))
      else if (this.database > 0) select()
      else subscribe()
    })
    socket.on('data', (chunk) => parser.push(chunk))
    socket.on('timeout', () => socket.destroy())
    socket.on('error', () => {})
    socket.on('close', () => {
      clearTimeout(handshakeTimer)
      clearTimeout(pongTimer)
      clearInterval(heartbeatTimer)
      if (this.stopped) return
      this.ready = false
      this.onStatus('down')
      const delay = this.retryMs
      this.retryMs = Math.min(30_000, this.retryMs * 2)
      this.retryTimer = setTimeout(() => { this.retryTimer = null; this.connect() }, delay)
      this.retryTimer.unref?.()
    })
  }

  stop() {
    this.stopped = true
    this.ready = false
    if (this.retryTimer) clearTimeout(this.retryTimer)
    this.retryTimer = null
    this.socket?.destroy()
  }
}
