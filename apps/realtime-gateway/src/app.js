// Realtime Gateway 核心：ticket 鉴权 → SSE 供流；Redis 不可用时停止供流。
// 契约见 docs/engineering/COMMUNICATION-RUNTIME-CONTRACT-2026-11-06.md §4。

import http from 'node:http'
import net from 'node:net'
import { RedisSubscriber } from './redis-subscriber.js'
import { heartbeatFrame, pingComment, retryDirective, signalFrame } from './sse.js'

export const PROTOCOL = 'lc-realtime/1'
export const VERSION = '0.1.0'

const HEARTBEAT_MS = 15_000
const MAX_LIFETIME_MS = 60_000
const RECONNECT_RETRY_MS = 2_000
const REDIS_CONFIG_REFRESH_MS = 15_000
const DEFAULT_MAX_CONNECTIONS = 100
const BACKEND_TIMEOUT_MS = 5_000

function validTlsIdentity(settings) {
  const name = settings.tls_server_name || settings.host
  return name.length <= 253 && net.isIP(name) === 0
    && /^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*$/.test(name)
}

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
    redisConfigPath = null,
    redisConfigRefreshMs = REDIS_CONFIG_REFRESH_MS,
    maxConnections = DEFAULT_MAX_CONNECTIONS,
    backendTimeoutMs = BACKEND_TIMEOUT_MS,
    fetchImpl = globalThis.fetch,
    logger = () => {},
  } = config
  if (!backendUrl || !internalToken) {
    throw new Error('gateway requires backendUrl and internalToken')
  }
  if (!Number.isInteger(maxConnections) || maxConnections < 1) {
    throw new RangeError('gateway maxConnections must be a positive integer')
  }

  const state = {
    transport: 'disconnected',
    redisConfigVersion: null,
    redisConfigSource: null,
    connections: new Set(),
  }
  let subscriber = null
  let configTimer = null
  let configRefreshPending = false
  let stopped = false
  let pendingConnections = 0

  const backendFetch = async (path, init = {}) => {
    const response = await fetchImpl(new URL(path, backendUrl), {
      ...init,
      signal: init.signal ?? AbortSignal.timeout(backendTimeoutMs),
      headers: { Authorization: `Bearer ${internalToken}`, Accept: 'application/json', ...(init.headers ?? {}) },
    })
    if (response.status >= 500) throw new Error('backend unavailable')
    if (response.status !== 200) return null
    try {
      const payload = await response.json()
      // 后端 ApiResponse 信封：内部通道一律在 data 字段下返回业务载荷。
      if (payload && typeof payload === 'object' && 'data' in payload) return payload.data ?? null
      return payload
    } catch {
      return null
    }
  }

  const closeConnection = (conn, reason = 'closed', force = false) => {
    if (!state.connections.delete(conn)) return
    clearInterval(conn.heartbeat)
    clearTimeout(conn.lifetime)
    try {
      if (force) conn.res.destroy()
      else conn.res.end()
    } catch {
      // 客户端已断开。
    }
    logger(`connection ${reason}`)
  }

  const disableTransport = (reason) => {
    const old = subscriber
    subscriber = null
    old?.stop()
    state.transport = 'disconnected'
    state.redisConfigVersion = null
    state.redisConfigSource = null
    for (const conn of [...state.connections]) closeConnection(conn, reason)
  }

  const configureRedis = (settings) => {
    if (!settings?.enabled || typeof settings.host !== 'string' || !settings.host.trim()
      || !Number.isInteger(settings.port) || settings.port < 1 || settings.port > 65535
      || !Number.isInteger(settings.database) || settings.database < 0 || settings.database > 15
      || typeof settings.tls !== 'boolean' || typeof settings.tls_server_name !== 'string'
      || (settings.tls && !validTlsIdentity(settings))
      || typeof settings.channel !== 'string' || !/^lc:[a-f0-9]{32}:realtime$/.test(settings.channel)
      || typeof settings.password !== 'string' || typeof settings.revision !== 'string' || !settings.revision
      || !['env', 'database'].includes(settings.source)) {
      disableTransport('redis configuration unavailable')
      return
    }
    if (subscriber && state.redisConfigVersion === settings.revision) return
    disableTransport('redis configuration changed')
    state.redisConfigVersion = settings.revision
    state.redisConfigSource = settings.source
    const instance = new RedisSubscriber({
        host: settings.host,
        port: settings.port,
        database: settings.database,
        password: settings.password,
        tls: settings.tls,
        tlsServerName: settings.tls_server_name,
        channel: settings.channel,
        onMessage: (message) => {
          // 信号载荷 {topic, uid:'user:42', cursor}；只按 recipient scope 定向推送。
          if (message.uid === 'all' && (message.topic !== 'feed' || message.cursor !== 'public')) return
          const recipients = Array.isArray(message.uids) && message.uids.length <= 200
            ? new Set(message.uids.filter((uid) => typeof uid === 'string')) : null
          for (const conn of state.connections) {
            if (message.uid !== 'all' && conn.recipientKey !== message.uid && !recipients?.has(conn.recipientKey)) continue
            conn.send(signalFrame({ topic: message.topic ?? 'notifications', cursor: message.cursor ?? '' }))
          }
        },
        onStatus: (status) => {
          if (subscriber !== instance) return
          if (status === 'down') {
            state.transport = 'disconnected'
            for (const conn of [...state.connections]) closeConnection(conn, 'redis disconnected')
            logger('redis disconnected, realtime stream paused')
          } else if (status === 'up') {
            state.transport = 'redis'
          }
        },
      })
    subscriber = instance
    instance.start()
  }

  const refreshRedisConfig = async () => {
    if (!redisConfigPath || stopped || configRefreshPending) return
    configRefreshPending = true
    try {
      const settings = await backendFetch(redisConfigPath, { signal: AbortSignal.timeout(2_000) })
      if (!stopped) configureRedis(settings)
    } catch {
      if (!stopped) disableTransport('redis configuration unavailable')
    } finally {
      configRefreshPending = false
    }
  }

  const openStream = async (req, res, ticket) => {
    if (state.transport !== 'redis') {
      res.writeHead(503, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ error: 'realtime transport unavailable' }))
      return
    }
    const rejectFull = () => {
      res.writeHead(503, { 'Content-Type': 'application/json', 'Retry-After': '5' })
      res.end(JSON.stringify({ error: 'realtime connection capacity reached' }))
    }
    if (state.connections.size + pendingConnections >= maxConnections) {
      rejectFull()
      return
    }
    // 验证 ticket 会等待后端；预留名额阻止同时通过验证的请求超出上限。
    pendingConnections += 1
    let verified
    const verifyStartedAt = performance.now()
    let backendUnavailable = false
    try {
      verified = await backendFetch('/api/internal/realtime/ticket/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket }),
      })
    } catch {
      backendUnavailable = true
    } finally {
      pendingConnections -= 1
    }
    if (res.destroyed || req.destroyed) return
    if (backendUnavailable) {
      res.writeHead(503, { 'Content-Type': 'application/json', 'Retry-After': '5' })
      res.end(JSON.stringify({ error: 'backend unavailable' }))
      return
    }
    // 后端必须显式提供会话期限；减去整次验证耗时，避免网络延迟延长授权。
    const lifetimeMs = Number.isInteger(verified?.connection_ttl_seconds)
      ? Math.min(MAX_LIFETIME_MS, verified.connection_ttl_seconds * 1000) - (performance.now() - verifyStartedAt) : 0
    if (!verified || !Number.isInteger(verified.uid) || verified.uid < 1 || lifetimeMs <= 0) {
      res.writeHead(401, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ success: false, error: { code: 1005, message: 'invalid stream ticket' } }))
      return
    }
    if (state.transport !== 'redis') {
      res.writeHead(503, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ error: 'realtime transport unavailable' }))
      return
    }
    if (state.connections.size >= maxConnections) {
      rejectFull()
      return
    }

    res.writeHead(200, {
      'Content-Type': 'text/event-stream; charset=utf-8',
      'Cache-Control': 'no-cache',
      Connection: 'keep-alive',
      'X-Accel-Buffering': 'no',
    })
    if (!res.write(retryDirective(RECONNECT_RETRY_MS))) {
      res.destroy()
      logger('connection write backpressure')
      return
    }

    const conn = {
      recipientKey: `user:${verified.uid}`,
      res,
      send(frame) {
        try {
          if (!res.write(frame)) closeConnection(conn, 'write backpressure', true)
        } catch {
          closeConnection(conn, 'write failed')
        }
      },
    }
    state.connections.add(conn)

    conn.heartbeat = setInterval(() => { conn.send(pingComment()); conn.send(heartbeatFrame()) }, HEARTBEAT_MS)
    conn.lifetime = setTimeout(() => closeConnection(conn, 'session lifetime'), lifetimeMs)

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
        session_ttl_max_seconds: MAX_LIFETIME_MS / 1000,
        ready: state.transport === 'redis',
        cursor: true,
        auth: ['ticket'],
        version: VERSION,
        transport: state.transport,
        redis_config_version: state.redisConfigVersion,
        redis_config_source: state.redisConfigSource,
        connected_connections: state.connections.size,
        max_connections: maxConnections,
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
      await openStream(req, res, ticket)
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
    start(port, host = '127.0.0.1') {
      stopped = false
      if (redisConfigPath) {
        void refreshRedisConfig()
        configTimer = setInterval(() => void refreshRedisConfig(), redisConfigRefreshMs)
      } else if (redis) {
        configureRedis({ enabled: true, host: redis.host, port: redis.port ?? 6379,
          database: redis.database ?? 0, password: redis.password ?? '',
          tls: redis.tls ?? false, tls_server_name: redis.tls_server_name ?? '',
          channel: redis.channel, source: redis.source ?? 'env', revision: redis.revision ?? 'env' })
      }
      return new Promise((resolve) => {
        server.listen(port, host, () => resolve(server.address().port))
      })
    },
    stop() {
      stopped = true
      if (configTimer) clearInterval(configTimer)
      configTimer = null
      disableTransport('shutdown')
      server.close()
    },
  }
}
