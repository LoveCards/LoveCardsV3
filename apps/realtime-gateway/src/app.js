// Realtime Gateway 核心：ticket 鉴权 → SSE 供流；Redis 信号缺失时自动 source-poll 回源。
// 契约见 docs/engineering/COMMUNICATION-RUNTIME-CONTRACT-2026-11-06.md §4。

import http from 'node:http'
import { RedisSubscriber } from './redis-subscriber.js'
import { pingComment, resyncFrame, retryDirective, signalFrame } from './sse.js'

export const PROTOCOL = 'lc-realtime/1'
export const VERSION = '0.1.0'

const HEARTBEAT_MS = 15_000
const MAX_LIFETIME_MS = 900_000
const DEFAULT_POLL_INTERVAL_MS = 5_000
const RECONNECT_RETRY_MS = 2_000

function bearerOk(req, token) {
  if (!token) return false
  const header = String(req.headers.authorization ?? '')
  return header === `Bearer ${token}`
}

function readJsonBody(req, limitBytes = 16 * 1024) {
  return new Promise((resolve, reject) => {
    const chunks = []
    let size = 0
    req.on('data', (chunk) => {
      size += chunk.length
      if (size > limitBytes) {
        reject(new Error('body too large'))
        req.destroy()
        return
      }
      chunks.push(chunk)
    })
    req.on('end', () => {
      try {
        resolve(chunks.length === 0 ? {} : JSON.parse(Buffer.concat(chunks).toString('utf8')))
      } catch {
        reject(new Error('invalid json'))
      }
    })
    req.on('error', reject)
  })
}

export function createApp(config) {
  const {
    backendUrl,
    internalToken,
    redis = null,
    fetchImpl = globalThis.fetch,
    logger = () => {},
    pollIntervalMs = DEFAULT_POLL_INTERVAL_MS,
  } = config
  if (!backendUrl || !internalToken) {
    throw new Error('gateway requires backendUrl and internalToken')
  }

  // transport 状态机：redis 订阅断开时退回 source-poll，恢复后回到 redis。
  const state = {
    transport: redis ? 'redis' : 'source-poll',
    connections: new Set(),
  }

  const backendFetch = async (path, init = {}) => {
    const response = await fetchImpl(new URL(path, backendUrl), {
      ...init,
      headers: { Authorization: `Bearer ${internalToken}`, Accept: 'application/json', ...(init.headers ?? {}) },
    })
    if (response.status !== 200) return null
    try {
      return await response.json()
    } catch {
      return null
    }
  }

  const subscriber = redis
    ? new RedisSubscriber({
        host: redis.host,
        port: redis.port,
        channel: 'lovecards:realtime',
        onMessage: (message) => {
          // 信号载荷 {topic, uid:'user:42', cursor}；只按 recipient scope 定向推送。
          for (const conn of state.connections) {
            if (conn.recipientKey !== message.uid) continue
            conn.send(signalFrame({ id: message.cursor || conn.cursor, topic: message.topic ?? 'notifications', cursor: message.cursor || conn.cursor }))
          }
        },
        onStatus: (status) => {
          if (status === 'down' && state.transport !== 'source-poll') {
            state.transport = 'source-poll'
            logger('redis down, falling back to source-poll')
          }
          if (status === 'up' && redis) {
            state.transport = 'redis'
          }
        },
      })
    : null

  const closeConnection = (conn, reason = 'closed') => {
    if (!state.connections.delete(conn)) return
    clearInterval(conn.heartbeat)
    if (conn.poll) clearInterval(conn.poll)
    clearTimeout(conn.lifetime)
    try {
      conn.res.end()
    } catch {
      // 客户端已断开。
    }
    logger(`connection ${reason}`)
  }

  const openStream = async (req, res, ticket, lastEventId) => {
    const verified = await backendFetch('/api/internal/realtime/ticket/verify', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ticket }),
    })
    if (!verified || typeof verified.uid !== 'number' || verified.uid < 1) {
      res.writeHead(401, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ success: false, error: { code: 1005, message: 'invalid stream ticket' } }))
      return
    }

    res.writeHead(200, {
      'Content-Type': 'text/event-stream; charset=utf-8',
      'Cache-Control': 'no-cache',
      Connection: 'keep-alive',
      'X-Accel-Buffering': 'no',
    })
    res.write(retryDirective(RECONNECT_RETRY_MS))

    const conn = {
      recipientKey: `user:${verified.uid}`,
      cursor: lastEventId || verified.cursor_seed || null,
      res,
      send(frame) {
        try {
          res.write(frame)
        } catch {
          closeConnection(conn, 'write failed')
        }
      },
    }
    state.connections.add(conn)

    // Last-Event-ID 携带的游标若与后端当前游标无法对齐（跨进程无状态），以 seed 为准；
    // 客户端凭游标 API 回源补齐，信号只负责加速。
    if (lastEventId && verified.cursor_seed && lastEventId !== verified.cursor_seed) {
      conn.send(resyncFrame())
    }

    conn.heartbeat = setInterval(() => conn.send(pingComment()), HEARTBEAT_MS)
    conn.lifetime = setTimeout(() => closeConnection(conn, 'max lifetime'), MAX_LIFETIME_MS)

    if (state.transport === 'source-poll') {
      conn.poll = setInterval(async () => {
        const result = await backendFetch(
          `/api/internal/realtime/notifications-since?uid=${verified.uid}&limit=50${conn.cursor ? `&cursor=${encodeURIComponent(conn.cursor)}` : ''}`,
        )
        if (!result || !result.newer || !result.latest_cursor || result.latest_cursor === conn.cursor) return
        conn.cursor = result.latest_cursor
        conn.send(signalFrame({ id: conn.cursor, topic: 'notifications', cursor: conn.cursor }))
      }, pollIntervalMs)
    }

    req.on('close', () => closeConnection(conn, 'client closed'))
  }

  const server = http.createServer(async (req, res) => {
    const url = new URL(req.url ?? '/', 'http://localhost')

    if (req.method === 'GET' && url.pathname === '/internal/health') {
      if (!bearerOk(req, internalToken)) {
        res.writeHead(401, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ error: 'unauthorized' }))
        return
      }
      res.writeHead(200, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({
        protocol: PROTOCOL,
        ready: true,
        cursor: true,
        auth: ['ticket'],
        version: VERSION,
        transport: state.transport,
      }))
      return
    }

    if (req.method === 'GET' && url.pathname === '/stream') {
      const ticket = url.searchParams.get('ticket') ?? ''
      if (ticket.length < 16 || ticket.length > 128) {
        res.writeHead(401, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ error: 'invalid ticket' }))
        return
      }
      await openStream(req, res, ticket, url.searchParams.get('lastEventId') ?? req.headers['last-event-id'] ?? null)
      return
    }

    if (req.method === 'GET' && url.pathname === '/') {
      res.writeHead(200, { 'Content-Type': 'text/plain' })
      res.end('lovecards realtime gateway\n')
      return
    }

    res.writeHead(404, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify({ error: 'not found' }))
  })

  return {
    server,
    state,
    start(port) {
      subscriber?.start()
      return new Promise((resolve) => {
        server.listen(port, () => resolve(server.address().port))
      })
    },
    stop() {
      subscriber?.stop()
      for (const conn of [...state.connections]) closeConnection(conn, 'shutdown')
      server.close()
    },
  }
}
