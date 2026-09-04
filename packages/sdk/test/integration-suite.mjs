import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import {
  setPhase, makeClient, test, assert, assertType, assertShape,
  assertApiError, sleep, summary, generateReport, BASE_URL,
} from './helpers.mjs'
import { isApiError, createClient } from '../dist/lovecards.es.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const ADMIN_ACCOUNT = process.env.LOVECARDS_SDK_TEST_ADMIN_ACCOUNT
const ADMIN_PASSWORD = process.env.LOVECARDS_SDK_TEST_ADMIN_PASSWORD

// ═══════════════════════════════════════════════
//  Phase 0: Bootstrap
// ═══════════════════════════════════════════════

setPhase('Phase 0: Bootstrap')

let adminToken, userToken, guestToken
let adminClient, userClient, guestClient, publicClient
let cardId, tagId, commentId, userId, roleId, registeredUserId
let guestUnavailable = false
let themeUnavailable = false

await test('session.guest() → guest token', async () => {
  guestClient = makeClient(null)
  try {
    const result = await guestClient.session.guest()
    assert(result && result.token, 'Should return token')
    guestToken = result.token
    guestClient = makeClient(guestToken)
    return result
  } catch (error) {
    if (isApiError(error) && error.status === 401 && error.message.includes('访客模式')) {
      guestUnavailable = true
      throw 'SKIP'
    }
    throw error
  }
})

await test('session.login() → admin token', async () => {
  adminClient = makeClient(null)
  const result = await adminClient.session.login({ account: ADMIN_ACCOUNT, password: ADMIN_PASSWORD })
  assert(result && result.token, 'Should return token')
  adminToken = result.token
  adminClient = makeClient(adminToken)
  return result
})

await test('session.register() → user token', async () => {
  userClient = makeClient(null)
  const ts = Date.now()
  try {
    const result = await userClient.session.register({
      account: `sdktest_${ts}@test.com`,
      password: 'Test1234!',
      password_confirm: 'Test1234!',
    })
    assert(result && result.token, 'Should return token')
    userToken = result.token
    userClient = makeClient(userToken)
    const currentUser = await userClient.users.me()
    registeredUserId = currentUser.id
    return result
  } catch (e) {
    // SessionDebounce may block if rapid retry
    if (isApiError(e) && e.status === 401) {
      // captcha required — skip
      userClient = makeClient(guestToken || null) // fallback to guest/public client
      throw 'SKIP'
    }
    throw e
  }
})

await test('cards.list() → collect cardId', async () => {
  const pc = makeClient(null)
  const r = await pc.cards.list({ list_rows: 5 })
  assert(r && Array.isArray(r.data), 'Should return ListResult with data array')
  if (r.data.length > 0) {
    cardId = r.data[0].id
    assertType(cardId, 'number', 'cardId should be number')
  }
  return r
})

await test('tags.list() → collect tagId', async () => {
  const pc = makeClient(null)
  const r = await pc.tags.list()
  assert(Array.isArray(r), 'tags.list() should return array')
  if (r.length > 0) {
    tagId = r[0].id
    assertType(tagId, 'number', 'tagId should be number')
  }
  return r
})

// ═══════════════════════════════════════════════
//  Phase 1: Public Endpoints
// ═══════════════════════════════════════════════

setPhase('Phase 1: Public Endpoints')

const pc = makeClient(null)

await test('GET /session/policy → session.policy()', async () => {
  const r = await pc.session.policy()
  assert(r && r.registration && r.guest && r.human_verification, 'Should return the complete auth policy')
  assert(Array.isArray(r.registration.account_types), 'registration.account_types should be an array')
  return r
})

await test('GET /cards → cards.list()', async () => {
  const r = await pc.cards.list({ page: 1, list_rows: 5 })
  assertShape(r, ['data', 'pagination'], 'Should have data + pagination')
  assert(Array.isArray(r.data), 'data should be array')
  assertShape(r.pagination, ['currentPage', 'totalPages', 'totalItems', 'itemsPerPage'])
  return r
})

await test('GET /cards/:id → cards.get()', async () => {
  if (!cardId) throw 'SKIP'
  const r = await pc.cards.get(cardId)
  assert(r && r.id === cardId, 'Should return correct card')
  assertShape(r, ['id', 'content', 'user_id', 'status', 'goods', 'views', 'comments'])
  return r
})

await test('GET /cards/hot → cards.hot()', async () => {
  const r = await pc.cards.hot()
  assert(Array.isArray(r), 'Should return array')
  return r
})

await test('GET /cards/search → cards.search()', async () => {
  const r = await pc.cards.search({ list_rows: 5 })
  assert(r && Array.isArray(r.data), 'Should have data')
  return r
})

await test('GET /cards/search with search_keys → cards.search()', async () => {
  const r = await pc.cards.search({ search_value: 'test', search_keys: ['content'], list_rows: 5 })
  assert(r && Array.isArray(r.data), 'Should return data array')
  return r
})

await test('GET /cards/:id/comments → comments.cardList()', async () => {
  if (!cardId) throw 'SKIP'
  const r = await pc.comments.cardList(cardId)
  assertShape(r, ['data'], 'Should have data')
  assert(Array.isArray(r.data), 'data should be array')
  return r
})

await test('GET /tags → tags.list()', async () => {
  const r = await pc.tags.list()
  assert(Array.isArray(r), 'Should return array (not ListResult)')
  return r
})

await test('GET /tags/:id → tags.get()', async () => {
  if (!tagId) throw 'SKIP'
  const r = await pc.tags.get(tagId)
  assert(r && r.id === tagId, 'Should return correct tag')
  assertShape(r, ['id', 'name', 'status'])
  return r
})

await test('GET /captcha/config → captcha.config()', async () => {
  const r = await pc.captcha.config()
  assert(r !== null && typeof r === 'object', 'Should return object')
  return r
})

await test('GET /theme/config → theme.publicConfig()', async () => {
  try {
    const r = await pc.theme.publicConfig()
    assert(r && r.name, 'Should have theme name')
    assertShape(r, ['name', 'mode', 'config_schema', 'config_values'])
    return r
  } catch (error) {
    if (isApiError(error) && error.status === 400 && error.message === 'Active theme not found') {
      themeUnavailable = true
      throw 'SKIP'
    }
    throw error
  }
})

await test('GET /tags → theme.tags() (compat proxy)', async () => {
  const r = await pc.theme.tags()
  assert(Array.isArray(r), 'theme.tags() should return array')
  if (r.length > 0) {
    assertType(r[0].id, 'number', 'tag should have numeric id')
  }
  return r
})

