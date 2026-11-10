// Realtime Gateway 行为测试：health 协议、一次性 ticket、Redis 信号与故障停流、SSE 字节规范。
import assert from 'node:assert/strict'
import http from 'node:http'
import net from 'node:net'
import tls from 'node:tls'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import test from 'node:test'

import { createApp, PROTOCOL } from '../src/app.js'
import { createRespParser, encodeCommand, RedisSubscriber } from '../src/redis-subscriber.js'
import { signalFrame, sseFrame } from '../src/sse.js'

function listen(server, port = 0) {
  return new Promise((resolve) => server.listen(port, '127.0.0.1', () => resolve(server.address().port)))
}

function httpGet(port, path, headers = {}) {
  return new Promise((resolve, reject) => {
    const req = http.get({ host: '127.0.0.1', port, path, headers }, (res) => resolve(res))
    req.on('error', reject)
  })
}

function readJson(res) {
  return new Promise((resolve) => {
    const chunks = []
    res.on('data', (chunk) => chunks.push(chunk))
    res.on('end', () => resolve(JSON.parse(Buffer.concat(chunks).toString('utf8') || 'null')))
  })
}

function collectFrames(res, count, timeoutMs = 4000) {
  return new Promise((resolve, reject) => {
    const frames = []
    let buffer = ''
    const timer = setTimeout(() => reject(new Error(`timed out waiting for ${count} SSE frames, got: ${JSON.stringify(frames)}`)), timeoutMs)
    res.on('data', (chunk) => {
      buffer += chunk.toString('utf8')
      let index = buffer.indexOf('\n\n')
      while (index !== -1) {
        frames.push(buffer.slice(0, index))
        buffer = buffer.slice(index + 2)
        index = buffer.indexOf('\n\n')
      }
      if (frames.length >= count) {
        clearTimeout(timer)
        resolve(frames)
      }
    })
    res.on('end', () => {
      clearTimeout(timer)
      reject(new Error(`stream ended early, got: ${JSON.stringify(frames)}`))
    })
  })
}

function createFakeBackend() {
  const tickets = new Map([
    ['valid-ticket-26chars-hex', { uid: 7, cursor_seed: 'seed-1' }],
    ['second-ticket-26chars-hex', { uid: 8, cursor_seed: 'seed-2' }],
  ])
  const redisConfig = { enabled: false, host: '', port: 0, database: 0, password: '', tls: false, tls_server_name: '', source: 'database', revision: 'disabled' }
  let configAvailable = true
  const server = http.createServer(async (req, res) => {
    if (req.headers.authorization !== 'Bearer test-internal-token-1234') {
      res.writeHead(401, { 'Content-Type': 'application/json' })
      res.end('{}')
      return
    }
    if (req.method === 'POST' && req.url.startsWith('/api/internal/realtime/ticket/verify')) {
      const chunks = []
      for await (const chunk of req) chunks.push(chunk)
      const body = JSON.parse(Buffer.concat(chunks).toString('utf8') || '{}')
      const ticket = tickets.get(body.ticket)
      if (!ticket) {
        res.writeHead(401, { 'Content-Type': 'application/json' })
        res.end('{}')
        return
      }
      tickets.delete(body.ticket) // one-time semantics
      res.writeHead(200, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ success: true, data: { uid: ticket.uid, cursor_seed: ticket.cursor_seed } }))
      return
    }
    if (req.method === 'GET' && req.url === '/api/internal/realtime/redis-config') {
      res.writeHead(configAvailable ? 200 : 503, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ success: configAvailable, data: configAvailable ? redisConfig : null }))
      return
    }
    res.writeHead(404)
    res.end()
  })
  return { server, tickets, redisConfig, setConfigAvailable: (value) => { configAvailable = value } }
}

test('health endpoint enforces internal token and frozen protocol body', async () => {
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({ backendUrl: `http://127.0.0.1:${backendPort}`, internalToken: 'test-internal-token-1234' })
  const port = await app.start(0)
  try {
    const denied = await readJson(await httpGet(port, '/internal/health'))
    assert.equal(denied.error, 'unauthorized')
    const health = await readJson(await httpGet(port, '/internal/health', { Authorization: 'Bearer test-internal-token-1234' }))
    assert.equal(health.protocol, PROTOCOL)
    assert.equal(health.ready, false)
    assert.equal(health.cursor, true)
    assert.deepEqual(health.auth, ['ticket'])
    assert.equal(health.transport, 'disconnected')
    assert.equal(health.connected_connections, 0)
    assert.equal(health.max_connections, 100)
    assert.equal(typeof health.version, 'string')
  } finally {
    app.stop()
    backend.server.close()
  }
})

