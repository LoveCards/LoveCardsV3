import { expect, test, type Page } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })
test.setTimeout(60_000)

async function fixture(page: Page, options: { limited?: boolean; noDetails?: boolean; failBusiness?: boolean; failRuntime?: boolean; mode?: string } = {}) {
  const state = { businessFails: Boolean(options.failBusiness), runtimeFails: Boolean(options.failRuntime), dashboardCalls: 0, runtimeCalls: 0 }
  const grants = [
    { capability: 'admin.access', scope: 'global' }, { capability: 'dashboard.read', scope: 'global' },
    ...(!options.limited ? [{ capability: 'operations.read', scope: 'global' }, ...(!options.noDetails ? [{ capability: 'communications.read', scope: 'global' }] : []), { capability: 'cards.read', scope: 'all' }, { capability: 'cards.approve', scope: 'all' }] : [{ capability: 'comments.read', scope: 'all' }]),
  ]
  const dashboard = {
    count: options.limited ? { comments: 27 } : { cards: 12684, comments: 38920, good: 86430 },
    chart: (options.limited ? ['评论'] : ['卡片', '评论', '点赞']).map(label => ({ label, data: { x: ['2026-11-21', '2026-11-22', '2026-11-23', '2026-11-24', '2026-11-25', '昨日'], y: [65, 88, 101, 92, 145, 130] } })),
    notice: [], ver: {}, workbench: { tasks: options.limited ? [] : [{ key: 'cards_pending', label: '待审核卡片', count: 18, action_url: '/apps/content/cards?filters=%7B%22status%22%3A3%7D', severity: 'warning' }, { key: 'cards_zero', label: '零待办入口', count: 0, action_url: '/apps/content/cards', severity: 'info' }] },
  }
  const runtime = { facilities: [], workers: [], eventing: { pending: 128, processing: 4, dead: 12, expired_processing: 0, oldest_pending_age_seconds: 120 }, communication: { desired_mode: 'polling', status: 'ready', checked_at: '2026-11-27T01:00:00Z', reason_code: null }, event_processing: { mode: options.mode ?? 'scheduled', state: options.mode === 'draining' ? 'draining' : 'active', target_mode: 'resident', status: options.mode === 'unavailable' ? 'degraded' : 'ready', reason_code: options.mode === 'unavailable' ? 'scheduler-trigger-unconfigured' : null } }
  const writes: string[] = []
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  await page.addInitScript(() => localStorage.setItem('token', 'dashboard-fixture'))
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (route.request().method() !== 'GET') { writes.push(path); return route.abort() }
    if (path === '/api/admin/dashboard') { state.dashboardCalls++; return route.fulfill({ status: state.businessFails ? 503 : 200, json: state.businessFails ? { success: false, message: '业务模拟失败' } : { success: true, data: dashboard } }) }
    if (path === '/api/admin/operations/runtime') { state.runtimeCalls++; return route.fulfill({ status: state.runtimeFails ? 503 : 200, json: state.runtimeFails ? { success: false, message: '运行模拟失败' } : { success: true, data: runtime } }) }
    const data = path === '/api/me' ? { id: 9001, username: '工作台验收员', roles: [], grants, status: 0 } : []
    return route.fulfill({ json: { success: true, data } })
  })
  return { state, writes, errors }
}

test('workbench switches real series, navigates tasks and targets all dead queues', async ({ page }) => {
  const { state, writes, errors } = await fixture(page)
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('./apps/dashboard')
  await expect(page.getByRole('heading', { name: '每日新增卡片', exact: true })).toBeVisible()
  await page.getByRole('button', { name: /评论总量/ }).click()
  await expect(page.getByRole('heading', { name: '每日新增评论', exact: true })).toBeVisible()
  await page.getByRole('button', { name: '查看每日数据', exact: true }).click()
  await expect(page.getByRole('table')).toContainText('145')
  await expect(page.getByTestId('workbench-tasks').getByRole('link', { name: /零待办入口/ })).toContainText('查看')
  await expect(page.getByTestId('workbench-tasks').getByRole('link', { name: /待审核卡片/ })).toHaveAttribute('href', /filters=/)
  await page.getByRole('button', { name: /处理任务失败（死信）/ }).click()
  await expect(page.getByRole('link', { name: '查看失败任务' })).toHaveAttribute('href', '/admin/apps/operations/deliveries?queue=all&view=dead')
  expect(state.dashboardCalls).toBe(1)
  expect(writes).toEqual([])
  expect(errors).toEqual([])
})