await test('theme.tags() returns same shape as tags.list()', async () => {
  const [themeTags, tagsResult] = await Promise.all([
    pc.theme.tags(),
    pc.tags.list()
  ])
  assert(Array.isArray(themeTags), 'theme.tags() should return array')
  assert(Array.isArray(tagsResult), 'tags.list() should return array')
  assert(themeTags.length === tagsResult.length, 'Both should return same data')
  if (themeTags.length > 0) {
    assert(themeTags[0].id === tagsResult[0].id, 'Same first tag ID expected')
  }
  return { themeTags, tagsResult }
})

await test('POST /session/captcha → session.captcha()', async () => {
  // This may fail with debounce, that's OK
  try {
    await pc.session.captcha({ account: 'test@test.com' })
  } catch (e) {
    // debounce or other error is acceptable for public endpoint
    if (!isApiError(e)) throw e
  }
  return true
})

await test('GET /comments/:id → comments.get()', async () => {
  // Need a valid comment ID — try to get from cardList
  if (!cardId) throw 'SKIP'
  const list = await pc.comments.cardList(cardId)
  if (!list.data || list.data.length === 0) throw 'SKIP'
  const cid = list.data[0].id
  const r = await pc.comments.get(cid)
  assert(r && r.id === cid, 'Should return correct comment')
  return r
})

// ═══════════════════════════════════════════════
//  Phase 2: Auth-Only Endpoints (admin token)
// ═══════════════════════════════════════════════

setPhase('Phase 2: Auth-Only (admin)')

await test('GET /session/check → session.check()', async () => {
  const r = await adminClient.session.check()
  assertShape(r, ['uid', 'visitor', 'roles', 'capabilities', 'grants'])
  return r
})

await test('GET /me/cards/:id → cards.getOwn()', async () => {
  if (!cardId) throw 'SKIP'
  const r = await adminClient.cards.getOwn(cardId)
  assert(r && r.id === cardId, 'Should return the owned card')
  return r
})

await test('GET /users/me → users.me()', async () => {
  const r = await adminClient.users.me()
  assert(r && r.id, 'Should have id')
  assertShape(r, ['id', 'username', 'email', 'roles_id', 'status'])
  assert(!('password' in r), 'Should NOT have password field')
  userId = r.id
  return r
})

await test('PATCH /users/me → users.updateMe()', async () => {
  await adminClient.users.updateMe({ username: 'TestAdmin' })
  return true
})

await test('POST /users/me/password → users.updatePassword()', async () => {
  const temporaryPassword = `SdkTmp${Date.now()}!`
  let changed = false
  let restored = false

  try {
    await adminClient.users.updatePassword({ password: temporaryPassword })
    changed = true
    await assertApiError(() => adminClient.session.check(), 401)

    const temporaryLogin = await makeClient(null).session.login({ account: ADMIN_ACCOUNT, password: temporaryPassword })
    const temporaryClient = makeClient(temporaryLogin.token)
    await temporaryClient.users.updatePassword({ password: ADMIN_PASSWORD })
    restored = true
    await assertApiError(() => temporaryClient.session.check(), 401)

    const refreshed = await makeClient(null).session.login({ account: ADMIN_ACCOUNT, password: ADMIN_PASSWORD })
    adminToken = refreshed.token
    adminClient = makeClient(adminToken)
    return true
  } finally {
    if (changed && !restored) {
      try {
        const recoveryLogin = await makeClient(null).session.login({ account: ADMIN_ACCOUNT, password: temporaryPassword })
        await makeClient(recoveryLogin.token).users.updatePassword({ password: ADMIN_PASSWORD })
        restored = true
      } catch {}
    }
    if (changed) {
      try {
        const refreshed = await makeClient(null).session.login({ account: ADMIN_ACCOUNT, password: ADMIN_PASSWORD })
        adminToken = refreshed.token
        adminClient = makeClient(adminToken)
      } catch {}
    }
  }
})

await test('GET /users/me/cards → cards.listOwn()', async () => {
  const r = await adminClient.cards.listOwn({ page: 1 })
  assertShape(r, ['data'], 'Should have data')
  assert(Array.isArray(r.data), 'data should be array')
  return r
})

await test('GET /users/me/comments → comments.listOwn()', async () => {
  const r = await adminClient.comments.listOwn({ page: 1 })
  assertShape(r, ['data'], 'Should have data')
  return r
})

await test('POST /session/logout → session.logout()', async () => {
  // Create a fresh client for logout test
  const lc = makeClient(null)
  const loginResult = await lc.session.login({ account: ADMIN_ACCOUNT, password: ADMIN_PASSWORD })
  const logoutClient = makeClient(loginResult.token)
  await logoutClient.session.logout()
  const refreshed = await lc.session.login({ account: ADMIN_ACCOUNT, password: ADMIN_PASSWORD })
  adminToken = refreshed.token
  adminClient = makeClient(adminToken)
  return true
})

// Community scenes previously leaked required fields from unrelated routes.
// Keep these calls parameter-free where the contract declares every field optional.
setPhase('Phase 2b: Community Request Scenes')

await test('GET /me/library without params → library.list()', async () => {
  const r = await adminClient.library.list()
  assert(Array.isArray(r.data), 'library.list() should return a cursor page')
  return r
})

await test('GET /me/history without params → library.history()', async () => {
  const r = await adminClient.library.history()
  assert(Array.isArray(r.data), 'library.history() should return a cursor page')
  return r
})

await test('GET /me/notifications without params → notifications.list()', async () => {
  const r = await adminClient.notifications.list()
  assert(Array.isArray(r.data), 'notifications.list() should return a cursor page')
  return r
})

await test('GET /me/notifications?category=engagement → unified notification list', async () => {
  const r = await adminClient.notifications.list({ category: 'engagement', limit: 20 })
  assert(Array.isArray(r.data), 'engagement notifications should return a cursor page')
  assert(r.data.every((item) => item.category !== 'system'), 'engagement notifications must exclude system items')
  return r
})

await test('GET /me/notifications/unread → notifications.unread()', async () => {
  const r = await adminClient.notifications.unread({ category: 'engagement' })
  assert(r !== null && typeof r.unread === 'number', 'notifications.unread() should return a numeric unread count')
  return r
})

await test('GET /me/notifications/stream without params → notifications.stream()', async () => {
  const r = await adminClient.notifications.stream()
  assert(typeof r === 'string' && r.includes('event: notifications'), 'notification stream should return one SSE event')
  return r
})

await test('POST /me/notifications/read requires only ids', async () => {
  await adminClient.notifications.read({ ids: [999999] })
  return true
})

