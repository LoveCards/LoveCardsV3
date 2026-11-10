// SSE 帧工具：真实换行字节（契约 §4.5），杜绝旧 FPM 伪 SSE 的 \n 字面量缺陷。

export function sseFrame({ id, event, data }) {
  const lines = []
  if (id !== undefined && id !== null && id !== '') lines.push(`id: ${id}`)
  if (event) lines.push(`event: ${event}`)
  if (data !== undefined && data !== null) lines.push(`data: ${data}`)
  return `${lines.join('\n')}\n\n`
}

export function retryDirective(ms) {
  return `retry: ${ms}\n\n`
}

export function pingComment() {
  return `: ping\n\n`
}

export function heartbeatFrame() {
  return sseFrame({ event: 'heartbeat', data: '{}' })
}

export function signalFrame({ topic, cursor }) {
  // topic 各有业务游标；没有全局流序号时不能把某个业务游标写成 SSE id。
  return sseFrame({ event: 'signal', data: JSON.stringify({ topic, cursor }) })
}
