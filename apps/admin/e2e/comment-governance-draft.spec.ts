import { expect, test } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

test('comment governance stays available without discarding an edited body', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('token', 'comment-governance-fixture'))
  const grants = [
    { capability: 'admin.access', scope: 'global' },
    { capability: 'comments.read', scope: 'all' },
    { capability: 'comments.update', scope: 'all' },
    { capability: 'comments.moderate', scope: 'all' },
  ]
  const comment = {
    id: 101, card_id: 202, user_id: 303, username: '作者', content: '原评论', status: 0,
    is_pinned: false, deleted: false, parent_id: null, goods: 0, created_at: '2026-11-01 10:00:00', updated_at: null,
  }
  const commands: string[] = []
  const patches: Array<Record<string, unknown>> = []
  await page.route('**/api/**', async route => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (path === '/api/me') return route.fulfill({ json: { success: true, data: { id: 9001, username: '管理员', roles: [], grants, status: 0 } } })
    if (path === '/api/admin/comments/101' && request.method() === 'PATCH') {
      const patch = request.postDataJSON() as Record<string, unknown>
      patches.push(patch)
      comment.content = String(patch.content)
      return route.fulfill({ status: 204, body: '' })
    }
    if (path === '/api/admin/comments/batch' && request.method() === 'POST') {
      const method = (request.postDataJSON() as { method: string }).method
      commands.push(method)
      if (method === 'hide') comment.status = 2
      return route.fulfill({ status: 204, body: '' })
    }
    if (path === '/api/admin/comments/101') return route.fulfill({ json: { success: true, data: comment } })
    return route.fulfill({ json: { success: true, data: [] } })
  })

  await page.goto('./apps/content/comments/101')
  await expect(page.getByText('原评论', { exact: true })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('button', { name: '编辑', exact: true }).locator('xpath=ancestor::*[contains(@class, "v-card-actions")]')).toBeVisible()
  await page.getByRole('button', { name: '编辑', exact: true }).click()
  await page.getByRole('textbox', { name: '评论正文' }).fill('未保存的评论')
  await page.locator('.v-select').filter({ hasText: '审核状态' }).click()
  await page.getByRole('option', { name: '隐藏评论' }).click()
  await page.getByRole('button', { name: '隐藏评论', exact: true }).click()
  await expect(page.getByRole('textbox', { name: '评论正文' })).toHaveValue('未保存的评论')
  expect(commands).toEqual(['hide'])
  expect(patches).toHaveLength(0)
  await page.getByRole('button', { name: '保存修改' }).click()
  expect(patches).toHaveLength(1)
  expect(patches[0]).toMatchObject({ content: '未保存的评论' })
})