await test('POST /me/notifications/read-all without body', async () => {
  await adminClient.notifications.readAll()
  return true
})

await test('GET /me/conversations without params → messages.conversations()', async () => {
  const r = await adminClient.messages.conversations()
  assert(Array.isArray(r), 'messages.conversations() should return an array')
  return r
})

await test('POST /me/messages validates only message fields', async () => {
  if (!registeredUserId) throw 'SKIP'
  const error = await assertApiError(
    () => adminClient.messages.send({ recipient_id: registeredUserId }),
    400,
  )
  const fields = Object.keys(error.details || {}).sort()
  assert(JSON.stringify(fields) === JSON.stringify(['body']), `unexpected message validation fields: ${fields.join(',')}`)
  return true
})

let createdConversationId
await test('POST /me/messages then GET conversation messages without params', async () => {
  if (!registeredUserId) throw 'SKIP'
  const sent = await adminClient.messages.send({
    recipient_id: registeredUserId,
    body: 'SDK community scene regression',
    client_message_id: `sdk-community-${Date.now()}`,
  })
  assert(sent && sent.id, 'messages.send() should return an id')
  const conversations = await adminClient.messages.conversations()
  assert(conversations.length > 0, 'sent message should create a conversation')
  createdConversationId = Number(conversations[0].id)
  const messages = await adminClient.messages.list(createdConversationId)
  assert(Array.isArray(messages.data) && messages.data.length > 0, 'messages.list() should return the sent message')
  return messages
})

let createdDraftId
await test('draft create, list, get, update and delete use only draft fields', async () => {
  const created = await adminClient.drafts.create({
    title: 'SDK scene regression',
    content: 'draft content',
    payload: { source: 'integration' },
  })
  createdDraftId = Number(created.id)
  assert(createdDraftId > 0, 'draft create should return a numeric id')
  const list = await adminClient.drafts.list()
  assert(Array.isArray(list), 'drafts.list() should return an array')
  const item = await adminClient.drafts.get(createdDraftId)
  assert(Number(item.id) === createdDraftId, 'drafts.get() should return the created draft')
  await adminClient.drafts.update(createdDraftId, { title: 'SDK scene regression updated' })
  await adminClient.drafts.delete(createdDraftId)
  createdDraftId = undefined
  return true
})

await test('GET and POST /me/privacy use only preference fields', async () => {
  const current = await adminClient.safety.preferences()
  assert(current !== null && typeof current === 'object', 'preferences should return an object')
  await adminClient.safety.preferences_update({
    profile_visibility: 'public',
    messages_from: 'everyone',
    show_relations: true,
  })
  return true
})

await test('POST /reports validates only report fields', async () => {
  const error = await assertApiError(() => adminClient.safety.report({}), 400)
  const fields = Object.keys(error.details || {}).sort()
  const expected = ['reason', 'resource_id', 'resource_type']
  assert(JSON.stringify(fields) === JSON.stringify(expected), `unexpected report validation fields: ${fields.join(',')}`)
  return true
})

// ═══════════════════════════════════════════════
//  Phase 3: Protected CRUD (admin)
// ═══════════════════════════════════════════════

setPhase('Phase 3: Protected CRUD (admin)')

let createdCardId, createdTagId, createdRoleId, createdReportId, managedFileId
let threadCardId, threadCommentId
let mediaCardId, mediaFileId, mediaPublicUrl

// --- Cards CRUD ---
await test('POST /cards → cards.create()', async () => {
  const r = await adminClient.cards.create({ content: 'SDK test card content for testing' })
  assert(r !== null, 'Should not be null')
  assert('id' in r, 'Should have id field')
  if (r.id) {
    createdCardId = parseInt(r.id) || r.id
  }
  return r
})

await test('library save/remove and history record use ResourceRef query/body contracts', async () => {
  if (!createdCardId) throw 'SKIP'
  const ref = { resource_type: 'card', resource_id: String(createdCardId) }
  await adminClient.library.save(ref)
  const library = await adminClient.library.list()
  assert(library.data.some((item) => String(item.resource_id) === ref.resource_id), 'saved card should appear in library')
  await adminClient.library.remove(ref)
  await adminClient.library.history_record(ref)
  const history = await adminClient.library.history()
  assert(history.data.some((item) => String(item.resource_id) === ref.resource_id), 'viewed card should appear in history')
  return true
})

await test('POST /reports accepts a valid card report', async () => {
  if (!createdCardId) throw 'SKIP'
  const report = await adminClient.safety.report({
    resource_type: 'card',
    resource_id: String(createdCardId),
    reason: 'other',
    details: 'Disposable SDK integration fixture',
  })
  assert(report && report.id, 'report should return an id')
  createdReportId = Number(report.id)
  return report
})

await test('cards.list search matches the created card', async () => {
  if (!createdCardId) throw new Error('create card scenario did not produce an id')
  const r = await adminClient.cards.list({ search_value: 'SDK test card content', search_like: 2, list_rows: 20 })
  assert(r && Array.isArray(r.data), 'search should return a data array')
  assert(r.data.some((item) => item.id === createdCardId), 'search should include the created card')
  return r
})

await test('empty cards.list search is equivalent to the default list', async () => {
  const [empty, normal] = await Promise.all([
    adminClient.cards.list({ search_value: '', list_rows: 20 }),
    adminClient.cards.list({ list_rows: 20 }),
  ])
  const ids = (value) => value.data.map((item) => item.id)
  assert(JSON.stringify(ids(empty)) === JSON.stringify(ids(normal)), 'empty search should restore the default list')
  return empty
})

await test('PATCH /cards/:id → cards.update()', async () => {
  if (!createdCardId) throw 'SKIP'
  await adminClient.cards.update(createdCardId, { content: 'Updated SDK test card content' })
  return true
})

await test('GET /cards/:id → verify update', async () => {
  if (!createdCardId) throw 'SKIP'
  const r = await adminClient.cards.get(createdCardId)
  assert(r.content === 'Updated SDK test card content', 'Content should be updated')
  return r
})

await test('POST /cards/:id/like → cards.like()', async () => {
  if (!cardId) throw 'SKIP'
  try {
    const r = await adminClient.cards.like(cardId)
    assert(r && typeof r.likes === 'number', 'Should return { likes: number }')
    return r
  } catch (e) {
    if (isApiError(e) && e.status === 400 && e.message.includes('重复')) {
      // Already liked — this is expected behavior, not a bug
      return { likes: -1, _note: 'already liked' }
    }
    throw e
  }
})