test('workbench keeps independent stale snapshots and supports retry', async ({ page }) => {
  const { state } = await fixture(page)
  await page.goto('./apps/dashboard')
  await expect(page.getByTestId('workbench-runtime')).toContainText('定时消费')
  state.businessFails = true; state.runtimeFails = true
  await page.getByRole('button', { name: '刷新', exact: true }).click()
  await expect(page.getByTestId('dashboard-error')).toContainText('保留上次数据')
  await expect(page.getByTestId('runtime-error')).toContainText('保留上次快照')
  await expect(page.getByTestId('workbench-overview')).toContainText('12,684')
  state.runtimeFails = false
  await page.getByRole('button', { name: '重试运行状态' }).click()
  await expect(page.getByTestId('runtime-error')).toHaveCount(0)
  await expect(page.getByTestId('dashboard-error')).toBeVisible()
})

test('first business failure does not hide runtime or fabricate totals', async ({ page }) => {
  await fixture(page, { failBusiness: true })
  await page.goto('./apps/dashboard')
  await expect(page.getByTestId('dashboard-error')).toBeVisible()
  await expect(page.getByTestId('workbench-runtime')).toContainText('定时消费')
  await expect(page.getByTestId('workbench-overview')).toHaveCount(0)
})

test('limited permissions hide unavailable metrics and runtime requests', async ({ page }) => {
  const { state } = await fixture(page, { limited: true })
  await page.goto('./apps/dashboard')
  await expect(page.getByRole('button', { name: /评论总量/ })).toBeVisible()
  await expect(page.getByRole('button', { name: /卡片总量/ })).toHaveCount(0)
  await expect(page.getByTestId('workbench-runtime')).toHaveCount(0)
  expect(state.runtimeCalls).toBe(0)
})

test('runtime reader sees dead summary without an unauthorized detail link', async ({ page }) => {
  await fixture(page, { noDetails: true, mode: 'draining' })
  await page.goto('./apps/dashboard')
  await expect(page.getByTestId('workbench-runtime')).toContainText('策略切换中')
  await page.getByRole('button', { name: /处理任务失败（死信）/ }).click()
  await expect(page.getByRole('link', { name: '查看失败任务' })).toHaveCount(0)
  await expect(page.getByTestId('workbench-issues')).toContainText('没有读取处理明细的权限')
})

for (const width of [1440, 640, 390, 320]) {
  test(`workbench reflows and keeps focus order at ${width}px`, async ({ page }, info) => {
    await fixture(page)
    await page.setViewportSize({ width, height: 1000 })
    await page.emulateMedia({ reducedMotion: 'reduce', colorScheme: 'dark' })
    await page.addInitScript(() => localStorage.setItem('lovecards.admin.theme', 'dark'))
    await page.goto('./apps/dashboard')
    await expect(page.getByTestId('workbench-overview')).toBeVisible()
    await expect(page.locator('canvas')).toBeVisible()
    const ids = await page.locator('.workbench-grid > section').evaluateAll(elements => elements.map(element => element.getAttribute('aria-labelledby')))
    expect(ids).toEqual(width <= 680 ? ['issues-title', 'overview-title', 'runtime-title'] : ['overview-title', 'runtime-title', 'issues-title'])
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
    await page.screenshot({ path: info.outputPath('dashboard.png'), fullPage: true, animations: 'disabled' })
  })
}
