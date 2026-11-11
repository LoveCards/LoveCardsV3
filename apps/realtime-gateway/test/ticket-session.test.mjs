// 使用真实 HTTP 流验证会话授权期限；不添加生产计时器测试接口。
import assert from 'node:assert/strict'
import http from 'node:http'
import net from 'node:net'
import test from 'node:test'
import { createApp } from '../src/app.js'

test('gateway requires explicit session TTL and closes streams before it expires', async () => {
  const sockets = new Set()
  const redis = net.createServer((socket) => {
    sockets.add(socket)
    socket.on('close', () => sockets.delete(socket))
    socket.on('data', () => socket.write('*3\r\n$9\r\nsubscribe\r\n$44\r\nlc:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa:realtime\r\n:1\r\n'))
  })
  await new Promise((resolve) => redis.listen(0, '127.0.0.1', resolve))
  let ttl
  const app = createApp({ backendUrl: 'http://backend.test', internalToken: 'test-ticket-token',
    redis: { channel: 'lc:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa:realtime', host: '127.0.0.1', port: redis.address().port },
    fetchImpl: async () => new Response(JSON.stringify({ success: true, data: {
      uid: 7, cursor_seed: null, connection_ttl_seconds: ttl,
    } }), { status: 200 }),
  })
  const port = await app.start(0)
  const connect = () => new Promise((resolve, reject) => {
    http.get({ host: '127.0.0.1', port, path: '/stream?ticket=session-test-ticket' }, resolve).on('error', reject)
  })
  try {
    for (let i = 0; i < 50 && app.state.transport !== 'redis'; i++) await new Promise((r) => setTimeout(r, 10))
    assert.equal(app.state.transport, 'redis')
    for (const invalid of [undefined, 0, -1, '60', 1.5]) {
      ttl = invalid
      const res = await connect()
      assert.equal(res.statusCode, 401)
      res.resume()
      assert.equal(app.state.connections.size, 0)
    }
    ttl = 1
    const started = performance.now()
    const res = await connect()
    assert.equal(res.statusCode, 200)
    res.resume()
    await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error('session stream exceeded its lifetime')), 1800)
      res.on('end', () => { clearTimeout(timeout); resolve() })
    })
    assert.ok(performance.now() - started < 1500)
    assert.equal(app.state.connections.size, 0)
  } finally {
    app.stop()
    for (const socket of sockets) socket.destroy()
    redis.close()
  }
})