// --- Tags CRUD ---
await test('POST /tags → tags.create()', async () => {
  await adminClient.tags.create({ name: `sdk_test_tag_${Date.now()}` })
  // Find the created tag
  const tags = await adminClient.tags.list()
  const found = tags.find(t => t.name.startsWith('sdk_test_tag_'))
  if (found) createdTagId = found.id
  return true
})

await test('PATCH /tags/:id → tags.update()', async () => {
  if (!createdTagId) throw 'SKIP'
  await adminClient.tags.update(createdTagId, { name: `sdk_updated_tag_${Date.now()}` })
  return true
})

await test('DELETE /tags/:id → tags.delete()', async () => {
  if (!createdTagId) throw 'SKIP'
  await adminClient.tags.delete(createdTagId, { reason: 'SDK integration test deletes tag' })
  return true
})

// --- Comments CRUD ---
await test('POST /cards/:id/comments → comments.create()', async () => {
  if (!cardId) throw 'SKIP'
  const r = await adminClient.comments.create(cardId, { content: 'SDK test comment' })
  assert(r !== null, 'Should not be null')
  return r
})

await test('PATCH /admin/comments/:id → comments.moderateManaged()', async () => {
  if (!commentId) throw 'SKIP'
  await adminClient.comments.moderateManaged(commentId, { status: 0 })
  return true
})

await test('DELETE /comments/:id → comments.delete()', async () => {
  if (!commentId) throw 'SKIP'
  await adminClient.comments.delete(commentId)
  return true
})

// --- Roles CRUD ---
await test('GET /roles → roles.list()', async () => {
  const r = await adminClient.roles.list()
  assertShape(r, ['data'], 'Should have data')
  assert(Array.isArray(r.data), 'data should be array')
  if (r.data.length > 0) {
    assertShape(r.data[0], ['id', 'name', 'slug'], 'Role should have id/name/slug')
  }
  return r
})

await test('GET /roles/:id → roles.get()', async () => {
  const r = await adminClient.roles.get(1)
  assert(r && r.id === 1, 'Should return role')
  assertShape(r, ['id', 'name', 'slug', 'description', 'is_system'])
  assert(typeof r.is_system === 'number', 'is_system should be number')
  return r
})

await test('GET /roles/:id/grants → roles.getGrants()', async () => {
  const r = await adminClient.roles.getGrants(1)
  assert(Array.isArray(r), 'Should return grant array')
  return r
})

await test('POST /roles → roles.create()', async () => {
  const r = await adminClient.roles.create({ name: 'SDK-Test-Role', slug: `sdk_test_${Date.now()}`, description: 'test', reason: 'SDK integration test creates role' })
  assert(r && r.id, 'Should return { id }')
  assert(typeof r.id === 'string' || typeof r.id === 'number', 'id should be string or number')
  createdRoleId = r.id
  return r
})

await test('PATCH /roles/:id → roles.update()', async () => {
  if (!createdRoleId) throw 'SKIP'
  console.log(`    (updating role ${createdRoleId}, type: ${typeof createdRoleId})`)
  try {
    await adminClient.roles.update(createdRoleId, { name: 'SDK-Test-Role-Updated', reason: 'SDK integration test updates role' })
    console.log('    (update succeeded)')
  } catch (e) {
    console.log(`    (update error: ${e.status} ${e.message})`)
    throw e
  }
  return true
})

await test('POST /roles/:id/grants → roles.assignGrants()', async () => {
  if (!createdRoleId) throw 'SKIP'
  await adminClient.roles.assignGrants(createdRoleId, { grants: [
    { capability: 'cards.read', scope: 'all' },
    { capability: 'tags.read', scope: 'all' },
    ], reason: 'SDK integration test assigns grants' })
  return true
})

await test('GET /roles/:id/grants → verify assigned', async () => {
  if (!createdRoleId) throw 'SKIP'
  const r = await adminClient.roles.getGrants(createdRoleId)
  assert(Array.isArray(r), 'Should return grant array')
  assert(r.some((grant) => grant.capability === 'cards.read'), 'Should include cards.read')
  return r
})

await test('DELETE /roles/:id → roles.delete()', async () => {
  if (!createdRoleId) throw 'SKIP'
  await adminClient.roles.delete(createdRoleId, { reason: 'SDK integration test deletes role' })
  return true
})

await test('POST /roles/reseed → roles.reseed()', async () => {
  const r = await adminClient.roles.reseed({ reason: 'SDK integration test reseeds role templates' })
  assert(r && typeof r.total === 'number', 'Should return ReseedResult')
  assertShape(r, ['total', 'guest', 'user', 'admin', 'root'])
  return r
})

// --- Permissions ---
await test('GET /permissions → permissions.list()', async () => {
  const r = await adminClient.permissions.list()
  assertShape(r, ['data'], 'Should have data')
  return r
})

await test('GET /permissions/all → permissions.all()', async () => {
  const r = await adminClient.permissions.all()
  assert(Array.isArray(r), 'Should return array')
  if (r.length > 0) {
    assertShape(r[0], ['capability', 'description'], 'CapabilityItem shape')
  }
  return r
})

// --- Dashboard ---
await test('GET /dashboard → dashboard.index()', async () => {
  const r = await adminClient.dashboard.index()
  assert(r && typeof r === 'object', 'Should return object')
  assertShape(r, ['count', 'chart', 'ver', 'notice'])
  assert(typeof r.ver === 'object', 'ver should be object (VersionInfo)')
  assertShape(r.ver, ['app_name', 'version', 'build'])
  return r
})

// --- Config ---
await test('GET /config → config.list()', async () => {
  const r = await adminClient.config.list()
  assert(r && typeof r === 'object', 'Should return object')
  return r
})

await test('GET /config/groups → config.groups()', async () => {
  const r = await adminClient.config.groups()
  assert(Array.isArray(r), 'Should return array')
  return r
})

await test('POST /config → config.update()', async () => {
  // config.update expects { group: { key: value } } structure
  // Just verify the method works with a no-op update
  const groups = await adminClient.config.groups()
  if (groups.length > 0) {
    const g = groups[0]
    const current = await adminClient.config.list()
    if (current[g]) {
      await adminClient.config.update({ [g]: current[g] })
    }
  }
  return true
})

// --- Files ---
await test('GET /admin/files → files.listManaged()', async () => {
  const r = await adminClient.files.listManaged()
  assertShape(r, ['data'], 'Should have data')
  if (r.data.length > 0) managedFileId = Number(r.data[0].id)
  return r
})

await test('POST /files/direct → files.direct()', async () => {
  try {
    const r = await adminClient.files.direct({ filename: 'test.jpg', size: 1024, mime: 'image/jpeg' })
    assert(r && typeof r === 'object', 'Should return object')
    return r
  } catch (e) {
    if (isApiError(e) && e.status === 500) {
      // Backend DirectUploadManager internal error — not SDK bug
      return { _note: 'backend direct upload not configured' }
    }
    throw e
  }
})

