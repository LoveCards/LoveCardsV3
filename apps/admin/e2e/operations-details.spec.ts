import { expect, test } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

test.use({
  storageState: {
    cookies: [],
    origins: [{
      origin: adminOrigin,
      localStorage: [{ name: 'token', value: 'operations-details-mock-token' }],
    }],
  },
})

test.beforeEach(async ({ page }) => {
  await page.route('**/api/me', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          id: 2,
          number: '1000000002',
          username: '运维测试员',
          email: 'operations@example.test',
          phone: '',
          avatar: '',
          roles_id: [],
          roles: [],
          capabilities: [],
          grants: [
            { capability: 'admin.access', scope: 'global' },
            { capability: 'communications.read', scope: 'global' },
            { capability: 'operations.retry', scope: 'global' },
            { capability: 'audit.read', scope: 'global' },
            { capability: 'users.read', scope: 'all' },
            { capability: 'cards.read', scope: 'all' },
          ],
          status: 0,
          stats: { received_likes: 0 },
        },
      }),
    })
  })
})

const deliveryFixture = (status = 'dead') => ({
  id: 77,
  event_id: 912,
  event_type: 'content.card_liked',
  handler_key: 'notifications.card-liked',
  queue_name: 'notifications',
  execution_mode: 'async',
  status,
  attempt_count: status === 'dead' ? 3 : 4,
  available_at: '2026-08-23 09:00:00',
  last_error: status === 'dead' ? 'recipient projection unavailable' : null,
  correlation_id: 'correlation-delivery-77',
  created_at: '2026-08-23 08:59:58',
  updated_at: '2026-08-23 09:00:01',
  payload: { private: 'this value must never be rendered' },
})

const auditFixture = {
  id: 88,
  actor_id: 2,
  action: 'cards.update',
  resource_type: 'card',
  resource_id: '41',
  request_id: 'request-audit-88',
  correlation_id: 'correlation-audit-88',
  payload: {
    before: { status: 3, title: '审核前' },
    after: { status: 0, title: '审核后' },
    source: 'admin-console',
  },
  ip_hash: 'must-not-render',
  created_at: '2026-08-23 10:30:00',
}

test('delivery detail keeps payload private and retries without a manual reason', async ({ page }) => {
  let status = 'dead'
  let retried = false
  await page.route('**/api/admin/operations/deliveries/77/retry', async (route) => {
    expect(route.request().postData()).toBeFalsy()
    retried = true
    status = 'pending'
    await route.fulfill({ status: 204, body: '' })
  })
  await page.route('**/api/admin/operations/deliveries/77', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: deliveryFixture(status) }),
    })
  })

  await page.goto('./apps/operations/deliveries/77', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: '投递 #77', exact: true, level: 1 })).toBeVisible()
  await expect(page.getByRole('listbox', { name: '投递摘要' }).getByText('死信', { exact: true })).toBeVisible()
  await expect(page.getByText('recipient projection unavailable')).toBeVisible()
  await expect(page.locator('body')).not.toContainText('this value must never be rendered')

  await page.getByRole('button', { name: '重试投递', exact: true }).click()
  const confirm = page.getByTestId('danger-confirm')
  await expect(confirm).toBeEnabled()
  await confirm.click()

  await expect.poll(() => retried).toBe(true)
  await expect(page.getByRole('listbox', { name: '投递摘要' }).getByText('待处理', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: '重试投递', exact: true })).toHaveCount(0)
})

test('audit detail presents snapshots, trace and no manual reason or contract-external fields', async ({ page }) => {
  await page.route('**/api/admin/audit-logs/88', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: auditFixture }),
    })
  })

  await page.goto('./apps/operations/audit/88', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: '审计记录 #88', exact: true, level: 1 })).toBeVisible()
  await expect(page.getByRole('heading', { name: '操作原因', exact: true })).toHaveCount(0)
  await expect(page.getByText(/"title": "审核前"/)).toBeVisible()
  await expect(page.getByText(/"title": "审核后"/)).toBeVisible()
  await expect(page.getByText('request-audit-88', { exact: true })).toBeVisible()
  await expect(page.getByText('correlation-audit-88', { exact: true })).toBeVisible()
  await expect(page.getByText(/"source": "admin-console"/)).toBeVisible()
  await expect(page.locator('body')).not.toContainText('must-not-render')
})

test('operations lists link each record to its canonical detail page', async ({ page }) => {
  await page.route('**/api/admin/operations/notifications', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { total: 1, unread: 0, recipients: 1, types: [] } }),
    })
  })
  await page.route('**/api/admin/operations/deliveries?**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: [deliveryFixture()],
        pagination: { currentPage: 1, totalPages: 1, totalItems: 1, itemsPerPage: 20 },
      }),
    })
  })
  await page.route('**/api/admin/audit-logs?**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: [auditFixture],
        pagination: { currentPage: 1, totalPages: 1, totalItems: 1, itemsPerPage: 30 },
      }),
    })
  })

  await page.goto('./apps/operations/deliveries', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('link', { name: /content\.card_liked/ })).toHaveAttribute(
    'href',
    '/admin/apps/operations/deliveries/77',
  )

  await page.goto('./apps/operations/audit', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('link', { name: '查看审计记录 #88' })).toHaveAttribute(
    'href',
    '/admin/apps/operations/audit/88',
  )
})
