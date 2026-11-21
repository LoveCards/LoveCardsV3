import { expect, test } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

test('tag governance stays available without discarding an edited name', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('token', 'tag-governance-fixture'))
  const grants = [
    { capability: 'admin.access', scope: 'global' },
    { capability: 'tags.read', scope: 'all' },
    { capability: 'tags.update', scope: 'all' },
  ]
  const tag = { id: 101, aid: 1, user_id: 201, name: '原标签', status: 0, created_at: '2026-11-01 10:00:00' }
  const commands: string[] = []
  const patches: Array<Record<string, unknown>> = []
  await page.route('**/api/**', async route => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (path === '/api/me') return route.fulfill({ json: { success: true, data: { id: 9001, username: '管理员', roles: [], grants, status: 0 } } })
    if (path === '/api/admin/tags/101' && request.method() === 'PATCH') {
      const patch = request.postDataJSON() as Record<string, unknown>
      patches.push(patch)
      tag.name = String(patch.name)
      return route.fulfill({ status: 204, body: '' })
    }
    if (path === '/api/admin/tags/batch' && request.method() === 'POST') {
      const method = (request.postDataJSON() as { method: string }).method
      commands.push(method)
      if (method === 'hide') tag.status = 2
      return route.fulfill({ status: 204, body: '' })
    }
    if (path === '/api/admin/tags/101') return route.fulfill({ json: { success: true, data: tag } })
    return route.fulfill({ json: { success: true, data: [] } })
  })

  await page.goto('./apps/content/tags/101')
  await expect(page.getByText('原标签', { exact: true })).toBeVisible({ timeout: 20_000 })
  await page.getByRole('button', { name: '编辑', exact: true }).click()
  await page.getByRole('textbox', { name: '标签名称' }).fill('未保存的标签')
  await page.getByRole('button', { name: '隐藏', exact: true }).click()
  await page.getByRole('button', { name: '确认隐藏' }).click()
  await expect(page.getByRole('textbox', { name: '标签名称' })).toHaveValue('未保存的标签')
  expect(commands).toEqual(['hide'])
  expect(patches).toHaveLength(0)
  await page.getByRole('button', { name: '保存名称' }).click()
  expect(patches).toHaveLength(1)
  expect(patches[0]).toMatchObject({ name: '未保存的标签' })
})
