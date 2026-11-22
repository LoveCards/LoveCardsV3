import { expect, test } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

test.use({
  storageState: {
    cookies: [],
    origins: [{
      origin: adminOrigin,
      localStorage: [{ name: 'token', value: 'user-unified-edit-mock-token' }],
    }],
  },
})

test('user detail saves profile and roles in one request', async ({ page }) => {
  const grants = [
    { capability: 'admin.access', scope: 'global' },
    { capability: 'users.read', scope: 'all' },
    { capability: 'users.update', scope: 'all' },
    { capability: 'users.roles.assign', scope: 'global' },
    { capability: 'roles.read', scope: 'global' },
  ]
  const user = {
    id: 7,
    number: '1000000007',
    username: '普通用户七',
    email: 'seven@example.test',
    phone: '',
    avatar: '',
    roles_id: [4],
    roles: [{ id: 4, slug: 'visitor', name: '访客' }],
    status: 0,
    created_at: '2026-10-01 00:00:00',
    updated_at: '2026-10-01 00:00:00',
  }
  const requests: Array<{ path: string; body: Record<string, unknown> }> = []
  await page.route('**/api/me', route => route.fulfill({
    json: { success: true, data: { ...user, id: 6, username: '测试管理员', grants, capabilities: [] } },
  }))
  await page.route('**/api/admin/roles**', route => route.fulfill({
    json: {
      success: true,
      data: {
        data: [
          { id: 4, slug: 'visitor', name: '访客' },
          { id: 5, slug: 'editor', name: '编辑' },
        ],
        pagination: { total: 2, totalPages: 1, page: 1, list_rows: 100 },
      },
    },
  }))
  await page.route('**/api/admin/users/7**', route => {
    if (route.request().method() === 'PATCH') {
      requests.push({ path: new URL(route.request().url()).pathname, body: route.request().postDataJSON() })
      Object.assign(user, route.request().postDataJSON())
      return route.fulfill({ status: 204, body: '' })
    }
    return route.fulfill({ json: { success: true, data: user } })
  })

  await page.goto('./apps/users/7', { waitUntil: 'domcontentloaded' })
  const editAction = page.getByRole('button', { name: '编辑', exact: true })
  await expect(editAction.locator('xpath=ancestor::*[contains(@class, "v-card-actions")]')).toBeVisible()
  const editBox = await editAction.boundingBox()
  await editAction.click()
  const saveAction = page.getByRole('button', { name: '保存修改' })
  await expect(saveAction.locator('xpath=ancestor::*[contains(@class, "v-card-actions")]')).toBeVisible()
  const saveBox = await saveAction.boundingBox()
  expect(editBox && saveBox && Math.abs(editBox.x + editBox.width - saveBox.x - saveBox.width)).toBeLessThan(2)
  await page.getByRole('textbox', { name: '用户名' }).fill('更新用户七')
  await page.locator('.v-select').filter({ hasText: '角色' }).click()
  await page.getByRole('option', { name: '编辑 #5' }).click()
  await page.getByRole('combobox', { name: '角色' }).press('Escape')
  await page.getByRole('button', { name: '保存修改' }).click()

  await expect.poll(() => requests.length).toBe(1)
  expect(requests[0]?.path).toBe('/api/admin/users/7')
  expect(requests[0]?.body).toMatchObject({ username: '更新用户七', roles_id: [4, 5] })
  await expect(page.getByRole('button', { name: '编辑', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: '调整角色' })).toHaveCount(0)

  await page.route('**/api/admin/users?**', route => route.fulfill({
    json: { success: true, data: { data: [user], pagination: { total: 1, totalPages: 1, page: 1, list_rows: 10 } } },
  }))
  await page.goto('./apps/users', { waitUntil: 'domcontentloaded' })
  await page.getByRole('row').filter({ hasText: '更新用户七' }).getByRole('button', { name: '编辑', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '用户详情' })
  await expect(dialog.getByRole('combobox', { name: '角色' })).toBeVisible()
  await expect(dialog.getByRole('button', { name: '保存修改' })).toHaveCount(1)
  await expect(dialog.getByRole('button', { name: '调整角色' })).toHaveCount(0)
})

test('profile save remains available when role catalog fails', async ({ page }) => {
  const user = {
    id: 8,
    number: '1000000008',
    username: '普通用户八',
    email: 'eight@example.test',
    phone: '',
    avatar: '',
    roles_id: [4],
    roles: [{ id: 4, slug: 'visitor', name: '访客' }],
    status: 0,
  }
  let patch: Record<string, unknown> | null = null
  await page.route('**/api/me', route => route.fulfill({
    json: {
      success: true,
      data: {
        ...user,
        id: 6,
        username: '测试管理员',
        capabilities: [],
        grants: [
          { capability: 'admin.access', scope: 'global' },
          { capability: 'users.read', scope: 'all' },
          { capability: 'users.update', scope: 'all' },
          { capability: 'users.roles.assign', scope: 'global' },
          { capability: 'roles.read', scope: 'global' },
        ],
      },
    },
  }))
  await page.route('**/api/admin/roles**', route => route.fulfill({
    status: 503,
    json: { success: false, message: '角色目录暂时不可用' },
  }))
  await page.route('**/api/admin/users/8', route => {
    if (route.request().method() === 'PATCH') {
      patch = route.request().postDataJSON()
      Object.assign(user, patch)
      return route.fulfill({ status: 204, body: '' })
    }
    return route.fulfill({ json: { success: true, data: user } })
  })

  await page.goto('./apps/users/8', { waitUntil: 'domcontentloaded' })
  await page.getByRole('button', { name: '编辑', exact: true }).click()
  await page.getByRole('textbox', { name: '用户名' }).fill('更新用户八')
  await expect(page.getByRole('combobox', { name: '角色' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: '保存修改' })).toBeEnabled()
  await page.getByRole('button', { name: '保存修改' }).click()
  await expect.poll(() => patch).toEqual({ username: '更新用户八' })
})