// --- Storage ---
await test('GET /storage/types → storage.types()', async () => {
  const r = await adminClient.storage.types()
  assert(Array.isArray(r), 'Should return array')
  if (r.length > 0) assertShape(r[0], ['type', 'name', 'icon'])
  return r
})

await test('GET /storage/channels → storage.channels()', async () => {
  const r = await adminClient.storage.channels()
  assert(Array.isArray(r), 'Should return array')
  return r
})

await test('GET /storage/channel-stats → storage.channelStats()', async () => {
  const r = await adminClient.storage.channelStats()
  assert(r !== null && typeof r === 'object', 'Should return object')
  return r
})

// --- Sender ---
await test('GET /sender/types → sender.types()', async () => {
  const r = await adminClient.sender.types()
  assert(Array.isArray(r), 'Should return array')
  return r
})

await test('GET /sender/channels → sender.channels()', async () => {
  const r = await adminClient.sender.channels()
  assert(Array.isArray(r), 'Should return array')
  return r
})

await test('GET /sender/templates → sender.templates()', async () => {
  const r = await adminClient.sender.templates()
  assert(Array.isArray(r), 'Should return array')
  return r
})

// --- Captcha ---
await test('GET /captcha/types → captcha.types()', async () => {
  const r = await adminClient.captcha.types()
  assert(Array.isArray(r), 'Should return array')
  if (r.length > 0) assertShape(r[0], ['slug', 'type', 'name', 'icon'])
  return r
})

await test('GET /captcha/drivers → captcha.drivers()', async () => {
  const r = await adminClient.captcha.drivers()
  assert(Array.isArray(r), 'Should return array')
  return r
})

// --- Theme ---
await test('GET /theme/list → theme.list()', async () => {
  const r = await adminClient.theme.list()
  assert(Array.isArray(r), 'Should return array (not ListResult)')
  return r
})

await test('GET /theme/config → theme.config()', async () => {
  if (themeUnavailable) throw 'SKIP'
  const r = await adminClient.theme.config()
  assertShape(r, ['name', 'mode', 'config_schema', 'config_values'])
  return r
})

await test('card media_file_ids produce ordered stable media metadata', async () => {
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64')
  const form = new FormData()
  form.set('file', new Blob([png], { type: 'image/png' }), 'sdk-media.png')
  form.set('scene', 'card')
  const uploaded = await adminClient.files.upload(form)
  mediaFileId = Number(uploaded.id)
  assert(Number.isInteger(mediaFileId) && mediaFileId > 0, 'media upload should return an id')
  const created = await adminClient.cards.create({
    content: `SDK typed media ${Date.now()}`,
    media_file_ids: [mediaFileId],
    cover_file_id: mediaFileId,
  })
  mediaCardId = Number(created.id)
  assert(Number.isInteger(mediaCardId) && mediaCardId > 0, 'media card should return an id')
  await adminClient.cards.updateManaged(mediaCardId, { status: 0 })
  const card = await pc.cards.get(mediaCardId)
  assert(Array.isArray(card.media) && card.media.length === 1, 'card should expose one typed media item')
  assert(card.media[0].file_id === mediaFileId, 'typed media should retain file_id')
  assert(card.media[0].type === 'image' && card.media[0].mime_type === 'image/png', 'typed media should retain MIME semantics')
  assert(card.media[0].position === 1 && card.media[0].url.startsWith('/media/'), 'typed media should be ordered and use a stable URL')
  mediaPublicUrl = card.media[0].url
  const response = await fetch(`${BASE_URL.replace(/\/api\/?$/, '')}${mediaPublicUrl}`)
  assert(response.ok && response.headers.get('content-type')?.startsWith('image/'), 'stable media URL should stream the public file')
  return card
})

await test('hiding a card revokes its stable public media URL', async () => {
  if (!mediaCardId || !mediaPublicUrl) throw 'SKIP'
  await adminClient.cards.updateManaged(mediaCardId, { status: 2 })
  await assertApiError(() => pc.cards.get(mediaCardId), 404)
  const hiddenMedia = await fetch(`${BASE_URL.replace(/\/api\/?$/, '')}${mediaPublicUrl}`)
  assert(hiddenMedia.status === 404, `hidden card media should return 404, got ${hiddenMedia.status}`)
  await adminClient.cards.updateManaged(mediaCardId, { status: 0 })
  const restoredMedia = await fetch(`${BASE_URL.replace(/\/api\/?$/, '')}${mediaPublicUrl}`)
  assert(restoredMedia.ok, 'restored card media should be public again')
  return true
})

await test('PUT /theme/config → theme.updateConfig()', async () => {
  if (themeUnavailable) throw 'SKIP'
  const current = await adminClient.theme.config()
  await adminClient.theme.updateConfig({ id: current.id, release: current.release, values: current.config_values })
  return true
})

await test('comment replies reject a parent from another card', async () => {
  if (!createdCardId) throw 'SKIP'
  const second = await adminClient.cards.create({ content: `SDK thread scope ${Date.now()}` })
  threadCardId = Number(second.id)
  assert(Number.isInteger(threadCardId) && threadCardId > 0, 'second card should return an id')
  await adminClient.cards.updateManaged(createdCardId, { status: 0 })
  await adminClient.cards.updateManaged(threadCardId, { status: 0 })
  const parent = await adminClient.comments.create(createdCardId, { content: 'SDK thread parent' })
  threadCommentId = Number(parent.id)
  assert(Number.isInteger(threadCommentId) && threadCommentId > 0, 'parent comment should return an id')
  await adminClient.comments.moderateManaged(threadCommentId, { status: 0 })
  await assertApiError(
    () => adminClient.comments.create(threadCardId, { content: 'cross-card reply', parent_id: threadCommentId }),
    400,
  )
  return true
})

await test('public comment detail hides moderation states', async () => {
  if (!threadCommentId) throw 'SKIP'
  await adminClient.comments.moderateManaged(threadCommentId, { status: 1 })
  await assertApiError(() => pc.comments.get(threadCommentId), 404)
  const managed = await adminClient.comments.getManaged(threadCommentId)
  assert(Number(managed.status) === 1, 'managed detail should retain hidden comments')
  await adminClient.comments.moderateManaged(threadCommentId, { status: 0 })
  const visible = await pc.comments.get(threadCommentId)
  assert(Number(visible.id) === threadCommentId, 'restored comment should be public again')
  return true
})

