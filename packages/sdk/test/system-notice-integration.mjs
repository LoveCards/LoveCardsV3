import assert from 'node:assert/strict'
import { randomUUID } from 'node:crypto'
import { execFile } from 'node:child_process'
import { promisify } from 'node:util'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

// 本测试会创建账号、角色和通知，仅允许一次性 localhost 实例；不得指向常驻 dev。
const run = promisify(execFile)
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..')
const base = new URL(process.env.LOVECARDS_SDK_TEST_BASE_URL || 'http://invalid.invalid')
const database = process.env.LOVECARDS_E2E_DB_NAME || ''
const localHosts = ['127.0.0.1', 'localhost', '[::1]', '::1']
const instance = process.env.LC_INSTANCE_ROOT || ''
const relativeInstance = path.relative(root, path.resolve(instance))
if (process.env.LOVECARDS_SDK_TEST_ISOLATED !== '1'
  || process.env.LOVECARDS_E2E_ISOLATED !== '1'
  || process.env.LC_ENV_NAME !== 'e2e'
  || base.protocol !== 'http:' || !localHosts.includes(base.hostname)
  || !base.port || ['6001', '20089', '20087'].includes(base.port)
  || base.pathname.replace(/\/$/, '') !== '/api' || base.search || base.hash || base.username || base.password
  || !/^lovecards_e2e_[a-z0-9_]+$/.test(database)
  || !path.isAbsolute(instance) || !instance.split(/[\\/]/).includes(database)
  || (!relativeInstance.startsWith('..' + path.sep) && !path.isAbsolute(relativeInstance))
  || (process.env.LC_INSTANCE_ROOT_RESOLVED && path.resolve(process.env.LC_INSTANCE_ROOT_RESOLVED) !== path.resolve(instance))
  || !['DATABASE_', 'PHP_DATABASE_'].every(prefix =>
    process.env[prefix + 'DATABASE'] === database && localHosts.includes(process.env[prefix + 'HOSTNAME']))) {
  throw new Error('Refusing system-notice integration outside a named disposable localhost instance')
}
const { createClient, isApiError } = await import(pathToFileURL(path.join(root, 'packages/sdk/dist/lovecards.es.js')).href)
const client = token => createClient({ apiUrl: base.href.replace(/\/$/, ''), debug: false,
  tokenStore: token ? { get: () => token, set() {}, clear() {} } : undefined,
})
const account = process.env.LOVECARDS_SDK_TEST_ADMIN_ACCOUNT
const password = process.env.LOVECARDS_SDK_TEST_ADMIN_PASSWORD
if (!account || !password) throw new Error('Missing disposable administrator credentials')
const publicClient = client()
const login = await publicClient.session.login({ account, password })
const admin = client(login.token)
const administrator = await admin.users.me()
assert(administrator.grants.some(g => g.capability === 'notifications.publish'), 'Fixture administrator must have publishing access')
const consumption = await admin.operations.eventConsumption()
await admin.operations.applyEventConsumption({ mode: 'scheduled', scheduled_trigger: 'cli', expected_revision: consumption.revision })

