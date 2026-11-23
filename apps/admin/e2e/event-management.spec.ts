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

test('delivery detail returns to the selected event and restores its filters', async ({ page }) => {
  await mockAdministrator(page, [{ capability: 'communications.read', scope: 'global' }])
  const delivery = {
    id: 17, event_id: 7, event_type: 'message.sent', queue_name: 'realtime', handler_key: 'message.sent.signal',
    execution_mode: 'daemon_required', status: 'completed', attempt_count: 1, available_at: '2026-11-16 12:00:00',
    created_at: '2026-11-16 12:00:00', updated_at: '2026-11-16 12:00:00',
  }
  const eventRequests: URLSearchParams[] = []
  await page.route('**/api/admin/operations/events?**', route => {
    eventRequests.push(new URL(route.request().url()).searchParams)
    return route.fulfill({ json: {
      success: true, data: [{
        id: 7, event_type: 'message.sent', event_version: 1, aggregate_type: 'message', aggregate_key: '19',
        occurred_at: '2026-11-16 12:00:00', created_at: '2026-11-16 12:00:00', deliveries: [delivery],
      }],
      pagination: { currentPage: 2, totalPages: 2, totalItems: 21, itemsPerPage: 20 },
    } })
  })
  await page.route('**/api/admin/operations/deliveries/17', route => route.fulfill({ json: { success: true, data: delivery } }))

  await page.goto('./apps/operations/eventing?tab=events&queue=realtime&event_type=message.sent&event_page=2', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('link', { name: '查看处理任务' })).toBeVisible()
  const event = page.getByRole('button', { name: /message\.sent@1.*#7/ })
  await event.click()
  await page.getByRole('link', { name: /message\.sent\.signal/ }).click()
  await expect(page.getByRole('heading', { name: '处理任务 #17' })).toBeVisible()
  await page.getByRole('link', { name: '返回事件明细' }).click()
  await expect(event).toHaveAttribute('aria-expanded', 'true')
  await expect(page).toHaveURL(/queue=realtime&event_type=message.sent&event_page=2&event_id=7$/)
  expect(eventRequests.at(-1)?.get('page')).toBe('2')
  expect(eventRequests.at(-1)?.get('queue_name')).toBe('realtime')
  expect(eventRequests.at(-1)?.has('event_id')).toBe(false)
})