test('gateway rejects excess connections without consuming tickets and accepts them after release', async () => {
  const sockets = []
  const fakeRedis = net.createServer((socket) => {
    sockets.push(socket)
    socket.on('data', () => socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n')))
  })
  const redisPort = await listen(fakeRedis)
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({ backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234', redis: { host: '127.0.0.1', port: redisPort }, maxConnections: 1 })
  const port = await app.start(0)
  const headers = { Authorization: 'Bearer test-internal-token-1234' }
  try {
    let health
    for (let i = 0; i < 50; i += 1) {
      health = await readJson(await httpGet(port, '/internal/health', headers))
      if (health.ready) break
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    assert.equal(health.ready, true)
    const first = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(first.statusCode, 200)
    health = await readJson(await httpGet(port, '/internal/health', headers))
    assert.equal(health.connected_connections, 1)
    assert.equal(health.max_connections, 1)

    const denied = await httpGet(port, '/stream?ticket=second-ticket-26chars-hex')
    assert.equal(denied.statusCode, 503)
    assert.equal(denied.headers['retry-after'], '5')
    assert.equal((await readJson(denied)).error, 'realtime connection capacity reached')
    assert.equal(backend.tickets.has('second-ticket-26chars-hex'), true)

    first.destroy()
    for (let i = 0; i < 50 && app.state.connections.size !== 0; i += 1) {
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    assert.equal(app.state.connections.size, 0)
    const second = await httpGet(port, '/stream?ticket=second-ticket-26chars-hex')
    assert.equal(second.statusCode, 200)
    assert.equal(backend.tickets.has('second-ticket-26chars-hex'), false)
    second.destroy()
  } finally {
    app.stop()
    for (const socket of sockets) socket.destroy()
    fakeRedis.close()
    backend.server.close()
  }
})

test('pending ticket verification reserves capacity against concurrent connection attempts', async () => {
  const sockets = []
  const fakeRedis = net.createServer((socket) => {
    sockets.push(socket)
    socket.on('data', () => socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n')))
  })
  const redisPort = await listen(fakeRedis)
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  let releaseVerification
  let verificationStarted
  const verificationGate = new Promise((resolve) => { releaseVerification = resolve })
  const started = new Promise((resolve) => { verificationStarted = resolve })
  const app = createApp({ backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234', redis: { host: '127.0.0.1', port: redisPort }, maxConnections: 1,
    fetchImpl: async (url, init) => {
      if (url.pathname.endsWith('/ticket/verify')) {
        verificationStarted()
        await verificationGate
      }
      return fetch(url, init)
    } })
  const port = await app.start(0)
  try {
    for (let i = 0; i < 50 && app.state.transport !== 'redis'; i += 1) await new Promise((resolve) => setTimeout(resolve, 10))
    assert.equal(app.state.transport, 'redis')
    const firstPromise = httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    await started
    const denied = await httpGet(port, '/stream?ticket=second-ticket-26chars-hex')
    assert.equal(denied.statusCode, 503)
    await readJson(denied)
    assert.equal(backend.tickets.has('second-ticket-26chars-hex'), true)
    releaseVerification()
    const first = await firstPromise
    assert.equal(first.statusCode, 200)
    assert.equal(app.state.connections.size, 1)
    first.destroy()
  } finally {
    releaseVerification()
    app.stop()
    for (const socket of sockets) socket.destroy()
    fakeRedis.close()
    backend.server.close()
  }
})

test('stream rejects connections while redis is unavailable without consuming tickets', async () => {
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({ backendUrl: `http://127.0.0.1:${backendPort}`, internalToken: 'test-internal-token-1234' })
  const port = await app.start(0)
  try {
    const denied = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(denied.statusCode, 503)
    await readJson(denied)
    assert.equal(backend.tickets.has('valid-ticket-26chars-hex'), true)
  } finally {
    app.stop()
    backend.server.close()
  }
})

test('gateway becomes ready only after redis confirms the subscription', async () => {
  let subscriberSocket = null
  const fakeRedis = net.createServer((socket) => { subscriberSocket = socket })
  const redisPort = await listen(fakeRedis)
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({
    backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234',
    redis: { host: '127.0.0.1', port: redisPort },
  })
  const port = await app.start(0)
  try {
    while (!subscriberSocket) await new Promise((resolve) => setTimeout(resolve, 10))
    const headers = { Authorization: 'Bearer test-internal-token-1234' }
    const before = await readJson(await httpGet(port, '/internal/health', headers))
    assert.equal(before.ready, false)
    const denied = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(denied.statusCode, 503)
    await readJson(denied)
    subscriberSocket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n'))
    await new Promise((resolve) => setTimeout(resolve, 10))
    const after = await readJson(await httpGet(port, '/internal/health', headers))
    assert.equal(after.ready, true)
    assert.equal(backend.tickets.has('valid-ticket-26chars-hex'), true)
  } finally {
    app.stop()
    fakeRedis.close()
    backend.server.close()
  }
})

test('gateway reloads backend redis config and pauses streams when config becomes unreadable', async () => {
  const sockets = []
  const fakeRedis = net.createServer((socket) => {
    sockets.push(socket)
    socket.on('data', () => socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n')))
  })
  const redisPort = await listen(fakeRedis)
  const backend = createFakeBackend()
  Object.assign(backend.redisConfig, {
    enabled: true, host: '127.0.0.1', port: redisPort, database: 0,
    password: '', source: 'database', revision: 'config-v1',
  })
  const backendPort = await listen(backend.server)
  const app = createApp({
    backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234',
    redisConfigPath: '/api/internal/realtime/redis-config',
    redisConfigRefreshMs: 40,
  })
  const port = await app.start(0)
  const headers = { Authorization: 'Bearer test-internal-token-1234' }
  try {
    let health
    for (let i = 0; i < 50; i += 1) {
      health = await readJson(await httpGet(port, '/internal/health', headers))
      if (health.ready) break
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    assert.equal(health.ready, true)
    assert.equal(health.redis_config_version, 'config-v1')
    assert.equal(health.redis_config_source, 'database')

    const stream = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(stream.statusCode, 200)
    let ended = false
    stream.on('end', () => { ended = true })
    stream.resume()

    backend.setConfigAvailable(false)
    for (let i = 0; i < 50 && !ended; i += 1) await new Promise((resolve) => setTimeout(resolve, 10))
    assert.equal(ended, true)
    health = await readJson(await httpGet(port, '/internal/health', headers))
    assert.equal(health.ready, false)
    assert.equal(health.redis_config_version, null)

    backend.setConfigAvailable(true)
    backend.redisConfig.revision = 'config-v2'
    for (let i = 0; i < 50; i += 1) {
      health = await readJson(await httpGet(port, '/internal/health', headers))
      if (health.ready && health.redis_config_version === 'config-v2') break
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    assert.equal(health.ready, true)
    assert.equal(health.redis_config_version, 'config-v2')
    assert.ok(sockets.length >= 2)
  } finally {
    app.stop()
    for (const socket of sockets) socket.destroy()
    fakeRedis.close()
    backend.server.close()
  }
})

test('gateway closes old streams and waits for the new subscription after a config revision change', async () => {
  const firstSockets = []
  const secondSockets = []
  const firstRedis = net.createServer((socket) => {
    firstSockets.push(socket)
    socket.on('data', () => socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n')))
  })
  const secondRedis = net.createServer((socket) => { secondSockets.push(socket) })
  const firstPort = await listen(firstRedis)
  const secondPort = await listen(secondRedis)
  const backend = createFakeBackend()
  Object.assign(backend.redisConfig, { enabled: true, host: '127.0.0.1', port: firstPort,
    database: 0, password: '', source: 'database', revision: 'config-v1' })
  const backendPort = await listen(backend.server)
  const app = createApp({ backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234', redisConfigPath: '/api/internal/realtime/redis-config',
    redisConfigRefreshMs: 40 })
  const port = await app.start(0)
  const headers = { Authorization: 'Bearer test-internal-token-1234' }
  try {
    let health
    for (let i = 0; i < 50; i += 1) {
      health = await readJson(await httpGet(port, '/internal/health', headers))
      if (health.ready) break
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    assert.equal(health.ready, true)
    const stream = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(stream.statusCode, 200)
    let ended = false
    stream.on('end', () => { ended = true })
    stream.resume()

    backend.redisConfig.port = secondPort
    backend.redisConfig.revision = 'config-v2'
    for (let i = 0; i < 50 && !ended; i += 1) await new Promise((resolve) => setTimeout(resolve, 10))
    assert.equal(ended, true)
    health = await readJson(await httpGet(port, '/internal/health', headers))
    assert.equal(health.redis_config_version, 'config-v2')
    assert.equal(health.ready, false)
    assert.equal(secondSockets.length, 1)
    secondSockets[0].write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n'))
    for (let i = 0; i < 20; i += 1) {
      health = await readJson(await httpGet(port, '/internal/health', headers))
      if (health.ready) break
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    assert.equal(health.ready, true)
  } finally {
    app.stop()
    for (const socket of [...firstSockets, ...secondSockets]) socket.destroy()
    firstRedis.close()
    secondRedis.close()
    backend.server.close()
  }
})

test('gateway never reports ready when TLS is configured against a plaintext Redis endpoint', async () => {
  const sockets = new Set()
  const fakeRedis = net.createServer((socket) => {
    sockets.add(socket)
    socket.on('close', () => sockets.delete(socket))
    socket.destroy()
  })
  const redisPort = await listen(fakeRedis)
  const backend = createFakeBackend()
  Object.assign(backend.redisConfig, { enabled: true, host: '127.0.0.1', port: redisPort,
    database: 0, password: '', tls: true, tls_server_name: 'redis.test', source: 'database', revision: 'tls-v1' })
  const backendPort = await listen(backend.server)
  const app = createApp({ backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234', redisConfigPath: '/api/internal/realtime/redis-config',
    redisConfigRefreshMs: 40 })
  const port = await app.start(0)
  try {
    const headers = { Authorization: 'Bearer test-internal-token-1234' }
    let health
    for (let i = 0; i < 50; i += 1) {
      health = await readJson(await httpGet(port, '/internal/health', headers))
      if (health.redis_config_version === 'tls-v1') break
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    assert.equal(health.redis_config_version, 'tls-v1')
    assert.equal(health.ready, false)
    assert.equal(health.transport, 'disconnected')
  } finally {
    app.stop()
    for (const socket of sockets) socket.destroy()
    fakeRedis.close()
    backend.server.close()
  }
})

test('redis transport pushes recipient-scoped signals as SSE frames', async () => {
  let subscriberSocket = null
  const fakeRedis = net.createServer((socket) => {
    subscriberSocket = socket
    socket.on('data', () => {
      socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n'))
    })
  })
  const redisPort = await listen(fakeRedis)

  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({
    backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234',
    redis: { host: '127.0.0.1', port: redisPort },
  })
  const port = await app.start(0)
  try {
    await new Promise((resolve) => {
      const started = setInterval(() => {
        if (subscriberSocket) {
          clearInterval(started)
          resolve()
        }
      }, 20)
    })

    const stream = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(stream.statusCode, 200)
    assert.match(stream.headers['content-type'], /text\/event-stream/)
    assert.equal(stream.headers['x-accel-buffering'], 'no')
    const framePromise = collectFrames(stream, 3)
    const otherStream = await httpGet(port, '/stream?ticket=second-ticket-26chars-hex')
    assert.equal(otherStream.statusCode, 200)
    const otherFrames = collectFrames(otherStream, 3)

    const second = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(second.statusCode, 401)
    await readJson(second)

    // 定向 uid 不匹配的信号必须被忽略，匹配的必须送达。
    subscriberSocket.write(encodeMessage({ topic: 'notifications', uid: 'user:9', cursor: 'x' }))
    subscriberSocket.write(encodeMessage({ topic: 'notifications', uid: 'user:7', cursor: 'cursor-r1' }))
    subscriberSocket.write(encodeMessage({ topic: 'feed', uid: 'all', cursor: 'public' }))
    subscriberSocket.write(encodeMessage({ topic: 'feed', uids: ['user:8', 'user:9'], cursor: 'following' }))

    const frames = await framePromise
    assert.equal(frames[0], 'retry: 2000')
    assert.match(frames[2], /"topic":"feed","cursor":"public"/)
    const scopedFrames = await otherFrames
    assert.match(scopedFrames[1], /"topic":"feed","cursor":"public"/)
    assert.match(scopedFrames[2], /"topic":"feed","cursor":"following"/)
    assert.match(frames[1], /^event: signal\ndata: \{"topic":"notifications","cursor":"cursor-r1"}$/)
    stream.destroy()
    otherStream.destroy()
  } finally {
    app.stop()
    fakeRedis.close()
    backend.server.close()
  }

  function encodeMessage(payload) {
    const body = JSON.stringify(payload)
    return Buffer.from(`*3\r\n$7\r\nmessage\r\n$18\r\nlovecards:realtime\r\n$${Buffer.byteLength(body)}\r\n${body}\r\n`, 'utf8')
  }
})

test('gateway closes a stream when an SSE write hits backpressure', async () => {
  let subscriberSocket = null
  const fakeRedis = net.createServer((socket) => {
    subscriberSocket = socket
    socket.on('data', () => socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n')))
  })
  const redisPort = await listen(fakeRedis)
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({
    backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234',
    redis: { host: '127.0.0.1', port: redisPort },
  })
  const port = await app.start(0)
  try {
    const headers = { Authorization: 'Bearer test-internal-token-1234' }
    for (let i = 0; i < 50; i += 1) {
      const health = await readJson(await httpGet(port, '/internal/health', headers))
      if (health.ready) break
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    assert.ok(subscriberSocket)
    const stream = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(stream.statusCode, 200)
    stream.pause()
    stream.on('error', () => {})
    const closed = new Promise((resolve) => stream.once('close', resolve))
    const payload = JSON.stringify({ topic: 'notifications', uid: 'user:7', cursor: 'x'.repeat(64 * 1024) })
    subscriberSocket.write(Buffer.from(`*3\r\n$7\r\nmessage\r\n$18\r\nlovecards:realtime\r\n$${Buffer.byteLength(payload)}\r\n${payload}\r\n`))
    let timeout
    try {
      await Promise.race([closed, new Promise((_, reject) => {
        timeout = setTimeout(() => reject(new Error('stream stayed open after backpressure')), 2000)
      })])
    } finally {
      clearTimeout(timeout)
    }
    const health = await readJson(await httpGet(port, '/internal/health', headers))
    assert.equal(health.connected_connections, 0)
  } finally {
    app.stop()
    subscriberSocket?.destroy()
    fakeRedis.close()
    backend.server.close()
  }
})

test('existing redis connections close when redis goes down without source polling', async () => {
  let subscriberSocket = null
  const fakeRedis = net.createServer((socket) => {
    subscriberSocket = socket
    socket.on('data', () => socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n')))
  })
  const redisPort = await listen(fakeRedis)
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({
    backendUrl: `http://127.0.0.1:${backendPort}`,
    internalToken: 'test-internal-token-1234',
    redis: { host: '127.0.0.1', port: redisPort },
  })
  const port = await app.start(0)
  try {
    await new Promise((resolve) => {
      const started = setInterval(() => {
        if (subscriberSocket) { clearInterval(started); resolve() }
      }, 10)
    })
    const stream = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(stream.statusCode, 200)
    let ended = false
    stream.on('end', () => { ended = true })
    stream.resume()
    subscriberSocket.destroy()
    fakeRedis.close()
    await new Promise((resolve) => setTimeout(resolve, 50))
    assert.equal(ended, true)
    const health = await readJson(await httpGet(port, '/internal/health', { Authorization: 'Bearer test-internal-token-1234' }))
    assert.equal(health.ready, false)
    assert.equal(health.transport, 'disconnected')
  } finally {
    app.stop()
    backend.server.close()
  }
})

test('sse frames use real newline bytes and exact frame shape', () => {
  assert.equal(sseFrame({ id: 'a', event: 'signal', data: '{}' }), 'id: a\nevent: signal\ndata: {}\n\n')
  assert.equal(signalFrame({ topic: 'unread', cursor: 'c' }), 'event: signal\ndata: {"topic":"unread","cursor":"c"}\n\n')
})

test('resp parser handles chunked replies and nested arrays', () => {
  const replies = []
  const parser = createRespParser({ onReply: (reply) => replies.push(reply) })
  const full = encodeCommand(['SUBSCRIBE', 'lovecards:realtime'])
  assert.equal(full, '*2\r\n$9\r\nSUBSCRIBE\r\n$18\r\nlovecards:realtime\r\n')
  const push = Buffer.from('*3\r\n$7\r\nmessage\r\n$18\r\nlovecards:realtime\r\n$2\r\nhi\r\n', 'utf8')
  parser.push(push.subarray(0, 5))
  parser.push(push.subarray(5, 20))
  parser.push(push.subarray(20))
  assert.equal(replies.length, 1)
  assert.equal(replies[0][0].toString(), 'message')
  assert.equal(replies[0][2].toString(), 'hi')
})

test('redis subscriber authenticates and selects the configured database before becoming ready', async () => {
  const commands = []
  let socketRef = null
  const fakeRedis = net.createServer((socket) => {
    socketRef = socket
    const parser = createRespParser({ onReply: (reply) => {
      const args = reply.map((value) => value.toString())
      commands.push(args)
      if (args[0] === 'AUTH' || args[0] === 'SELECT') socket.write('+OK\r\n')
      if (args[0] === 'SUBSCRIBE') socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n'))
    } })
    socket.on('data', (chunk) => parser.push(chunk))
  })
  const port = await listen(fakeRedis)
  const statuses = []
  const subscriber = new RedisSubscriber({
    host: '127.0.0.1', port, database: 2, password: 'test-secret', channel: 'lovecards:realtime',
    onMessage: () => {}, onStatus: (status) => statuses.push(status),
  })
  try {
    subscriber.start()
    for (let i = 0; i < 50 && statuses.length === 0; i += 1) await new Promise((resolve) => setTimeout(resolve, 10))
    assert.deepEqual(commands, [
      ['AUTH', 'test-secret'],
      ['SELECT', '2'],
      ['SUBSCRIBE', 'lovecards:realtime'],
    ])
    assert.deepEqual(statuses, ['up'])
  } finally {
    subscriber.stop()
    socketRef?.destroy()
    fakeRedis.close()
  }
})

test('redis TLS subscriber verifies certificate chain and server name before reporting ready', async () => {
  const directory = mkdtempSync(join(tmpdir(), 'lc-redis-tls-'))
  const keyPath = join(directory, 'redis.key')
  const certPath = join(directory, 'redis.crt')
  const openssl = process.env.OPENSSL_BIN || (process.platform === 'win32' ? 'C:\\Program Files\\Git\\usr\\bin\\openssl.exe' : 'openssl')
  try {
    execFileSync(openssl, ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', keyPath,
      '-out', certPath, '-days', '1', '-subj', '/CN=redis.test', '-addext', 'subjectAltName=DNS:redis.test'], {
      stdio: 'ignore',
      env: { ...process.env, ...(process.platform === 'win32' ? { OPENSSL_CONF: 'C:\\Program Files\\Git\\usr\\ssl\\openssl.cnf' } : {}) },
    })
    const cert = readFileSync(certPath)
    const sockets = new Set()
    const server = tls.createServer({ key: readFileSync(keyPath), cert }, (socket) => {
      sockets.add(socket)
      socket.on('close', () => sockets.delete(socket))
      socket.on('data', () => socket.write(Buffer.from('*3\r\n$9\r\nsubscribe\r\n$18\r\nlovecards:realtime\r\n:1\r\n')))
    })
    server.on('tlsClientError', () => {})
    const port = await listen(server)
    const run = async (name, trustedCa, expected) => {
      const statuses = []
      const subscriber = new RedisSubscriber({ host: '127.0.0.1', port, tls: true,
        tlsServerName: name, ...(trustedCa ? { ca: cert } : {}), channel: 'lovecards:realtime',
        onMessage: () => {}, onStatus: (status) => statuses.push(status), connectTimeoutMs: 500 })
      try {
        subscriber.start()
        for (let i = 0; i < 100 && !statuses.includes(expected); i += 1) {
          await new Promise((resolve) => setTimeout(resolve, 10))
        }
        assert.equal(statuses[0], expected)
        assert.equal(subscriber.ready, expected === 'up')
      } finally {
        subscriber.stop()
      }
    }
    try {
      await run('redis.test', true, 'up')
      await run('redis.test', false, 'down')
      await run('wrong.test', true, 'down')
    } finally {
      for (const socket of sockets) socket.destroy()
      server.close()
    }
  } finally {
    rmSync(directory, { recursive: true, force: true })
  }
})
