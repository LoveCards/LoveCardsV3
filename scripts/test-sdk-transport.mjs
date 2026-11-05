import assert from 'node:assert/strict'
import { once } from 'node:events'
import http from 'node:http'
import { mkdtemp, rm } from 'node:fs/promises'
import { createRequire } from 'node:module'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { operationResponseType } from './lib/sdk-codegen.mjs'

const root = fileURLToPath(new URL('..', import.meta.url))
const sdkRoot = path.join(root, 'packages/sdk')
const sdkRequire = createRequire(path.join(sdkRoot, 'package.json'))
const { build } = await import(pathToFileURL(sdkRequire.resolve('vite')).href)
const temporaryDir = await mkdtemp(path.join(tmpdir(), 'lovecards-sdk-transport-'))
const bundlePath = path.join(temporaryDir, 'sdk-under-test.mjs')
const requests = []
let server

function query(request) {
  return Object.fromEntries(request.url.searchParams.entries())
}

function body(request) {
  return request.body ? JSON.parse(request.body) : undefined
}

try {
  assert.equal(operationResponseType({
    'x-response-shape': 'raw',
    responses: {
      200: { content: { 'text/event-stream': { schema: { type: 'string' } } } },
    },
  }), 'string')

  await build({
    root: sdkRoot,
    configFile: false,
    logLevel: 'error',
    build: {
      emptyOutDir: true,
      minify: false,
      outDir: temporaryDir,
      lib: {
        entry: path.join(sdkRoot, 'src/index.ts'),
        formats: ['es'],
        fileName: () => path.basename(bundlePath),
      },
      rollupOptions: {
        output: { inlineDynamicImports: true },
      },
    },
  })

  const { createClient } = await import(pathToFileURL(bundlePath).href)
  server = http.createServer(async (request, response) => {
    const chunks = []
    for await (const chunk of request) chunks.push(chunk)
    const url = new URL(request.url, 'http://127.0.0.1')
    requests.push({
      method: request.method,
      url,
      body: Buffer.concat(chunks).toString('utf8'),
    })

    if (url.pathname === '/api/me/notifications/stream-ticket') {
      response.writeHead(200, { 'Content-Type': 'application/json' })
      response.end(JSON.stringify({ data: { ticket: 'encoded.signature', endpoint: 'http://127.0.0.1:7070/stream', cursor_strategy: 'opaque' } }))
      return
    }

    response.writeHead(204)
    response.end()
  })
  server.listen(0, '127.0.0.1')
  await once(server, 'listening')

  const address = server.address()
  assert(address && typeof address === 'object')
  const client = createClient({ apiUrl: `  http://127.0.0.1:${address.port}/api  ` })

  await client.library.remove({ resource_type: 'card', resource_id: '24' })
  await client.likes.unlike(27, { type: 'card' })
  await client.notifications.list({ cursor: 'opaque:created=2026-08-22T10%3A00%3A00Z&id=91', limit: 15, unread_only: true, type: 'content.card_liked', category: 'engagement' })
  await client.messages.list(31, { cursor: 'message-cursor', limit: 20 })
  await client.library.save({ resource_type: 'card', resource_id: '25' })
  await client.drafts.update(41, { title: 'Draft', content: 'Body', payload: { revision: 2 } })
  await client.files.listManaged({ search_keys: ['name', 'original_name'], order_desc: true })
  await client.operations.deliveries({ status: 'failed', worker_type: 'notification' })
  await client.operations.retryDelivery(52)
  await client.operations.runMigrations()
  await client.reports.transition(61, { status: 'resolved', resolution: 'policy_applied', note: 'reviewed' })
  await client.users.revokeSession(7, 'session-9')
  const streamTicket = await client.notifications.streamTicket()
  await client.library.history()
  await client.library.history_record({ resource_type: 'card', resource_id: '26' })
  await client.library.history_clear()
  await client.notifications.unread({ category: 'engagement' })
  await client.notifications.readAll({ category: 'system' })
  await client.notifications.read({ ids: [1, 2] })
  await client.messages.conversations()
  await client.messages.send({ recipient_id: 8, body: 'transport message', client_message_id: 'transport-1' })
  await client.drafts.list()
  await client.drafts.create({ title: 'Transport draft', content: 'Body', payload: { source: 'transport' } })
  await client.safety.preferences()
  await client.safety.preferences_update({ profile_visibility: 'public', messages_from: 'everyone', show_relations: true })
  await client.safety.report({ resource_type: 'card', resource_id: '26', reason: 'transport_test', details: 'fixture' })
  await client.cards.listPublicByUser('123456789012', { page: 2, list_rows: 30 })

  assert.equal(requests.length, 27)

  assert.equal(requests[0].method, 'DELETE')
  assert.equal(requests[0].url.pathname, '/api/me/library')
  assert.deepEqual(query(requests[0]), { resource_type: 'card', resource_id: '24' })

  assert.equal(requests[1].method, 'DELETE')
  assert.equal(requests[1].url.pathname, '/api/me/likes/27')
  assert.deepEqual(query(requests[1]), { type: 'card' })

  assert.equal(requests[2].method, 'GET')
  assert.equal(requests[2].url.pathname, '/api/me/notifications')
  assert.deepEqual(query(requests[2]), { cursor: 'opaque:created=2026-08-22T10%3A00%3A00Z&id=91', limit: '15', unread_only: 'true', type: 'content.card_liked', category: 'engagement' })

  assert.equal(requests[3].method, 'GET')
  assert.equal(requests[3].url.pathname, '/api/me/conversations/31/messages')
  assert.deepEqual(query(requests[3]), { cursor: 'message-cursor', limit: '20' })

  assert.equal(requests[4].method, 'POST')
  assert.equal(requests[4].url.pathname, '/api/me/library')
  assert.deepEqual(body(requests[4]), { resource_type: 'card', resource_id: '25' })

  assert.equal(requests[5].method, 'PATCH')
  assert.equal(requests[5].url.pathname, '/api/me/drafts/41')
  assert.deepEqual(body(requests[5]), { title: 'Draft', content: 'Body', payload: { revision: 2 } })

  assert.equal(requests[6].method, 'GET')
  assert.equal(requests[6].url.pathname, '/api/admin/files')
  assert.deepEqual(query(requests[6]), { search_keys: '["name","original_name"]', order_desc: 'true' })

  assert.equal(requests[7].method, 'GET')
  assert.equal(requests[7].url.pathname, '/api/admin/operations/deliveries')
  assert.deepEqual(query(requests[7]), { status: 'failed', worker_type: 'notification' })

  assert.equal(requests[8].method, 'POST')
  assert.equal(requests[8].url.pathname, '/api/admin/operations/deliveries/52/retry')
  assert.equal(body(requests[8]), undefined)

  assert.equal(requests[9].method, 'POST')
  assert.equal(requests[9].url.pathname, '/api/admin/operations/migrations/run')
  assert.equal(body(requests[9]), undefined)

  assert.equal(requests[10].method, 'POST')
  assert.equal(requests[10].url.pathname, '/api/admin/reports/61/transition')
  assert.deepEqual(body(requests[10]), { status: 'resolved', resolution: 'policy_applied', note: 'reviewed' })

  assert.equal(requests[11].method, 'POST')
  assert.equal(requests[11].url.pathname, '/api/admin/users/7/sessions/session-9/revoke')
  assert.equal(body(requests[11]), undefined)

  assert.equal(requests[12].method, 'GET')
  assert.equal(requests[12].url.pathname, '/api/me/notifications/stream-ticket')
  assert.deepEqual(query(requests[12]), {})
  assert.deepEqual(streamTicket, { ticket: 'encoded.signature', endpoint: 'http://127.0.0.1:7070/stream', cursor_strategy: 'opaque' })

  assert.equal(requests[13].method, 'GET')
  assert.equal(requests[13].url.pathname, '/api/me/history')
  assert.deepEqual(query(requests[13]), {})

  assert.equal(requests[14].method, 'POST')
  assert.equal(requests[14].url.pathname, '/api/me/history')
  assert.deepEqual(body(requests[14]), { resource_type: 'card', resource_id: '26' })

  assert.equal(requests[15].method, 'DELETE')
  assert.equal(requests[15].url.pathname, '/api/me/history')
  assert.deepEqual(query(requests[15]), {})

  assert.equal(requests[16].method, 'GET')
  assert.equal(requests[16].url.pathname, '/api/me/notifications/unread')
  assert.deepEqual(query(requests[16]), { category: 'engagement' })

  assert.equal(requests[17].method, 'POST')
  assert.equal(requests[17].url.pathname, '/api/me/notifications/read-all')
  assert.deepEqual(body(requests[17]), { category: 'system' })

  assert.equal(requests[18].method, 'POST')
  assert.equal(requests[18].url.pathname, '/api/me/notifications/read')
  assert.deepEqual(body(requests[18]), { ids: [1, 2] })

  assert.equal(requests[19].method, 'GET')
  assert.equal(requests[19].url.pathname, '/api/me/conversations')
  assert.deepEqual(query(requests[19]), {})

  assert.equal(requests[20].method, 'POST')
  assert.equal(requests[20].url.pathname, '/api/me/messages')
  assert.deepEqual(body(requests[20]), { recipient_id: 8, body: 'transport message', client_message_id: 'transport-1' })

  assert.equal(requests[21].method, 'GET')
  assert.equal(requests[21].url.pathname, '/api/me/drafts')
  assert.deepEqual(query(requests[21]), {})

  assert.equal(requests[22].method, 'POST')
  assert.equal(requests[22].url.pathname, '/api/me/drafts')
  assert.deepEqual(body(requests[22]), { title: 'Transport draft', content: 'Body', payload: { source: 'transport' } })

  assert.equal(requests[23].method, 'GET')
  assert.equal(requests[23].url.pathname, '/api/me/privacy')
  assert.deepEqual(query(requests[23]), {})

  assert.equal(requests[24].method, 'POST')
  assert.equal(requests[24].url.pathname, '/api/me/privacy')
  assert.deepEqual(body(requests[24]), { profile_visibility: 'public', messages_from: 'everyone', show_relations: true })

  assert.equal(requests[25].method, 'POST')
  assert.equal(requests[25].url.pathname, '/api/reports')
  assert.deepEqual(body(requests[25]), { resource_type: 'card', resource_id: '26', reason: 'transport_test', details: 'fixture' })

  assert.equal(requests[26].method, 'GET')
  assert.equal(requests[26].url.pathname, '/api/users/123456789012/cards')
  assert.deepEqual(query(requests[26]), { page: '2', list_rows: '30' })

  console.log('SDK localhost transport tests passed (27 requests).')
} finally {
  if (server?.listening) {
    await new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()))
  }
  await rm(temporaryDir, { recursive: true, force: true })
}
