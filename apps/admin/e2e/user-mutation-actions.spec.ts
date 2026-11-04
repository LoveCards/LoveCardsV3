import { expect, test } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

// 用户删除与 Session 撤销的写路径证据（审计 §5），并锁定 D-1 行为：
// 危险确认弹窗不再包含任何文字输入框，data-testid="danger-confirm" 直接可用。
test.use({
  storageState: {
    cookies: [],
    origins: [{
      origin: adminOrigin,
      localStorage: [{ name: 'token', value: 'user-mutation-mock-token' }],
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
          id: 6,
          number: '1000000006',
          username: '用户治理测试员',
          email: 'mutation@example.test',
          phone: '',
          avatar: '',
          roles_id: [],
          roles: [],
          capabilities: [],
          grants: [
            { capability: 'admin.access', scope: 'global' },
            { capability: 'users.read', scope: 'all' },
            { capability: 'users.update', scope: 'all' },
            { capability: 'users.delete', scope: 'all' },
            { capability: 'users.security.read', scope: 'global' },
            { capability: 'users.sessions.revoke', scope: 'global' },
          ],
          status: 0,
          stats: { received_likes: 0 },
        },
      }),
    })
  })
  await page.route('**/api/admin/roles**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: { data: [{ id: 5, slug: 'editor', name: '编辑', description: '', status: 0, created_at: '2026-01-01 00:00:00', updated_at: '2026-01-01 00:00:00' }], pagination: { total: 1, page: 1, list_rows: 100 } },
      }),
    })
  })
})

const userRow = {
  id: 7,
  number: '1000000007',
  username: '普通用户七',
  email: 'seven@example.test',
  phone: '',
  avatar: '',
  roles_id: [],
  roles: [],
  status: 0,
  stats: { received_likes: 0 },
  created_at: '2026-10-01 00:00:00',
  updated_at: '2026-10-01 00:00:00',
}

test('deleting a user confirms without typed input and removes the row after the server delete', async ({ page }) => {
  test.slow() // 冷编译首屏兜底
  let deleted = false
  await page.route('**/api/admin/users/7', async (route) => {
    if (route.request().method() !== 'DELETE') return route.fallback()
    deleted = true
    await route.fulfill({ status: 204, body: '' })
  })
  await page.route('**/api/admin/users**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: { data: deleted ? [] : [userRow], pagination: { total: deleted ? 0 : 1, page: 1, list_rows: 10 } },
      }),
    })
  })

  await page.goto('./apps/users', { waitUntil: 'domcontentloaded' })
  // vite dev 冷缓存下用户页首屏编译可能超过 10 秒，首个断言给足窗口（后续页面模块已热）。
  await expect(page.getByRole('row').filter({ hasText: '普通用户七' })).toBeVisible({ timeout: 25_000 })

  await page.getByRole('row').filter({ hasText: '普通用户七' }).getByRole('button', { name: '更多用户操作' }).click()
  // Vuetify v-list-item 在 menu 内的 role 不稳定，按文案定位菜单项。
  await page.getByText('删除用户', { exact: true }).click()

  const dialog = page.getByRole('dialog').filter({ hasText: '确认删除' })
  await expect(dialog.getByText('账号记录为软删除，当前用户列表不提供恢复入口。')).toBeVisible()
  // D-1 回归锁：确认弹窗内不得出现任何文字输入框。
  await expect(dialog.getByRole('textbox')).toHaveCount(0)
  const confirm = page.getByTestId('danger-confirm')
  await expect(confirm).toBeEnabled()
  await confirm.click()

  await expect.poll(() => deleted).toBe(true)
  await expect(page.getByRole('row').filter({ hasText: '普通用户七' })).toHaveCount(0)
})

test('revoking one session confirms without typed input and marks the session closed', async ({ page }) => {
  test.slow() // 冷编译首屏兜底
  let revokedId: string | null = null
  const session = (id: number, active: boolean) => ({
    id,
    user_id: 7,
    token_expires_at: '2026-11-11 00:00:00',
    renewable_until: '2026-12-11 00:00:00',
    security_version: 1,
    revoked_at: active ? null : '2026-11-04 10:00:00',
    created_at: '2026-11-01 00:00:00',
    active,
  })
  await page.route('**/api/admin/users/7/sessions/31/revoke', async (route) => {
    revokedId = '31'
    await route.fulfill({ status: 204, body: '' })
  })
  await page.route('**/api/admin/users/7/sessions', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: [session(31, revokedId === null), session(32, false)],
      }),
    })
  })
  await page.route('**/api/admin/users/7/security', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          user: { id: 7, number: '1000000007', username: '普通用户七', email: 'seven@example.test', phone: '', status: 0, security_version: 1, created_at: '2026-10-01 00:00:00', updated_at: '2026-10-01 00:00:00' },
          sessions: { active: revokedId === null ? 1 : 0, total: 2 },
          session_mutation_allowed: true,
        },
      }),
    })
  })

  await page.goto('./apps/users/7/security', { waitUntil: 'domcontentloaded' })
  // 数据行只含会话 ID 与状态文案，'Session' 字样只在表头，不能用作行过滤条件。
  const row = page.getByRole('row').filter({ hasText: '31' })
  await expect(row.getByText('有效', { exact: true })).toBeVisible({ timeout: 25_000 })

  await row.getByRole('button', { name: '撤销 Session' }).click()
  const dialog = page.getByRole('dialog').filter({ hasText: '撤销 Session' })
  await expect(dialog.getByText('该设备的登录态将立即失效，用户需要重新登录。')).toBeVisible()
  // D-1 回归锁：撤销确认同样不得要求打字。
  await expect(dialog.getByRole('textbox')).toHaveCount(0)
  await page.getByTestId('danger-confirm').click()

  await expect.poll(() => revokedId).toBe('31')
  await expect(row.getByText('已撤销或过期', { exact: true })).toBeVisible()
})