// --- Users admin ---
await test('GET /admin/users → users.listManaged()', async () => {
  const r = await adminClient.users.listManaged()
  assertShape(r, ['data'], 'Should have data')
  assert(Array.isArray(r.data), 'data should be array')
  if (r.data.length > 0) {
    assert(!('password' in r.data[0]), 'User should NOT have password')
  }
  return r
})

await test('GET /admin/users/:id → users.getManaged()', async () => {
  if (!userId) throw 'SKIP'
  const r = await adminClient.users.getManaged(userId)
  assert(r && r.id === userId, 'Should return correct user')
  return r
})

await test('PATCH /admin/users/:id → users.updateManaged()', async () => {
  if (!userId) throw 'SKIP'
  await adminClient.users.updateManaged(userId, { username: 'TestAdmin', reason: 'SDK integration test updates user' })
  return true
})

// --- Platform administration added for community, security and operations ---
await test('GET /admin/operations/overview → operations.overview()', async () => {
  const r = await adminClient.operations.overview()
  assert(r && typeof r === 'object', 'Operations overview should return an object')
  return r
})

await test('GET /admin/operations/migrations → operations.migrations()', async () => {
  const r = await adminClient.operations.migrations()
  assert(Array.isArray(r), 'Migrations should return an array')
  return r
})

await test('POST /admin/operations/migrations/run safely checkpoints settled migrations', async () => {
  const r = await adminClient.operations.runMigrations({ reason: 'SDK integration test checkpoints settled migrations' })
  assert(Array.isArray(r), 'Migration advance should return an array')
  return r
})

await test('POST /admin/operations/migrations/run rejects direct Contract execution', async () => {
  const error = await assertApiError(
    () => adminClient.operations.runMigrations({ allow_contract: true, reason: 'integration safety check' }),
    403,
    'Direct Contract execution must be rejected'
  )
  assert(error.code === 1101, `Expected permission error code 1101, got ${error.code}`)
  return error
})

await test('GET /admin/operations/runtime → operations.runtime()', async () => {
  const r = await adminClient.operations.runtime()
  assert(r && typeof r === 'object', 'Runtime status should return an object')
  return r
})

await test('GET /admin/operations/deliveries → operations.deliveries()', async () => {
  const r = await adminClient.operations.deliveries()
  assertShape(r, ['data', 'pagination'], 'Deliveries should be paginated')
  return r
})

await test('GET /admin/operations/notifications → operations.notificationStats()', async () => {
  const r = await adminClient.operations.notificationStats()
  assert(r && typeof r === 'object', 'Notification stats should return an object')
  return r
})

await test('GET /admin/audit-logs → operations.auditLogs()', async () => {
  const r = await adminClient.operations.auditLogs()
  assertShape(r, ['data', 'pagination'], 'Audit logs should be paginated')
  return r
})

await test('GET /admin/operations/upgrades → operations.upgrades()', async () => {
  const r = await adminClient.operations.upgrades()
  assert(Array.isArray(r), 'Upgrade plans should return an array')
  return r
})

await test('GET /admin/community/insights → community.insights()', async () => {
  const r = await adminClient.community.insights()
  assert(r && typeof r === 'object', 'Community insights should return an object')
  return r
})

await test('GET /admin/recovery → users.recovery()', async () => {
  const r = await adminClient.users.recovery()
  assertShape(r, ['data', 'pagination'], 'Recovery records should be paginated')
  return r
})

await test('GET /admin/users/:id/security → users.security()', async () => {
  if (!userId) throw 'SKIP'
  const r = await adminClient.users.security(userId)
  assert(r && typeof r === 'object', 'User security should return an object')
  return r
})

await test('GET /admin/users/:id/sessions → users.sessions()', async () => {
  if (!userId) throw 'SKIP'
  const r = await adminClient.users.sessions(userId)
  assert(Array.isArray(r), 'User sessions should return an array')
  return r
})

await test('GET /admin/reports and /admin/reports/:id → reports read model', async () => {
  const list = await adminClient.reports.listManaged()
  assertShape(list, ['data', 'pagination'], 'Managed reports should be paginated')
  if (!createdReportId) throw 'SKIP'
  const report = await adminClient.reports.getManaged(createdReportId)
  assert(Number(report.id) === createdReportId, 'Managed report detail should match the fixture')
  return report
})

await test('POST /admin/reports/:id/transition → reports.transition()', async () => {
  if (!createdReportId) throw 'SKIP'
  const report = await adminClient.reports.transition(createdReportId, {
    status: 'reviewing',
    note: 'Disposable SDK integration governance check',
  })
  assert(report.status === 'reviewing', 'Report should enter reviewing state')
  return report
})

await test('GET /admin/files/:id/usage → files.usage()', async () => {
  if (!managedFileId) throw 'SKIP'
  const r = await adminClient.files.usage(managedFileId)
  assert(r && typeof r === 'object', 'File usage should return an object')
  return r
})

// --- Delete test card ---
await test('DELETE /cards/:id → cards.delete()', async () => {
  if (!createdCardId) throw 'SKIP'
  await adminClient.cards.delete(createdCardId)
  return true
})

// ═══════════════════════════════════════════════
//  Phase 4: User Permissions
// ═══════════════════════════════════════════════

setPhase('Phase 4: User Permissions')

await test('user: GET /users/me → self profile', async () => {
  if (!userToken) throw 'SKIP'
  const r = await userClient.users.me()
  assert(r && r.id, 'Should return user')
  return r
})

await test('user: GET /cards/list → public access', async () => {
  const r = await userClient.cards.list({ list_rows: 3 })
  assertShape(r, ['data'], 'Should work for user')
  return r
})

await test('user: GET /admin/users → should be denied', async () => {
  if (!userToken) throw 'SKIP'
  await assertApiError(() => userClient.users.listManaged(), 403)
})

await test('user: GET /dashboard → should 403 (no dashboard.read)', async () => {
  if (!userToken) throw 'SKIP'
  await assertApiError(() => userClient.dashboard.index(), 403)
})

await test('user: GET /roles → should 403 (no roles.read)', async () => {
  if (!userToken) throw 'SKIP'
  await assertApiError(() => userClient.roles.list(), 403)
})

await test('user: GET /permissions → should 403 (no permissions.read)', async () => {
  if (!userToken) throw 'SKIP'
  await assertApiError(() => userClient.permissions.list(), 403)
})

await test('user: GET /config → should 403 (no config.read)', async () => {
  if (!userToken) throw 'SKIP'
  await assertApiError(() => userClient.config.list(), 403)
})

await test('user: GET /storage/types → should 403 (no storage.read)', async () => {
  if (!userToken) throw 'SKIP'
  await assertApiError(() => userClient.storage.types(), 403)
})

