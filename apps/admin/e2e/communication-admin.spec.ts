import { expect, test, type Page } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

async function mockSignalCatalog(page: Page) {
  await page.route('**/api/admin/communications/signals', route => {
    expect(route.request().method()).toBe('GET')
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {
      revision: 2,
      signals: [{
        key: 'notifications.new', topic: 'notifications', owner: 'Platform/Notifications',
        display_name: '新站内通知', impact_text: '提示用户读取新的站内通知。',
        default_enabled: true, enabled: true, event_type: 'notification.created', event_version: 1,
        handler_key: 'notification.created.signal',
      }],
    } }) })
  })
}

test.use({
  storageState: {
    cookies: [],
    origins: [{ origin: adminOrigin, localStorage: [{ name: 'token', value: 'communication-admin-mock-token' }] }],
  },
})

test('policy switch creates a reviewable change and sends the canonical event name', async ({ page }) => {
  test.setTimeout(90_000)
  await mockSignalCatalog(page)
  const policies = [
    { event_type: 'content.liked', event_version: 1, rule_key: 'notify', enabled: true, display_name: '点赞提醒', impact_text: '关闭后不生成点赞提醒。', notification_type: 'content.card_liked', recipient_rule: 'handler', user_disableable: true, locked: false, policy_revision: 6 },
    { event_type: 'system.notification.issued', event_version: 1, rule_key: 'notify', enabled: true, display_name: '系统安全与审核通知', impact_text: '必须始终开启。', notification_type: 'system.moderation', recipient_rule: 'handler', user_disableable: false, locked: true, policy_revision: 6 },
    { event_type: 'future.unknown', event_version: 1, rule_key: 'notify', enabled: true, display_name: null, impact_text: null, notification_type: null, recipient_rule: 'handler', user_disableable: true, locked: false, policy_revision: 6 },
  ]
  let saved: unknown = null
  await page.route('**/api/me', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: { id: 2, number: '1000000002', username: '运维测试员', email: 'operations@example.test', phone: '', avatar: '', roles_id: [], roles: [], capabilities: [], grants: [
      { capability: 'admin.access', scope: 'global' }, { capability: 'communications.read', scope: 'global' }, { capability: 'communications.configure', scope: 'global' },
    ], status: 0, stats: { received_likes: 0 } },
  }) }))
  await page.route('**/api/admin/communications/policies', async route => {
    if (route.request().method() === 'PUT') {
      saved = route.request().postDataJSON()
      await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { revision: 7, policies: [{ ...policies[0], enabled: false, policy_revision: 7 }, { ...policies[1], policy_revision: 7 }, { ...policies[2], policy_revision: 7 }] } }) })
    } else {
      await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { revision: 6, policies } }) })
    }
  })

  await page.goto('./apps/operations/notification-policies', { waitUntil: 'domcontentloaded' })
  const unknownRow = page.getByRole('row', { name: /future\.unknown/ })
  await expect(unknownRow).toContainText('待核对（当前已开启）', { timeout: 30_000 })
  await expect(unknownRow.locator('input[type=checkbox]')).toHaveCount(0)
  await expect(page.getByText('部分提醒规则缺少影响说明')).toBeVisible()
  const likedRow = page.getByRole('row', { name: /点赞提醒/ })
  await likedRow.locator('input[type=checkbox]').click({ force: true })
  await expect(page.getByText('已修改 1 项')).toBeVisible()
  await page.getByRole('button', { name: '审阅并保存' }).click()
  await expect(page.getByRole('dialog')).toContainText('点赞提醒：开启 → 关闭')
  await page.getByRole('button', { name: '确认保存' }).click()
  await expect.poll(() => saved).toEqual({
    expected_revision: 6,
    policies: [{ event_type: 'content.liked', event_version: 1, rule_key: 'notify', enabled: false }],
  })
  await expect(likedRow).toContainText('已关闭')
  await expect(page.getByText('当前策略版本 7')).toBeVisible()
})

