import { expect, test } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

test('role detail guards switching from unsaved basics to grants', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('token', 'role-detail-scope-fixture'))
  const grants = [
    { capability: 'admin.access', scope: 'global' },
    { capability: 'roles.read', scope: 'global' },
    { capability: 'roles.update', scope: 'global' },
    { capability: 'roles.assign', scope: 'global' },
    { capability: 'permissions.read', scope: 'global' },
  ]
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    let data: unknown = []
    if (path === '/api/me') data = { id: 900, username: '角色测试员', roles: [], grants, status: 0 }
    else if (path === '/api/admin/roles/7') data = {
      id: 7, name: '内容编辑', slug: 'content_editor', description: '编辑站内内容',
      is_system: 0, status: 0, created_at: '2026-11-01 00:00:00', updated_at: '2026-11-01 00:00:00',
    }
    else if (path === '/api/admin/roles/7/grants') data = []
    else if (path === '/api/admin/permissions') data = [
      { capability: 'cards.read', scope: 'all', description: '读取卡片' },
    ]
    await route.fulfill({ json: { success: true, data } })
  })
  await page.goto('./apps/access/roles/7')
  await expect(page.getByRole('heading', { name: '角色 · 内容编辑' })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('button', { name: '编辑', exact: true }).locator('xpath=ancestor::*[contains(@class, "v-card-actions")]')).toBeVisible()
  await page.getByRole('button', { name: '编辑', exact: true }).click()
  await page.getByRole('textbox', { name: '角色名称' }).fill('尚未保存的角色名称')
  await page.getByRole('button', { name: '分配权限' }).click()
  await expect(page.getByText('当前修改尚未保存，确定放弃吗？')).toBeVisible()
  await page.getByRole('button', { name: '继续编辑' }).click()
  await expect(page.getByRole('textbox', { name: '角色名称' })).toHaveValue('尚未保存的角色名称')
  await page.getByRole('button', { name: '分配权限' }).click()
  await page.getByRole('button', { name: '放弃修改' }).click()
  await expect(page.getByRole('button', { name: '保存权限' })).toBeVisible()
})

test('role write keeps the edit scope when the detail reread fails', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('token', 'role-reread-fixture'))
  const grants = [
    { capability: 'admin.access', scope: 'global' },
    { capability: 'roles.read', scope: 'global' },
    { capability: 'roles.update', scope: 'global' },
  ]
  const role = {
    id: 7, name: '内容编辑', slug: 'content_editor', description: '编辑站内内容',
    is_system: 0, status: 0, created_at: '2026-11-01 00:00:00', updated_at: '2026-11-01 00:00:00',
  }
  let failReread = false
  await page.route('**/api/**', async route => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (path === '/api/me') return route.fulfill({ json: { success: true, data: { id: 900, username: '管理员', roles: [], grants, status: 0 } } })
    if (path === '/api/admin/roles/7' && request.method() === 'PATCH') {
      role.name = String((request.postDataJSON() as { name: string }).name)
      failReread = true
      return route.fulfill({ status: 204, body: '' })
    }
    if (path === '/api/admin/roles/7' && failReread) {
      failReread = false
      return route.fulfill({ status: 503, json: { success: false, message: '暂时无法读取角色' } })
    }
    if (path === '/api/admin/roles/7') return route.fulfill({ json: { success: true, data: role } })
    if (path === '/api/admin/roles/7/grants') return route.fulfill({ json: { success: true, data: [] } })
    return route.fulfill({ json: { success: true, data: [] } })
  })

  await page.goto('./apps/access/roles/7')
  await expect(page.getByRole('heading', { name: '角色 · 内容编辑' })).toBeVisible({ timeout: 20_000 })
  await page.getByRole('button', { name: '编辑', exact: true }).click()
  await page.getByRole('textbox', { name: '角色名称' }).fill('更新后的角色')
  await page.getByRole('button', { name: '保存修改' }).click()
  await expect(page.getByText('修改已保存，但最新角色详情读取失败，请重试确认。')).toBeVisible()
  await expect(page.getByRole('textbox', { name: '角色名称' })).toHaveValue('更新后的角色')
  await expect(page.getByRole('heading', { name: '角色 · 内容编辑' })).toBeVisible()
})
