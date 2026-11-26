import { expect as baseExpect, test, type Page } from '@playwright/test'

const expect = baseExpect.configure({ timeout: 15_000 })
test.use({ storageState: { cookies: [], origins: [] } })
test.setTimeout(60_000)

async function notificationFixtures(page: Page, grant = 'notifications.read') {
  const state = { failList: false, failDetail: false, listCalls: [] as string[], detailCalls: 0, writes: [] as string[], errors: [] as string[] }
  await page.addInitScript(() => { localStorage.setItem('token', 'notification-readonly-fixture') })
  page.on('pageerror', error => state.errors.push(error.message))
  const record = { id: 71, type: 'content.commented', type_label: '收到评论', recipient_id: 48, actor_id: 52, resource_type: 'card', resource_id: '38472', read_at: null, created_at: '2026-11-26 12:00:00' }
  await page.route('**/api/**', async route => {
    const request = route.request()
    const url = new URL(request.url())
    if (!url.pathname.startsWith('/api/')) return route.continue()
    if (request.method() !== 'GET') { state.writes.push(`${request.method()} ${url.pathname}`); return route.abort() }
    if (url.pathname === '/api/me') return route.fulfill({ json: { success: true, data: { id: 9001, username: '通知查询员', roles: [], status: 0, grants: [{ capability: 'admin.access', scope: 'global' }, { capability: grant, scope: 'global' }] } } })
    if (url.pathname === '/api/admin/notifications') {
      state.listCalls.push(url.search)
      if (state.failList) return route.fulfill({ status: 503, json: { success: false, message: '通知查询暂不可用' } })
      const empty = url.searchParams.get('recipient_id') === '999'
      return route.fulfill({ json: { success: true, data: empty ? [] : [record], pagination: { totalItems: empty ? 0 : 1, currentPage: 1, totalPages: 1, itemsPerPage: 20 } } })
    }
    if (url.pathname === '/api/admin/notifications/71') {
      state.detailCalls++
      return route.fulfill({ status: state.failDetail ? 404 : 200, json: state.failDetail ? { success: false, message: '通知记录不存在' } : { success: true, data: record } })
    }
    return route.fulfill({ json: { success: true, data: [] } })
  })
  return state
}

test('notification list opens shared readonly detail without changing query or read state', async ({ page }, info) => {
  const state = await notificationFixtures(page)
  await page.goto('./apps/operations/notifications?recipient_id=48&read_state=unread')
  const row = page.locator('tbody tr').first()
  await expect(row).toContainText('用户 #48')
  await expect(row).toContainText('未读')
  const address = page.url()
  const view = row.getByRole('button', { name: '查看', exact: true })
  await view.click()
  const dialog = page.getByRole('dialog', { name: '查看站内通知' })
  await expect(dialog).toBeVisible()
  await expect(dialog).toContainText('content.commented')
  await expect(dialog).toContainText('当前记录未保存直接来源关联')
  await expect(dialog.getByRole('button', { name: /编辑|保存|删除|标记/ })).toHaveCount(0)
  await expect(dialog.getByRole('link', { name: /用户 #48/ })).toHaveCount(0)
  await page.screenshot({ path: info.outputPath('notification-dialog.png'), fullPage: true, animations: 'disabled' })
  await page.keyboard.press('Escape')
  await expect(dialog).not.toBeVisible()
  await expect(view).toBeFocused()
  expect(page.url()).toBe(address)
  expect(state.detailCalls).toBe(1)
  expect(state.writes).toEqual([])
  expect(state.errors).toEqual([])
})

test('notification filters apply together and reset without rewriting column preferences', async ({ page }) => {
  const state = await notificationFixtures(page)
  await page.goto('./apps/operations/notifications')
  await expect(page.locator('tbody tr')).toContainText('收到评论')
  const initialCount = state.listCalls.length
  await page.getByLabel('接收用户 ID', { exact: true }).fill('999')
  await page.getByLabel('生成开始时间', { exact: true }).fill('2026-11-25T08:00')
  expect(state.listCalls.length).toBe(initialCount)
  await page.getByRole('button', { name: '查询', exact: true }).click()
  await expect(page).toHaveURL(/recipient_id=999/)
  await expect(page.getByText('没有匹配的通知记录', { exact: true })).toBeVisible()
  expect(state.listCalls.at(-1)).toContain('from=2026-11-25')
  await page.getByRole('button', { name: '清除筛选', exact: true }).click()
  await expect(page.locator('tbody tr')).toContainText('用户 #48')
  expect(new URL(page.url()).searchParams.has('recipient_id')).toBe(false)
  await page.getByLabel('接收用户 ID', { exact: true }).fill('-2')
  await page.getByRole('button', { name: '查询', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('必须是正整数')
})

test('notification query keeps stale rows on refresh failure and retries', async ({ page }) => {
  const state = await notificationFixtures(page)
  await page.goto('./apps/operations/notifications')
  await expect(page.locator('tbody tr')).toContainText('收到评论')
  state.failList = true
  await page.getByRole('button', { name: '刷新', exact: true }).click()
  await expect(page.getByText('上次结果共 1 条')).toBeVisible()
  await expect(page.locator('tbody tr')).toContainText('用户 #48')
  state.failList = false
  await page.getByRole('button', { name: '重试', exact: true }).click()
  await expect(page.getByText('共 1 条', { exact: true })).toBeVisible()
  expect(state.writes).toEqual([])
})

test('notification direct route uses same details and handles missing records', async ({ page }) => {
  const state = await notificationFixtures(page)
  await page.goto('./apps/operations/notifications/71')
  await expect(page.getByRole('heading', { level: 1, name: '通知 #71' })).toBeVisible()
  await expect(page.getByText('content.commented', { exact: true })).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  state.failDetail = true
  await page.reload()
  await expect(page.locator('main')).toContainText('通知记录不存在')
  expect(state.writes).toEqual([])
})

test('communications metadata grant cannot reveal notification records', async ({ page }) => {
  const state = await notificationFixtures(page, 'communications.read')
  await page.goto('./apps/operations/notifications')
  await expect(page).toHaveURL(/\/client\/error/)
  expect(state.listCalls).toEqual([])
  expect(state.detailCalls).toBe(0)
})

for (const [width, theme] of [[1440, 'light'], [960, 'light'], [720, 'light'], [1440, 'dark'], [390, 'dark'], [320, 'dark']] as const) {
  test(`notification surfaces reflow with ${theme} theme at ${width}px`, async ({ page }, info) => {
    await notificationFixtures(page)
    await page.addInitScript(value => localStorage.setItem('lovecards.admin.theme', value), theme)
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize({ width, height: 960 })
    await page.goto('./apps/operations/notifications')
    await expect(page.locator('tbody tr')).toContainText('收到评论')
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
    await page.screenshot({ path: info.outputPath('notifications-list.png'), fullPage: true, animations: 'disabled' })
    await page.getByRole('button', { name: '查看', exact: true }).first().click()
    await expect(page.getByRole('dialog')).toContainText('接收用户')
    expect(await page.getByRole('dialog').evaluate(element => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1)
    await page.screenshot({ path: info.outputPath('notifications-detail.png'), fullPage: true, animations: 'disabled' })
  })
}