test('communications readers can inspect policy catalog without mutation controls', async ({ page }) => {
  test.setTimeout(90_000)
  await mockSignalCatalog(page)
  const policies = [
    { event_type: 'content.liked', event_version: 1, rule_key: 'notify', enabled: true, display_name: '点赞提醒', impact_text: '关闭后不生成点赞提醒。', notification_type: 'content.card_liked', recipient_rule: 'handler', user_disableable: true, locked: false, policy_revision: 6 },
    { event_type: 'system.notification.issued', event_version: 1, rule_key: 'notify', enabled: true, display_name: '系统安全与审核通知', impact_text: '必须始终开启。', notification_type: 'system.moderation', recipient_rule: 'handler', user_disableable: false, locked: true, policy_revision: 6 },
  ]
  await page.route('**/api/me', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: { id: 2, number: '1000000002', username: '只读运维员', email: 'operations-read@example.test', phone: '', avatar: '', roles_id: [], roles: [], capabilities: [], grants: [
      { capability: 'admin.access', scope: 'global' }, { capability: 'communications.read', scope: 'global' },
    ], status: 0, stats: { received_likes: 0 } },
  }) }))
  await page.route('**/api/admin/communications/policies', route => {
    expect(route.request().method()).toBe('GET')
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { revision: 6, policies } }) })
  })

  await page.goto('./apps/operations/notification-policies', { waitUntil: 'domcontentloaded' })
  const likedRow = page.getByRole('row', { name: /点赞提醒/ })
  await expect(likedRow).toContainText('已开启', { timeout: 30_000 })
  await expect(likedRow.locator('input[type=checkbox]')).toHaveCount(0)
  await expect(page.getByRole('button', { name: '审阅并保存' })).toHaveCount(0)
  const signalRow = page.getByRole('row', { name: /新站内通知/ })
  await expect(signalRow).toContainText('已开启')
  await expect(signalRow.locator('input[type=checkbox]')).toHaveCount(0)
})

test('policy revision conflict keeps the draft for explicit review', async ({ page }) => {
  await mockSignalCatalog(page)
  const liked = { event_type: 'content.liked', event_version: 1, rule_key: 'notify', enabled: true, display_name: '点赞提醒', impact_text: '关闭后不生成点赞提醒。', notification_type: 'content.card_liked', recipient_rule: 'handler', user_disableable: true, locked: false, policy_revision: 6 }
  const followed = { ...liked, event_type: 'social.followed', display_name: '新关注提醒', impact_text: '关闭后不生成关注提醒。' }
  let getCount = 0
  const writes: unknown[] = []
  await page.route('**/api/me', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: { id: 2, number: '1000000002', username: '运维测试员', email: 'operations@example.test', phone: '', avatar: '', roles_id: [], roles: [], capabilities: [], grants: [
      { capability: 'admin.access', scope: 'global' }, { capability: 'communications.read', scope: 'global' }, { capability: 'communications.configure', scope: 'global' },
    ], status: 0, stats: { received_likes: 0 } },
  }) }))
  await page.route('**/api/admin/communications/policies', route => {
    if (route.request().method() === 'GET') {
      getCount++
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: getCount === 1
        ? { revision: 6, policies: [liked, followed] }
        : { revision: 7, policies: [{ ...liked, policy_revision: 7 }, { ...followed, enabled: false, policy_revision: 7 }] } }) })
    }
    writes.push(route.request().postDataJSON())
    if (writes.length === 1) return route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ error: { code: 9016, message: '策略已被其他操作更新' } }) })
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { revision: 8, policies: [{ ...liked, enabled: false, policy_revision: 8 }, { ...followed, enabled: false, policy_revision: 8 }] } }) })
  })

  await page.goto('./apps/operations/notification-policies', { waitUntil: 'domcontentloaded' })
  await page.getByRole('row', { name: /点赞提醒/ }).locator('input[type=checkbox]').click({ force: true })
  await page.getByRole('button', { name: '审阅并保存' }).click()
  await page.getByRole('button', { name: '确认保存' }).click()
  await expect(page.getByText('其他操作改变了：新关注提醒')).toBeVisible()
  await expect(page.getByText('已修改 1 项')).toBeVisible()
  await expect(page.getByRole('row', { name: /点赞提醒/ })).toContainText('已关闭')
  await page.getByRole('button', { name: '审阅并保存' }).click()
  await page.getByRole('button', { name: '确认保存' }).click()
  await expect.poll(() => writes.length).toBe(2)
  expect(writes[1]).toEqual({ expected_revision: 7, policies: [{ event_type: 'content.liked', event_version: 1, rule_key: 'notify', enabled: false }] })
})

