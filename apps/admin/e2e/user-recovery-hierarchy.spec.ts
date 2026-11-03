import { expect, test, type Page } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

async function fixtures(page: Page, limited = false) {
  await page.addInitScript(() => localStorage.setItem('token', 'layout-fixture'))
  const unexpected: string[] = []
  await page.route('**/api/**', async route => {
    // 开发服务的模块路径也可能包含 /api/；只拦截真正的后端请求。
    if (!new URL(route.request().url()).pathname.startsWith('/api/')) return route.continue()
    if (route.request().method() !== 'GET') {
      unexpected.push(route.request().method())
      return route.abort()
    }
    const url = new URL(route.request().url())
    const grants = [
      { capability: 'admin.access', scope: 'global' },
      { capability: 'users.read', scope: 'all' },
      { capability: 'users.recovery.read', scope: 'global' },
      { capability: 'roles.read', scope: 'global' },
      ...(!limited ? [
        { capability: 'users.update', scope: 'all' },
        { capability: 'users.delete', scope: 'all' },
        { capability: 'users.security.read', scope: 'global' },
      ] : []),
    ]
    const users = [0, 1, 3].map((status, index) => ({
      id: 101 + index, username: ['林晓', '陈言', '周宁'][index], number: `member_${index + 1}`,
      avatar: null, status, roles_id: [2], roles: [{ id: 2, name: '普通用户', slug: 'user' }],
      created_at: '2026-09-18 09:20:00',
    }))
    const records = ['active', 'consumed', 'expired'].map((state, index) => ({
      id: 201 + index, user_id: 101 + index, purpose: 'password_recovery', state, attempts: index,
      created_at: '2026-09-18 09:20:00', expires_at: '2026-09-18 09:35:00',
    }))
    let data: unknown = []
    if (url.pathname === '/api/me') data = { id: 9001, username: '布局测试管理员', roles: [], grants, status: 0 }
    if (url.pathname === '/api/admin/roles') return route.fulfill({ json: {
      success: true, data: [{ id: 2, name: '普通用户', slug: 'user' }],
      pagination: { totalItems: 1, currentPage: 1, totalPages: 1, itemsPerPage: 100 },
    } })
    if (url.pathname === '/api/admin/users' || url.pathname === '/api/admin/recovery') {
      const currentPage = Number(url.searchParams.get('page') || 1)
      data = currentPage > 1 ? [] : url.pathname.endsWith('recovery') ? records : users
      return route.fulfill({ json: { success: true, data, pagination: { totalItems: 3, currentPage, totalPages: 1, itemsPerPage: 20 } } })
    }
    await route.fulfill({ json: { success: true, data } })
  })
  return unexpected
}

for (const width of [1440, 390]) {
  test(`user identity and secondary actions at ${width}px`, async ({ page }, testInfo) => {
    const unexpected = await fixtures(page)
    await page.setViewportSize({ width, height: 900 })
    await page.goto('./apps/users')
    const row = page.locator('tbody tr').first()
    await expect(row).toContainText('member_1', { timeout: 20_000 })
    await page.screenshot({ path: testInfo.outputPath('users.png'), fullPage: true })
    await row.getByRole('button', { name: '更多用户操作' }).click()
    await expect(page.getByText('删除用户', { exact: true })).toBeVisible()
    await expect(page.getByText('查看账号安全', { exact: true })).toBeVisible()
    expect(unexpected).toEqual([])
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
  })

  test(`recovery record hierarchy at ${width}px`, async ({ page }, testInfo) => {
    await fixtures(page)
    await page.setViewportSize({ width, height: 900 })
    await page.goto('./apps/security/recovery')
    await expect(page.getByRole('heading', { name: '账号恢复记录', exact: true })).toBeVisible()
    await expect(page.locator('tbody tr').first()).toContainText('密码恢复')
    await page.screenshot({ path: testInfo.outputPath('recovery.png'), fullPage: true })
    await expect(page.locator('tbody tr').first().getByRole('link', { name: '查看账号安全' })).toHaveAttribute('href', '/admin/apps/users/101/security')
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
  })
}

test('limited roles do not see security or mutation actions', async ({ page }) => {
  await fixtures(page, true)
  await page.goto('./apps/users')
  await expect(page.locator('tbody tr').first().getByRole('link', { name: '查看用户' })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('button', { name: '更多用户操作' })).toHaveCount(0)
  await page.goto('./apps/security/recovery')
  await expect(page.locator('tbody tr').first()).toContainText('密码恢复')
  await expect(page.getByRole('link', { name: '查看账号安全' })).toHaveCount(0)
})