const results = []
async function check(name, fn) {
  try { await fn(); results.push({ name, status: 'PASS' }); console.log('PASS ' + name) }
  catch (error) { results.push({ name, status: 'FAIL', message: error.message }); console.log('FAIL ' + name + ': ' + error.message) }
}
async function rejected(fn, codes) {
  try { await fn() } catch (error) {
    assert(isApiError(error), 'Expected an SDK ApiError')
    assert(codes.includes(error.status), `Unexpected error status ${error.status}`)
    return
  }
  assert.fail('Expected request rejection')
}
async function register(suffix) {
  await new Promise(resolve => setTimeout(resolve, 7500))
  const credentials = { account: `notice_${suffix}_${randomUUID().slice(0, 12)}@example.test`, password: 'DisposableNotice123!' }
  const login = await publicClient.session.register({ ...credentials, password_confirm: credentials.password })
  const api = client(login.token)
  return { ...credentials, api, user: await api.users.me() }
}
async function relogin(identity) {
  const result = await publicClient.session.login({ account: identity.account, password: identity.password })
  identity.api = client(result.token)
}
async function createPublisher(identity, broadcast = false) {
  const role = await admin.roles.create({ name: '隔离通知发布员', slug: `notice_${randomUUID().replaceAll('-', '')}` })
  const id = Number(typeof role === 'object' ? role.id : role)
  assert(id > 0, 'Disposable publisher role must return its identity')
  await admin.roles.assignGrants(id, { grants: [
    { capability: 'admin.access', scope: 'global' }, { capability: 'notifications.publish', scope: 'global' },
    ...(broadcast ? [{ capability: 'notifications.broadcast', scope: 'global' }] : []),
  ], reason: '隔离通知集成测试的受限发布角色' })
  await admin.users.assignRoles(identity.user.id, { roles_id: [id], reason: '隔离通知集成测试赋予受限发布角色' })
  await relogin(identity)
  return id
}
async function consume() {
  await run(process.env.PHP_BINARY || 'php', ['think', 'lc:scheduler:run-once'], {
    cwd: path.join(root, 'apps/backend'), env: process.env, timeout: 60_000, maxBuffer: 1024 * 1024,
  })
}
async function settle(api, id) {
  let batch
  for (let i = 0; i < 8; i++) {
    await consume()
    batch = await api.notifications.getBatch(id)
    if (['completed', 'partial_failure', 'failed'].includes(batch.status)) return batch
  }
  assert.fail('Notification batch did not reach a terminal state within bounded scheduler runs')
}
async function notices(api, message) {
  const page = await api.notifications.list({ limit: 100, type: 'system.notice' })
  return page.data.filter(row => row.payload?.message === message)
}
const recipient = await register('recipient')
const sender = await register('sender')
const outsider = await register('outsider')
const senderRole = await createPublisher(sender)
await createPublisher(outsider, true)
let prepared
const message = '隔离系统通知：' + randomUUID()
const input = { request_key: randomUUID(), audience: 'selected', recipient_ids: [administrator.id, recipient.user.id], message, action_url: '/settings', severity: 'info' }