test('delivery filter failure does not display records from the previous filter', async ({ page }) => {
  test.setTimeout(90_000)
  await page.route('**/api/me', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: { id: 2, number: '1000000002', username: '运维测试员', email: 'operations@example.test', phone: '', avatar: '', roles_id: [], roles: [], capabilities: [], grants: [
      { capability: 'admin.access', scope: 'global' }, { capability: 'communications.read', scope: 'global' },
    ], status: 0, stats: { received_likes: 0 } },
  }) }))
  await page.route('**/api/admin/operations/deliveries/summary', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true, data: { pending: 0, processing: 0, dead: 0, skipped: 0, checked_at: '2026-11-07 10:00:00' },
  }) }))
  await page.route('**/api/admin/operations/deliveries?**', route => {
    const query = new URL(route.request().url()).searchParams
    if (query.get('status') === 'dead') return route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ error: { code: 500, message: '查询失败' } }) })
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({
      success: true,
      data: [{ id: 22, event_type: 'message.sent', status: 'completed', policy_decision: 'applied', attempt_count: 1, last_error: null, updated_at: '2026-11-07 10:00:00' }],
      pagination: { currentPage: 2, totalPages: 2, totalItems: 35, itemsPerPage: 20 },
    }) })
  })

  await page.goto('./apps/operations/deliveries?view=all&page=2&list_rows=20', { waitUntil: 'domcontentloaded' })
  const priorRow = page.getByRole('row', { name: /message\.sent.*#22/ })
  await expect(priorRow).toBeVisible({ timeout: 30_000 })
  await expect(page).toHaveURL(/page=2/)
  await page.getByRole('button', { name: '失败', exact: true }).click()
  await expect(priorRow).toHaveCount(0)
  await expect(page.getByText('查询失败')).toBeVisible()
})

test('runtime validates before review and keeps the draft after a revision conflict', async ({ page }) => {
  test.setTimeout(90_000)
  const settings = {
    notification_interval_seconds: 20, message_interval_seconds: 45, unread_interval_seconds: 20,
    polling_backoff_factor: 2, polling_max_interval_seconds: 300, pause_when_hidden: true,
    worker_stale_seconds: 30, scheduler_stale_seconds: 3600, gateway_stale_seconds: 15, require_supervisor: false,
  }
  let revision = 3
  let validations = 0
  let writes = 0
  await page.route('**/api/me', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: { id: 2, number: '1000000002', username: '运维测试员', email: 'operations@example.test', phone: '', avatar: '', roles_id: [], roles: [], capabilities: [], grants: [
      { capability: 'admin.access', scope: 'global' }, { capability: 'operations.read', scope: 'global' },
      { capability: 'operations.runtime.configure', scope: 'global' },
    ], status: 0, stats: { received_likes: 0 } },
  }) }))
  await page.route('**/api/admin/operations/runtime', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true, data: { facilities: [], workers: [], eventing: { pending: 0, processing: 0, dead: 0 },
      communication: { desired_mode: 'polling', effective_mode: 'polling', status: 'ready', revision,
        reason_code: null, checked_at: '2026-11-07 10:00:00', settings: { ...settings, notification_interval_seconds: revision === 3 ? 20 : 25 },
        last_apply_state: null, last_apply_reason: null, capabilities: [] } },
  }) }))
  await page.route('**/api/admin/operations/runtime/validate', route => {
    validations++
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {
      valid: validations > 1,
      violations: validations === 1 ? [{ field: 'notification_interval_seconds', message: '请检查间隔' }] : [],
    } }) })
  })
  await page.route('**/api/admin/operations/runtime/config', route => {
    writes++
    revision = 4
    return route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ error: { code: 9016, message: '配置已变化' } }) })
  })

  await page.goto('./apps/operations/runtime', { waitUntil: 'domcontentloaded' })
  await page.getByRole('button', { name: '修改通信设置' }).click()
  const field = page.getByLabel('通知轮询间隔（秒）')
  await field.fill('21')
  await page.getByRole('button', { name: '应用变更' }).click()
  await expect(page.getByText('请检查间隔')).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  expect(writes).toBe(0)

  await page.getByRole('button', { name: '应用变更' }).click()
  await expect(page.getByRole('dialog')).toContainText('通知轮询间隔（秒）：20 → 21')
  await page.getByRole('button', { name: '确认应用' }).click()
  await expect(page.getByText('你的修改仍保留，请核对最新配置并重新审阅。')).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(field).toHaveValue('21')
  expect(writes).toBe(1)
  await page.getByRole('button', { name: '应用变更' }).click()
  await expect(page.getByRole('dialog')).toContainText('通知轮询间隔（秒）：25 → 21')
})