test('empty recovery page retains a path back to records', async ({ page }) => {
  await fixtures(page)
  await page.goto('./apps/security/recovery?page=9&list_rows=20')
  await page.getByRole('button', { name: '返回第一页' }).click()
  await expect(page.locator('tbody tr').first()).toContainText('密码恢复')
  await expect(page).toHaveURL(/page=1(?:&|$)/)
})

test('user filters survive sorting changes and clear without losing normal status zero', async ({ page }) => {
  await fixtures(page)
  await page.goto('./apps/users?filters=' + encodeURIComponent(JSON.stringify({ status: 0, role_id: 2 })))
  await expect(page.locator('tbody tr').first()).toContainText('member_1', { timeout: 20_000 })
  const sorted = page.waitForRequest(request => request.url().includes('/api/admin/users?') && new URL(request.url()).searchParams.get('order_key') === 'created_at')
  await page.locator('thead').getByText('注册时间', { exact: true }).click()
  const sortedParams = new URL((await sorted).url()).searchParams
  expect(sortedParams.get('status')).toBe('0')
  expect(sortedParams.get('role_id')).toBe('2')
  await page.getByRole('button', { name: '搜索与排序', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '搜索与排序' })
  await expect(dialog).toContainText('注册时间')
  await dialog.getByRole('button', { name: '重置', exact: true }).click()
  const clearedSort = page.waitForRequest(request => request.url().includes('/api/admin/users?') && !new URL(request.url()).searchParams.has('order_key'))
  await dialog.getByRole('button', { name: '应用筛选', exact: true }).click()
  expect(new URL((await clearedSort).url()).searchParams.get('status')).toBe('0')
  await expect(page).not.toHaveURL(/order_key=/)
  await page.reload()
  await expect(page.locator('tbody tr').first()).toContainText('member_1')
  const cleared = page.waitForRequest(request => request.url().includes('/api/admin/users?') && !new URL(request.url()).searchParams.has('status') && !new URL(request.url()).searchParams.has('role_id'))
  await page.getByRole('button', { name: '清除筛选', exact: true }).click()
  await cleared
})

test('recovery status is sent to the server and retained in the URL', async ({ page }) => {
  await fixtures(page)
  await page.goto('./apps/security/recovery?status=expired')
  await expect(page.locator('tbody tr').first()).toContainText('密码恢复')
  await page.getByRole('combobox').filter({ hasText: '恢复状态' }).click()
  const request = page.waitForRequest(request => request.url().includes('/api/admin/recovery?') && new URL(request.url()).searchParams.get('status') === 'active')
  await page.getByRole('option', { name: '待验证', exact: true }).click()
  await request
  await expect(page).toHaveURL(/status=active/)
})

test('user status and role controls send both filters', async ({ page }) => {
  await fixtures(page)
  await page.goto('./apps/users')
  await expect(page.locator('tbody tr').first()).toContainText('member_1')
  await page.getByRole('combobox').filter({ hasText: '账号状态' }).click()
  const statusRequest = page.waitForRequest(request => request.url().includes('/api/admin/users?') && new URL(request.url()).searchParams.get('status') === '0')
  await page.getByRole('option', { name: '#0 正常', exact: true }).click()
  await statusRequest
  await page.getByRole('combobox').filter({ hasText: '角色' }).click()
  const roleRequest = page.waitForRequest(request => request.url().includes('/api/admin/users?') && new URL(request.url()).searchParams.get('role_id') === '2')
  await page.getByRole('option', { name: '普通用户', exact: true }).click()
  expect(new URL((await roleRequest).url()).searchParams.get('status')).toBe('0')
})

test('user deletion confirmation identifies the account and session impact without mutating', async ({ page }) => {
  const unexpected = await fixtures(page)
  await page.goto('./apps/users')
  await page.locator('tbody tr').first().getByRole('button', { name: '更多用户操作' }).click()
  await page.getByText('删除用户', { exact: true }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toContainText('林晓 · 账号 member_1')
  await expect(dialog).toContainText('所有设备的登录会话会被撤销')
  await dialog.getByRole('button', { name: '取消', exact: true }).click()
  expect(unexpected).toEqual([])
})