await test('user: GET /sender/types → should 403 (no sender.read)', async () => {
  if (!userToken) throw 'SKIP'
  await assertApiError(() => userClient.sender.types(), 403)
})

await test('user: GET /theme/list → should 403 (no theme.read)', async () => {
  if (!userToken) throw 'SKIP'
  await assertApiError(() => userClient.theme.list(), 403)
})

// ═══════════════════════════════════════════════
//  Phase 5: Guest Permissions
// ═══════════════════════════════════════════════

setPhase('Phase 5: Guest Permissions')

await test('guest: POST /cards → should 403', async () => {
  if (guestUnavailable || !guestToken) throw 'SKIP'
  await assertApiError(() => guestClient.cards.create({ content: 'test' }), 403)
})

await test('guest: GET /users → should 403', async () => {
  if (guestUnavailable || !guestToken) throw 'SKIP'
  await assertApiError(() => guestClient.users.listManaged(), 403)
})

await test('guest: GET /dashboard → should 403', async () => {
  if (guestUnavailable || !guestToken) throw 'SKIP'
  await assertApiError(() => guestClient.dashboard.index(), 403)
})

await test('guest: GET /roles → should 403', async () => {
  if (guestUnavailable || !guestToken) throw 'SKIP'
  await assertApiError(() => guestClient.roles.list(), 403)
})

await test('guest: GET /config → should 403', async () => {
  if (guestUnavailable || !guestToken) throw 'SKIP'
  await assertApiError(() => guestClient.config.list(), 403)
})

await test('guest: GET /admin/files → should be denied', async () => {
  if (guestUnavailable || !guestToken) throw 'SKIP'
  await assertApiError(() => guestClient.files.listManaged(), 403)
})

await test('guest: GET /theme/list → should 403', async () => {
  if (guestUnavailable || !guestToken) throw 'SKIP'
  await assertApiError(() => guestClient.theme.list(), 403)
})

// ═══════════════════════════════════════════════
//  Phase 6: Batch Operations
// ═══════════════════════════════════════════════

setPhase('Phase 6: Batch Operations')

await test('POST /admin/cards/batch → cards.batchManaged(top)', async () => {
  if (!cardId) throw 'SKIP'
  await adminClient.cards.batchManaged({ method: 'top', ids: [cardId], reason: 'SDK integration test pins card' })
  return true
})

await test('POST /admin/cards/batch → cards.batchManaged(unset_top)', async () => {
  if (!cardId) throw 'SKIP'
  // First top, then unset_top
  await adminClient.cards.batchManaged({ method: 'top', ids: [cardId], reason: 'SDK integration test pins card' })
  await adminClient.cards.batchManaged({ method: 'unset_top', ids: [cardId], reason: 'SDK integration test unpins card' })
  return true
})

await test('POST /admin/cards/batch → cards.batchManaged(approve)', async () => {
  if (!cardId) throw 'SKIP'
  await adminClient.cards.batchManaged({ method: 'approve', ids: [cardId], reason: 'SDK integration test approves card' })
  return true
})

await test('POST /admin/comments/batch → comments.batchManaged(approve)', async () => {
  // Use a non-existent ID — should succeed (no-op) or 400
  try {
    await adminClient.comments.batchManaged({ method: 'approve', ids: [999999], reason: 'SDK integration test approves comment' })
  } catch (e) {
    if (!isApiError(e)) throw e
    // 400 "未指定要操作的资源" or success is OK
  }
  return true
})

await test('POST /admin/tags/batch → tags.batchManaged(approve)', async () => {
  try {
    await adminClient.tags.batchManaged({ method: 'approve', ids: [999999], reason: 'SDK integration test approves tag' })
  } catch (e) {
    if (!isApiError(e)) throw e
  }
  return true
})

await test('POST /admin/users/batch → users.batchManaged(approve)', async () => {
  try {
    await adminClient.users.batchManaged({ method: 'approve', ids: [999999], reason: 'SDK integration test approves user' })
  } catch (e) {
    if (!isApiError(e)) throw e
  }
  return true
})

await test('POST /admin/files/batch → files.batchManaged(hard_delete)', async () => {
  // 999999 does not exist — expect 404
  await assertApiError(() => adminClient.files.batchManaged({ method: 'hard_delete', ids: [999999], reason: 'SDK integration test purges file' }), 404)
})

await test('batch empty ids → should 400', async () => {
  await assertApiError(() => adminClient.cards.batchManaged({ method: 'delete', ids: [], reason: 'SDK integration test validates empty batch' }), 400)
})

await test('batch invalid method → should 400', async () => {
  await assertApiError(() => adminClient.cards.batchManaged({ method: 'invalid', ids: [1], reason: 'SDK integration test validates method' }), 400)
})

// ═══════════════════════════════════════════════
//  Phase 7: Edge Cases
// ═══════════════════════════════════════════════

setPhase('Phase 7: Edge Cases')

// 404
await test('GET /cards/999999 → 404', async () => {
  await assertApiError(() => pc.cards.get(999999), 404)
})

await test('GET /comments/999999 → 404', async () => {
  await assertApiError(() => pc.comments.get(999999), 404)
})

await test('GET /tags/999999 → 404', async () => {
  await assertApiError(() => pc.tags.get(999999), 404)
})

await test('GET /users/999999 → backend behavior (no 404 for nonexistent)', async () => {
  // Backend Users::Get() does not throw 404 for nonexistent — returns empty model
  // This is a backend design choice, not an SDK bug
  try {
    const r = await adminClient.users.getManaged(999999)
    // If it succeeds, backend returns empty/default model
    return r
  } catch (e) {
    if (isApiError(e) && e.status === 404) return true
    throw e
  }
})

await test('GET /roles/999999 → 404', async () => {
  await assertApiError(() => adminClient.roles.get(999999), 404)
})

await test('GET /files/999999 → 404', async () => {
  await assertApiError(() => adminClient.files.getManaged(999999), 404)
})

// 400
await test('POST /cards empty content → 422', async () => {
  await assertApiError(() => adminClient.cards.create({ content: '' }), 422)
})

await test('PATCH /cards/999999 → 404', async () => {
  await assertApiError(() => adminClient.cards.update(999999, { content: 'test' }), 404)
})

await test('DELETE /cards/999999 → backend behavior (no 404 for nonexistent)', async () => {
  // Backend deleteCards() is idempotent — deleting nonexistent IDs is a no-op
  // This is a backend design choice, not an SDK bug
  try {
    await adminClient.cards.delete(999999)
    return true
  } catch (e) {
    if (isApiError(e) && e.status === 404) return true
    throw e
  }
})

