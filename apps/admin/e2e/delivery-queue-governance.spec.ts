import { expect, test, type Page } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'
const json = (data: unknown) => ({ contentType: 'application/json', body: JSON.stringify({ success: true, data }) })

async function mockReader(page: Page, capability = 'communications.read') {
  await page.route('**/api/me', route => route.fulfill(json({
    id: 2, number: '1000000002', username: '运维测试员', email: 'operations@example.test',
    phone: '', avatar: '', roles_id: [], roles: [], capabilities: [], status: 0,
    grants: [
      { capability: 'admin.access', scope: 'global' },
      { capability, scope: 'global' },
    ],
    stats: { received_likes: 0 },
  })))
}

test.use({
  storageState: {
    cookies: [],
    origins: [{ origin: adminOrigin, localStorage: [{ name: 'token', value: 'delivery-governance-mock-token' }] }],
  },
})

test('legacy skipped URL keeps notifications and all queues removes queue_name', async ({ page }) => {
  await mockReader(page)
  const requests: URLSearchParams[] = []
  await page.route('**/api/admin/operations/deliveries/summary', route => route.fulfill(json({
    pending: 0, processing: 0, dead: 0, skipped: 0, checked_at: '2026-11-17 10:00:00',
  })))
  await page.route('**/api/admin/operations/deliveries?**', route => {
    requests.push(new URL(route.request().url()).searchParams)
    return route.fulfill({ json: {
      success: true, data: [],
      pagination: { currentPage: 1, totalPages: 0, totalItems: 0, itemsPerPage: 20 },
    } })
  })

  await page.goto('./apps/operations/deliveries?view=skipped&page=3&list_rows=20')
  await expect.poll(() => requests.length).toBeGreaterThan(0)
  expect(requests[0]?.get('queue_name')).toBe('notifications')
  expect(requests[0]?.get('status')).toBe('completed')
  expect(requests[0]?.get('policy_decision')).toBe('skipped')
  expect(requests[0]?.get('page')).toBe('3')

  await page.getByRole('combobox').first().click()
  await page.getByRole('option', { name: '全部队列' }).click()
  await expect.poll(() => requests.some(query => !query.has('queue_name') && query.get('page') === '1')).toBe(true)
  await expect(page).toHaveURL(/queue=all/)
})

test('realtime task detail does not show notification policy fields', async ({ page }) => {
  await mockReader(page)
  await page.route('**/api/admin/operations/deliveries/7', route => route.fulfill(json({
    id: 7, event_id: 5, handler_key: 'message.sent.signal', event_type: 'message.sent',
    display_name: '私信变化提示', queue_name: 'realtime', execution_mode: 'daemon_required',
    status: 'completed', policy_decision: 'skipped', policy_revision: 2,
    attempt_count: 0, last_error: null, correlation_id: null,
    created_at: '2026-11-17 10:00:00', available_at: '2026-11-17 10:00:00',
    updated_at: '2026-11-17 10:00:00',
  })))

  await page.goto('./apps/operations/deliveries/7')
  await expect(page.getByRole('heading', { name: '处理任务 #7' })).toBeVisible()
  await expect(page.getByRole('link', { name: '查看任务审计' })).toHaveCount(0)
  await page.getByRole('button', { name: '技术信息' }).click()
  await expect(page.locator('dd').filter({ hasText: '实时提示' })).toBeVisible()
  await expect(page.getByText('已跳过实时提示')).toBeVisible()
  await expect(page.getByText('已按策略跳过通知')).toHaveCount(0)
  await expect(page.getByText('通知策略版本')).toHaveCount(0)
  await expect(page.getByRole('link', { name: '返回处理任务' })).toHaveAttribute('href', '/admin/apps/operations/deliveries')
})

test('runtime-only reader cannot open a delivery bookmark', async ({ page }) => {
  await mockReader(page, 'operations.read')
  const requests: string[] = []
  await page.route('**/api/admin/operations/deliveries/7', route => {
    requests.push(route.request().url())
    return route.fulfill(json([]))
  })
  await page.goto('./apps/operations/deliveries/7')
  await expect(page).toHaveURL(/client\/error/)
  expect(requests).toEqual([])
})
