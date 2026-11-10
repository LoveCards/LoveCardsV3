// 进程入口：Redis 配置经后端内部 API 同步；后端的 LC_REDIS_HOST 可作为 env 应急来源。

import { createApp } from './app.js'

const required = (name) => {
  const value = process.env[name]
  if (!value) {
    console.error(`missing required env ${name}`)
    process.exit(1)
  }
  return value
}

const app = createApp({
  backendUrl: required('LC_BACKEND_URL'),
  internalToken: required('LC_RUNTIME_INTERNAL_TOKEN'),
  redisConfigPath: '/api/internal/realtime/redis-config',
  maxConnections: Number(process.env.LC_REALTIME_MAX_CONNECTIONS ?? 100),
  logger: (message) => console.log(`[realtime-gateway] ${message}`),
})

const port = Number(process.env.LC_REALTIME_GATEWAY_PORT ?? 20090)
const host = process.env.LC_REALTIME_GATEWAY_HOST ?? '127.0.0.1'

app.start(port, host).then((boundPort) => {
  console.log(`[realtime-gateway] listening on http://${host}:${boundPort}`)
})

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    app.stop()
    process.exit(0)
  })
}