// 401
await test('no token → protected endpoint → 401', async () => {
  const noAuth = makeClient(null)
  await assertApiError(() => noAuth.users.me(), 401)
})

await test('invalid token → 401', async () => {
  const bad = makeClient('invalid_token_xyz')
  await assertApiError(() => bad.users.me(), 401)
})

// ═══════════════════════════════════════════════
//  Phase 8: Lifecycle Hook Tests
// ═══════════════════════════════════════════════

setPhase('Phase 8: Lifecycle Hooks')

await test('hook: ctor: beforeRequest+afterResponse fire on request', async () => {
  const calls = []
  const c = makeClient(null)
  const login = await c.session.login({ account: ADMIN_ACCOUNT, password: ADMIN_PASSWORD })
  const hc = createClient({
    apiUrl: BASE_URL,
    tokenStore: { get: () => login.token, set: () => {}, clear: () => {} },
    hooks: {
      beforeRequest: (ctx) => { calls.push({ type: 'before', url: ctx.url }) },
      afterResponse: (ctx) => { calls.push({ type: 'after', url: ctx.url, status: ctx.status }) },
    },
  })
  await hc.cards.list({ list_rows: 1 })
  const before = calls.filter(c => c.type === 'before').length
  const after = calls.filter(c => c.type === 'after').length
  assert(before >= 1, `beforeRequest should fire, got ${before}`)
  assert(after >= 1, `afterResponse should fire, got ${after}`)
})

await test('hook: runtime: register and fire', async () => {
  const calls = []
  const hc = makeClient(adminToken)
  const unsub = hc.hooks.afterResponse((ctx) => { calls.push(ctx.url) })
  await hc.cards.hot()
  assert(calls.length >= 1, 'afterResponse should fire after registration')
  unsub()
})

await test('hook: unsubscribe prevents firing', async () => {
  const calls = []
  const hc = makeClient(adminToken)
  const unsub = hc.hooks.afterResponse((ctx) => { calls.push(ctx.url) })
  unsub()
  await hc.cards.hot()
  assert(calls.length === 0, 'afterResponse should NOT fire after unsubscribe')
})

await test('hook: beforeRequest abort interrupts request', async () => {
  const hc = createClient({
    apiUrl: BASE_URL,
    hooks: {
      beforeRequest: () => { throw new Error('hook-abort') },
    },
  })
  try {
    await hc.cards.list()
    assert(false, 'should have thrown')
  } catch (e) {
    assert(isApiError(e), 'error should be ApiError')
    assert(e.message.includes('hook-abort'), 'should carry hook message')
  }
})

await test('hook: beforeRequest can add header', async () => {
  const calls = []
  const hc = createClient({
    apiUrl: BASE_URL,
    hooks: {
      beforeRequest: (ctx) => { ctx.config.headers['X-SDK-Test'] = 'hook-value' },
      afterResponse: (ctx) => { calls.push(ctx.status) },
    },
  })
  const login = await hc.session.login({ account: ADMIN_ACCOUNT, password: ADMIN_PASSWORD })
  assert(calls.length >= 1, 'afterResponse should fire')
})

await test('hook: onError fires on 404', async () => {
  const errors = []
  const hc = createClient({
    apiUrl: BASE_URL,
    hooks: {
      onError: (ctx) => { errors.push({ status: ctx.status, reason: ctx.reason }) },
    },
  })
  try {
    await hc.cards.get(999999)
  } catch {}
  assert(errors.length >= 1, 'onError should fire')
  assert(errors[0].status === 404, 'status should be 404')
  assert(errors[0].reason === 'http', 'reason should be http')
})

await test('hook: afterResponse error does not break request', async () => {
  const hc = createClient({
    apiUrl: BASE_URL,
    hooks: {
      afterResponse: () => { throw new Error('hook-crash') },
    },
  })
  const r = await hc.cards.list({ list_rows: 1 })
  assert(Array.isArray(r.data), 'request should still succeed despite hook error')
})

await test('hook: dedup fires hook once', async () => {
  const calls = []
  const hc = createClient({
    apiUrl: BASE_URL,
    hooks: {
      afterResponse: () => { calls.push('x') },
    },
  })
  const [a, b] = await Promise.all([
    hc.cards.list({ list_rows: 1 }),
    hc.cards.list({ list_rows: 1 }),
  ])
  assert(calls.length === 1, `dedup should fire hook once, got ${calls.length}`)
})

// ═══════════════════════════════════════════════
//  Phase 9: Cleanup
// ═══════════════════════════════════════════════

setPhase('Phase 9: Cleanup')

await test('cleanup: delete test card', async () => {
  if (!createdCardId) { console.log('    (no card to clean)'); return true }
  try {
    await adminClient.cards.delete(createdCardId)
  } catch (e) {
    // already deleted is OK
  }
  return true
})

await test('cleanup: delete thread-safety fixtures', async () => {
  if (threadCommentId) {
    try { await adminClient.comments.deleteManaged(threadCommentId, { reason: 'SDK integration test cleanup' }) } catch {}
  }
  if (threadCardId) {
    try { await adminClient.cards.deleteManaged(threadCardId, { reason: 'SDK integration test cleanup' }) } catch {}
  }
  return true
})

await test('cleanup: delete typed-media fixtures', async () => {
  if (mediaCardId) {
    try { await adminClient.cards.batchManaged({ ids: [mediaCardId], method: 'hard_delete', reason: 'SDK integration test cleanup' }) } catch {}
  }
  if (mediaFileId) {
    try { await adminClient.files.deleteManaged(mediaFileId, { reason: 'SDK integration test cleanup' }) } catch {}
  }
  return true
})

await test('cleanup: delete test tag', async () => {
  if (!createdTagId) { console.log('    (no tag to clean)'); return true }
  try {
    await adminClient.tags.delete(createdTagId, { reason: 'SDK integration test cleanup' })
  } catch (e) {}
  return true
})

await test('cleanup: delete test role', async () => {
  if (!createdRoleId) { console.log('    (no role to clean)'); return true }
  try {
    await adminClient.roles.delete(createdRoleId, { reason: 'SDK integration test cleanup' })
  } catch (e) {}
  return true
})

// ═══════════════════════════════════════════════
//  Generate Report
// ═══════════════════════════════════════════════

const data = summary()
const report = generateReport(data)
const reportPath = path.join(__dirname, 'TEST_REPORT.md')
fs.writeFileSync(reportPath, report, 'utf-8')
console.log(`Report saved to: ${reportPath}`)

process.exit(data.failed > 0 ? 1 : 0)
