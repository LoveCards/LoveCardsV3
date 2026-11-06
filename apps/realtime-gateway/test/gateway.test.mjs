// Realtime Gateway 行为测试：health 协议、一次性 ticket、source-poll 回源、Redis 信号、SSE 字节规范。
import assert from 'node:assert/strict'
import http from 'node:http'
import net from 'node:net'
import test from 'node:test'

import { createApp, PROTOCOL } from '../src/app.js'
import { createRespParser, encodeCommand } from '../src/redis-subscriber.js'
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
  const tickets = new Map([['valid-ticket-26chars-hex', { uid: 7, cursor_seed: 'seed-1' }]])
  const notifications = { latest_cursor: 'seed-1', newer: 0 }
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
    if (req.method === 'GET' && req.url.startsWith('/api/internal/realtime/notifications-since')) {
      res.writeHead(200, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({ success: true, data: notifications }))
      return
    }
    res.writeHead(404)
    res.end()
  })
  return { server, tickets, notifications }
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
    assert.equal(health.ready, true)
    assert.equal(health.cursor, true)
    assert.deepEqual(health.auth, ['ticket'])
    assert.equal(health.transport, 'source-poll')
    assert.equal(typeof health.version, 'string')
  } finally {
    app.stop()
    backend.server.close()
  }
})

test('stream rejects unknown ticket with 401 and consumes tickets once', async () => {
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({ backendUrl: `http://127.0.0.1:${backendPort}`, internalToken: 'test-internal-token-1234', pollIntervalMs: 60 })
  const port = await app.start(0)
  try {
    const denied = await httpGet(port, '/stream?ticket=unknown-ticket-value')
    assert.equal(denied.statusCode, 401)
    await readJson(denied)

    const first = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(first.statusCode, 200)
    assert.match(first.headers['content-type'], /text\/event-stream/)
    assert.equal(first.headers['x-accel-buffering'], 'no')

    const second = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    assert.equal(second.statusCode, 401)
    await readJson(second)
    first.destroy()
  } finally {
    app.stop()
    backend.server.close()
  }
})

test('source-poll fallback emits signal frames when backend reports newer notifications', async () => {
  const backend = createFakeBackend()
  const backendPort = await listen(backend.server)
  const app = createApp({ backendUrl: `http://127.0.0.1:${backendPort}`, internalToken: 'test-internal-token-1234', pollIntervalMs: 80 })
  const port = await app.start(0)
  try {
    const stream = await httpGet(port, '/stream?ticket=valid-ticket-26chars-hex')
    const framePromise = collectFrames(stream, 2)
    setTimeout(() => {
      backend.notifications.latest_cursor = 'cursor-abc'
      backend.notifications.newer = 3
    }, 150)
    const frames = await framePromise
    assert.equal(frames[0], 'retry: 2000')
    assert.match(frames[1], /^id: cursor-abc\nevent: signal\ndata: \{"topic":"notifications","cursor":"cursor-abc"}$/)
    stream.destroy()
  } finally {
    app.stop()
    backend.server.close()
  }
})

test('redis transport pushes recipient-scoped signals as SSE frames', async () => {
  let subscriberSocket = null
  const fakeRedis = net.createServer((socket) => {
    subscriberSocket = socket
    socket.on('data', () => {
      // 忽略 SUBSCRIBE 字节，测试直接推送 message push 帧。
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
    const framePromise = collectFrames(stream, 2)

    // 定向 uid 不匹配的信号必须被忽略，匹配的必须送达。
    subscriberSocket.write(encodeMessage({ topic: 'notifications', uid: 'user:9', cursor: 'x' }))
    subscriberSocket.write(encodeMessage({ topic: 'notifications', uid: 'user:7', cursor: 'cursor-r1' }))

    const frames = await framePromise
    assert.equal(frames[0], 'retry: 2000')
    assert.match(frames[1], /^id: cursor-r1\nevent: signal\ndata: \{"topic":"notifications","cursor":"cursor-r1"}$/)
    stream.destroy()
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

test('sse frames use real newline bytes and exact frame shape', () => {
  assert.equal(sseFrame({ id: 'a', event: 'signal', data: '{}' }), 'id: a\nevent: signal\ndata: {}\n\n')
  assert.equal(signalFrame({ id: 'c', topic: 'unread', cursor: 'c' }), 'id: c\nevent: signal\ndata: {"topic":"unread","cursor":"c"}\n\n')
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
