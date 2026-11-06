// 进程入口：env 见契约 §4.2。默认监听 127.0.0.1（由部署层反代出公网）。

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
  redis: process.env.LC_REDIS_HOST ? { host: process.env.LC_REDIS_HOST, port: Number(process.env.LC_REDIS_PORT ?? 6379) } : null,
  logger: (message) => console.log(`[realtime-gateway] ${message}`),
})

const port = Number(process.env.LC_REALTIME_GATEWAY_PORT ?? 20090)
const host = process.env.LC_REALTIME_GATEWAY_HOST ?? '127.0.0.1'

app.start(port).then((boundPort) => {
  console.log(`[realtime-gateway] listening on http://${host}:${boundPort}`)
})

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    app.stop()
    process.exit(0)
  })
}
