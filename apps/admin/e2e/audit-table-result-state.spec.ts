import { expect, test } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

test.use({
  storageState: {
    cookies: [],
    origins: [{ origin: adminOrigin, localStorage: [{ name: 'token', value: 'audit-table-mock-token' }] }],
  },
})

test('audit table distinguishes stale results and restores the default query', async ({ page }) => {
  await page.route('**/api/me', route => route.fulfill({ json: {
    success: true,
    data: {
      id: 9001, username: '审计测试管理员', roles: [], status: 0,
      grants: [{ capability: 'admin.access', scope: 'global' }, { capability: 'audit.read', scope: 'global' }],
    },
  } }))

  let failNext = false
  await page.route('**/api/admin/audit-logs?**', route => {
    const action = new URL(route.request().url()).searchParams.get('action')
    if (action || failNext) {
      failNext = false
      return route.fulfill({ status: 500, json: { success: false, message: '读取失败' } })
    }
    return route.fulfill({ json: {
      success: true,
      data: [{
        id: 88, action: 'system.upgrade.advance.requested', actor_id: null,
        resource_type: 'system_upgrade', resource_id: 'plan-88', request_id: null,
        correlation_id: null, created_at: '2026-11-18 02:24:07.000000',
      }],
      pagination: { currentPage: 1, totalPages: 1, totalItems: 1, itemsPerPage: 30 },
    } })
  })

  await page.goto('./apps/operations/audit', { waitUntil: 'domcontentloaded' })
  const row = page.locator('.v-data-table tbody tr').filter({ hasText: 'system.upgrade.advance.requested' })
  await expect(row).toBeVisible({ timeout: 20_000 })
  await expect(row).toContainText('2026-11-18 02:24:07')
  await expect(row).not.toContainText('时间格式未知')

  await page.getByRole('button', { name: '视图设置' }).click()
  await page.getByRole('checkbox', { name: '隐藏资源列' }).click()
  await page.keyboard.press('Escape')
  await expect(page.locator('.v-data-table thead')).not.toContainText('资源')

  failNext = true
  await page.getByRole('button', { name: '刷新', exact: true }).click()
  await expect(page.getByText('上次结果共 1 条')).toBeVisible()
  await expect(row).toBeVisible()

  await page.getByRole('combobox', { name: '动作代码（前缀）' }).fill('auth.recovery.failed')
  await page.getByRole('button', { name: '查询', exact: true }).click()
  await expect(page).toHaveURL(/action=auth\.recovery\.failed/)
  await expect(row).toHaveCount(0)
  await expect(page.getByText('上次结果共 1 条')).toHaveCount(0)

  await page.getByRole('button', { name: '视图设置' }).click()
  await page.getByText('恢复默认视图', { exact: true }).click()
  await expect(page.locator('.v-data-table thead')).toContainText('资源')
  await expect(page).not.toHaveURL(/action=/)
  await expect(row).toBeVisible()
})