await check('ordinary user cannot prepare a system-notice batch', () => rejected(() => recipient.api.notifications.prepareBatch(input), [403]))
await check('anonymous caller cannot prepare a system-notice batch', () => rejected(() => publicClient.notifications.prepareBatch(input), [401]))
await check('recipient candidates expose only id and username without users.read', async () => {
  const candidates = await sender.api.notifications.recipientCandidates({ query: String(recipient.user.id), limit: 10 })
  assert(Array.isArray(candidates))
  assert(candidates.some(row => row.id === recipient.user.id), 'Numeric lookup must find the chosen formal user')
  for (const row of candidates) assert.deepEqual(Object.keys(row).sort(), ['id', 'username'])
})
await check('selected publication freezes recipients and content without creating notifications', async () => {
  prepared = await admin.notifications.prepareBatch(input)
  assert.equal(prepared.status, 'prepared')
  assert.equal(prepared.recipient_count, 2)
  assert.equal(prepared.generated_count, 0)
  assert.equal(prepared.can_confirm, true)
  assert.equal(prepared.message, input.message)
  assert.deepEqual([...prepared.recipient_ids].sort((a, b) => a - b), [...input.recipient_ids].sort((a, b) => a - b))
  assert.equal((await notices(admin, message)).length, 0)
})
await check('same request key with identical input returns the same frozen batch', async () => {
  const replay = await admin.notifications.prepareBatch(input)
  assert.equal(replay.id, prepared.id)
  assert.equal(replay.created_at, prepared.created_at)
})
await check('same request key with changed content returns a conflict', () => rejected(() => admin.notifications.prepareBatch({ ...input, message: input.message + '更改' }), [409]))
await check('lost-response lookup resolves the exact request key without publishing', async () => {
  const result = await admin.notifications.latestBatch({ request_key: input.request_key })
  assert.equal(result.batch?.id, prepared.id)
  assert.equal(result.batch?.status, 'prepared')
})
await check('different publisher cannot read or confirm another publisher batch', async () => {
  await rejected(() => outsider.api.notifications.getBatch(prepared.id), [403, 404])
  await rejected(() => outsider.api.notifications.confirmBatch(prepared.id), [403, 404])
  assert.equal((await outsider.api.notifications.latestBatch({ request_key: input.request_key })).batch, null)
})
await check('concurrent confirmations keep one batch and do not duplicate recipients', async () => {
  const responses = await Promise.all([admin.notifications.confirmBatch(prepared.id), admin.notifications.confirmBatch(prepared.id)])
  assert(responses.every(row => row.id === prepared.id))
  const terminal = await settle(admin, prepared.id)
  assert.equal(terminal.status, 'completed')
  assert.equal(terminal.generated_count, 2)
  assert.equal(terminal.pending_count, 0)
  assert.equal(terminal.failed_count, 0)
  const self = await notices(admin, message)
  const received = await notices(recipient.api, message)
  assert.equal(self.length, 1, 'System sender must not exclude the administrator as a recipient')
  assert.equal(received.length, 1)
  assert.equal(self[0].actor_id, null)
  assert.equal(self[0].actor_state, 'system')
  assert.equal(self[0].actor, null)
})
await check('completed confirmation replay never creates another notification', async () => {
  await admin.notifications.confirmBatch(prepared.id)
  await consume()
  assert.equal((await notices(recipient.api, message)).length, 1)
})
await check('publisher without broadcast permission cannot prepare an all-user batch', () => rejected(() => sender.api.notifications.prepareBatch({ request_key: randomUUID(), audience: 'all', message: '禁止未授权的全站发送' }), [403]))
await check('unknown selected recipient is rejected instead of silently shrinking scope', () => rejected(() => admin.notifications.prepareBatch({ ...input, request_key: randomUUID(), recipient_ids: [2147483647] }), [400, 404, 422]))
await check('unsafe external action URL is rejected before publication', () => rejected(() => admin.notifications.prepareBatch({ ...input, request_key: randomUUID(), action_url: '//example.invalid/path' }), [400, 422]))
await check('multibyte payload beyond the existing byte budget is rejected', () => rejected(() => admin.notifications.prepareBatch({ ...input, request_key: randomUUID(), message: '汉'.repeat(1500) }), [400, 422]))

let all
let late
const allMessage = '隔离全站通知：' + randomUUID()
await check('all-user preview freezes formal users and avoids returning the whole audience', async () => {
  all = await admin.notifications.prepareBatch({ request_key: randomUUID(), audience: 'all', message: allMessage })
  assert.equal(all.status, 'prepared')
  assert(all.recipient_count >= 4)
  assert.equal(all.recipient_ids.length, 0)
  assert.equal(all.recipients.length, 0)
  late = await register('after_snapshot')
  const unchanged = await admin.notifications.getBatch(all.id)
  assert.equal(unchanged.recipient_count, all.recipient_count)
})
await check('all-user confirmation does not include users registered after its frozen preview', async () => {
  await admin.notifications.confirmBatch(all.id)
  const terminal = await settle(admin, all.id)
  assert.equal(terminal.generated_count + terminal.skipped_count, terminal.recipient_count)
  assert.equal(terminal.pending_count, 0)
  assert.equal((await notices(late.api, allMessage)).length, 0)
  assert.equal((await notices(recipient.api, allMessage)).length, 1)
})
await check('publishing permission is checked again after a batch has been prepared', async () => {
  const draft = await sender.api.notifications.prepareBatch({ request_key: randomUUID(), audience: 'selected', recipient_ids: [recipient.user.id], message: '隔离撤权前准备的通知' })
  await admin.roles.assignGrants(senderRole, { grants: [{ capability: 'admin.access', scope: 'global' }], reason: '隔离通知测试验证确认前撤权' })
  await rejected(() => sender.api.notifications.confirmBatch(draft.id), [403])
})

console.log(JSON.stringify({ total: results.length, passed: results.filter(row => row.status === 'PASS').length,
  failed: results.filter(row => row.status === 'FAIL').length, results }, null, 2))
if (results.some(row => row.status === 'FAIL')) process.exitCode = 1
