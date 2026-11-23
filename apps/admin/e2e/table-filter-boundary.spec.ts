import { expect, test, type Page, type Browser } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

async function mockContext(browser: Browser) {
  return browser.newContext({
    baseURL: `${adminOrigin}/admin/`,
    storageState: { cookies: [], origins: [{ origin: adminOrigin, localStorage: [{ name: 'token', value: 'filter-boundary-fixture' }] }] },
  })
}

const cases = [
  { route: 'users', path: '/api/admin/users', capability: 'users.read', field: 'username' },
  { route: 'access/roles', path: '/api/admin/roles', capability: 'roles.read', field: 'name' },
  { route: 'content/tags', path: '/api/admin/tags', capability: 'tags.read', field: 'name' },
  { route: 'settings/storage/files', path: '/api/admin/files', capability: 'files.read', field: 'original_name' },
] as const

async function mockAdmin(page: Page, capability: string, path: string) {
  const requests: URL[] = []
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    if (!url.pathname.startsWith('/api/')) return route.continue()
    if (route.request().method() !== 'GET') return route.abort()
    if (url.pathname === '/api/me') {
      return route.fulfill({ json: { success: true, data: {
        id: 9001, username: '筛选测试管理员', roles: [], status: 0,
        grants: [
          { capability: 'admin.access', scope: 'global' },
          { capability, scope: capability === 'roles.read' ? 'global' : 'all' },
          { capability: 'roles.read', scope: 'global' },
        ],
      } } })
    }
    if (url.pathname === path) requests.push(url)
    return route.fulfill({ json: { success: true, data: [], pagination: {
      totalItems: 0, currentPage: 1, totalPages: 1, itemsPerPage: 20,
    } } })
  })
  return requests
}

for (const entry of cases) {
  test(`${entry.route} ignores unknown URL filters and preserves mandatory list params`, async ({ browser }) => {
    const context = await mockContext(browser)
    const page = await context.newPage()
    const requests = await mockAdmin(page, entry.capability, entry.path)
    const filters = JSON.stringify({
      page: 99, list_rows: 100, show_deleted: 0, extra: 'unrecognized',
      search_keys: [entry.field, 'unknown_field'],
      order_key: 'unknown_field', order_desc: 'yes', status: 999, role_id: -3,
    })
    const query = new URLSearchParams({ filters, page: '1', list_rows: '20', ...(entry.route === 'settings/storage/files' ? { view: 'deleted' } : {}) })
    await page.goto(`./apps/${entry.route}?${query}`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('main')).toBeVisible({ timeout: 30_000 })
    await expect.poll(() => requests.length).toBeGreaterThan(0)
    const params = requests[0]!.searchParams
    expect(params.get('page')).toBe('1')
    expect(params.get('list_rows')).toBe('20')
    expect(JSON.parse(params.get('search_keys') ?? '[]')).toEqual([entry.field])
    for (const key of ['extra', 'unknown_field', 'status', 'role_id', 'order_key']) {
      expect(params.has(key)).toBe(false)
    }
    if (entry.route === 'settings/storage/files') expect(params.get('show_deleted')).toBe('2')
    else expect(params.has('show_deleted')).toBe(false)
    await context.close()
  })
}

test('user saved view discards invalid status before requesting data', async ({ browser }) => {
  const context = await mockContext(browser)
  const page = await context.newPage()
  await page.addInitScript(() => {
    localStorage.setItem('lovecards.admin.table-view.9001.users', JSON.stringify({
      columns: ['username', 'roles_id', 'created_at', 'status', 'operate'],
      views: [{ id: 'old-view', name: '旧筛选', columns: ['username', 'operate'], query: {
        filters: JSON.stringify({ status: 999, role_id: -3, page: 99, search_keys: ['username'] }),
        list_rows: '20',
      } }],
    }))
  })
  const requests = await mockAdmin(page, 'users.read', '/api/admin/users')
  await page.goto('./apps/users', { waitUntil: 'domcontentloaded' })
  await expect(page.locator('main')).toBeVisible({ timeout: 30_000 })
  await expect.poll(() => requests.length).toBeGreaterThan(0)
  const before = requests.length
  await page.getByRole('button', { name: /视图设置/ }).click()
  await page.getByText('旧筛选', { exact: true }).click()
  await expect.poll(() => requests.length).toBeGreaterThan(before)
  const params = requests.at(-1)!.searchParams
  expect(params.has('status')).toBe(false)
  expect(params.has('role_id')).toBe(false)
  expect(params.get('page')).toBe('1')
  expect(params.get('list_rows')).toBe('20')
  await context.close()
})
