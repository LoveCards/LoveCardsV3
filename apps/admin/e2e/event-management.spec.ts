import { expect, test, type Page } from '@playwright/test'

type Grant = { capability: string; scope: 'global' }

async function mockAdministrator(page: Page, grants: Grant[]) {
  await page.route('**/api/me', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: {
      id: 2, number: '1000000002', username: '事件测试员', email: 'events@example.test',
      phone: '', avatar: '', roles_id: [], roles: [], capabilities: grants.map(grant => grant.capability),
      grants: [{ capability: 'admin.access', scope: 'global' }, ...grants], status: 0,
      stats: { received_likes: 0 },
    },
  }) }))
}

async function mockRuntime(page: Page) {
  await page.route('**/api/admin/operations/runtime', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: {
      facilities: [], workers: [], eventing: { pending: 0, processing: 0, dead: 0, expired_processing: 0, queues: [] },
      event_processing: { mode: 'scheduled', state: 'active', target_mode: null, scheduled_trigger: 'cli', revision: 1, status: 'ready', reason_code: null, note: null },
      communication: { desired_mode: 'polling', effective_mode: 'polling', status: 'ready', revision: 1, reason_code: null },
    },
  }) }))
}

test('runtime reader cannot open event records without communications grant', async ({ page }) => {
  await mockAdministrator(page, [{ capability: 'operations.read', scope: 'global' }])
  await mockRuntime(page)
  await page.goto('./apps/operations/eventing', { waitUntil: 'domcontentloaded' })

  await expect(page.getByRole('tab', { name: '运行状态' })).toBeVisible()
  await expect(page.getByRole('heading', { name: '事件队列' })).toBeVisible()
  await expect(page.getByRole('button', { name: '查看事件明细' })).toHaveCount(0)
  await expect(page.getByRole('tab', { name: '事件明细' })).toHaveCount(0)
})

test('event records include facts without deliveries and follow browser history', async ({ page }) => {
  await mockAdministrator(page, [
    { capability: 'operations.read', scope: 'global' },
    { capability: 'communications.read', scope: 'global' },
  ])
  await mockRuntime(page)
  await page.route('**/api/admin/operations/events?**', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: [{
      id: 7, event_type: 'message.sent', event_version: 1, aggregate_type: 'message', aggregate_key: '19',
      occurred_at: '2026-11-16 12:00:00', created_at: '2026-11-16 12:00:00', deliveries: [],
    }],
    pagination: { currentPage: 1, totalPages: 1, totalItems: 1, itemsPerPage: 20 },
  }) }))

  await page.goto('./apps/operations/eventing?tab=status', { waitUntil: 'domcontentloaded' })
  await page.getByRole('tab', { name: '事件明细' }).click()
  await expect(page).toHaveURL(/tab=events/)
  await page.getByRole('button', { name: /message\.sent@1.*#7/ }).click()
  await expect(page.getByText('这条事实没有注册处理器，因此没有投递任务。')).toBeVisible()

  await page.goBack()
  await expect(page.getByRole('tab', { name: '运行状态' })).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('button', { name: /message\.sent@1.*#7/ })).toHaveCount(0)
})
