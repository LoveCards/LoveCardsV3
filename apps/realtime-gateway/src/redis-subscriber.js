// 极简 Redis RESP2 订阅器（仅 SUBSCRIBE + message 推送解析），零第三方依赖。
// 断线自动重连并汇报状态；Gateway 依据状态在 redis/source-poll 两种 transport 间切换。

import net from 'node:net'

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
  constructor({ host = '127.0.0.1', port = 6379, channel, onMessage, onStatus, connectTimeoutMs = 1000 }) {
    this.host = host
    this.port = port
    this.channel = channel
    this.onMessage = onMessage
    this.onStatus = onStatus ?? (() => {})
    this.connectTimeoutMs = connectTimeoutMs
    this.socket = null
    this.stopped = false
    this.retryMs = 500
  }

  start() {
    this.stopped = false
    this.connect()
  }

  connect() {
    if (this.stopped) return
    const socket = net.connect({ host: this.host, port: this.port })
    this.socket = socket
    socket.setTimeout(this.connectTimeoutMs)
    const parser = createRespParser({
      onReply: (reply) => {
        if (Array.isArray(reply) && reply.length === 3 && Buffer.isBuffer(reply[2])) {
          const kind = reply[0]?.toString?.() ?? ''
          if (kind === 'message') {
            try {
              this.onMessage(JSON.parse(reply[2].toString('utf8')))
            } catch {
              // 非法 payload 忽略：信号是加速通道，事实以游标 API 为准。
            }
          }
        }
      },
    })
    socket.on('connect', () => {
      socket.setTimeout(0)
      socket.write(encodeCommand(['SUBSCRIBE', this.channel]))
      this.retryMs = 500
      this.onStatus('up')
    })
    socket.on('data', (chunk) => parser.push(chunk))
    socket.on('error', () => {})
    socket.on('close', () => {
      if (this.stopped) return
      this.onStatus('down')
      const delay = this.retryMs
      this.retryMs = Math.min(30_000, this.retryMs * 2)
      setTimeout(() => this.connect(), delay).unref?.()
    })
  }

  stop() {
    this.stopped = true
    this.socket?.destroy()
  }
}
