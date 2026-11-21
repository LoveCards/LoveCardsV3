import { expect, test, type Page } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/9YcAAAAASUVORK5CYII=', 'base64')

async function cardFixtures(page: Page) {
  await page.addInitScript(() => localStorage.setItem('token', 'card-detail-fixture'))
  const grants = [
    { capability: 'admin.access', scope: 'global' },
    { capability: 'cards.read', scope: 'all' },
    { capability: 'cards.update', scope: 'all' },
    { capability: 'cards.approve', scope: 'all' },
    { capability: 'cards.pin', scope: 'all' },
    { capability: 'files.upload', scope: 'global' },
  ]
  const card = {
    id: 101, user_id: 201, username: '林晓', content: '周末在校园里读书。',
    status: 0, is_top: 0, visibility: 'public', data: { mood: 'calm' },
    tags: [7], topics: [{ id: 7, name: '校园' }], media: [] as Array<Record<string, unknown>>,
    pictures: [], cover: null, cover_file_id: null as number | null,
    goods: 2, comments: 0, views: 5, created_at: '2026-09-18 09:20:00', updated_at: '',
  }
  const uploads: string[] = []
  const patches: Array<Record<string, unknown>> = []
  await page.route('**/api/**', async route => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (request.method() === 'POST' && path === '/api/me/files') {
      uploads.push(request.postDataBuffer()?.toString('utf8') ?? '')
      return route.fulfill({ json: { success: true, data: { id: 501, url: 'data:image/png;base64,' + png.toString('base64') } } })
    }
    if (request.method() === 'PATCH' && path === '/api/admin/cards/101') {
      const patch = request.postDataJSON() as Record<string, unknown>
      patches.push(patch)
      if (patch.media_file_ids) {
        card.media = [{ file_id: 501, type: 'image', url: 'data:image/png;base64,' + png.toString('base64'), position: 0 }]
        card.cover_file_id = Number(patch.cover_file_id)
      }
      return route.fulfill({ status: 204, body: '' })
    }
    let data: unknown = []
    if (path === '/api/me') data = { id: 9001, username: '详情测试管理员', roles: [], grants, status: 0 }
    else if (path === '/api/tags') data = [{ id: 7, name: '校园' }]
    else if (path === '/api/admin/cards') {
      return route.fulfill({ json: { success: true, data: [card], pagination: { totalItems: 1, currentPage: 1, totalPages: 1, itemsPerPage: 10 } } })
    } else if (path === '/api/admin/cards/101') data = card
    await route.fulfill({ json: { success: true, data } })
  })
  return { uploads, patches }
}

test('card dialog uploads in card scene and saves image binding', async ({ page }, testInfo) => {
  const { uploads, patches } = await cardFixtures(page)
  await page.goto('./apps/content/cards')
  const row = page.locator('tbody tr').first()
  await expect(row).toContainText('周末在校园', { timeout: 20_000 })
  await row.getByRole('button', { name: '编辑', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '卡片详情' })
  await expect(dialog.getByRole('textbox', { name: '正文' })).toHaveValue('周末在校园里读书。')
  await page.screenshot({ path: testInfo.outputPath('card-dialog-desktop.png'), animations: 'disabled' })
  await dialog.locator('input[type=file]').setInputFiles({ name: 'sample.png', mimeType: 'image/png', buffer: png })
  await expect(dialog.getByText('图片 #501')).toBeVisible()
  await expect.poll(() => uploads.length).toBe(1)
  expect(uploads[0]).toMatch(/name="scene"\r?\n\r?\ncard/)
  await dialog.getByRole('button', { name: '保存修改' }).click()
  await expect(dialog).toBeHidden()
  expect(patches).toHaveLength(1)
  expect(patches[0]).toMatchObject({ media_file_ids: [501], cover_file_id: 501 })
})

test('card detail and list dialog share fields at narrow width', async ({ page }, testInfo) => {
  await cardFixtures(page)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('./apps/content/cards/101')
  await expect(page.getByRole('heading', { name: '卡片 #101' })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByText('周末在校园里读书。')).toBeVisible()
  await page.getByRole('button', { name: '编辑', exact: true }).click()
  await expect(page.getByRole('textbox', { name: '正文' })).toHaveValue('周末在校园里读书。')
  await page.screenshot({ path: testInfo.outputPath('card-detail-narrow.png'), fullPage: true, animations: 'disabled' })
  await page.getByRole('button', { name: '取消编辑' }).click()
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
  await page.goto('./apps/content/cards')
  const row = page.locator('tbody tr').first()
  await expect(row).toContainText('周末在校园', { timeout: 20_000 })
  await row.getByRole('button', { name: '编辑', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '卡片详情' })
  await expect(dialog.getByRole('textbox', { name: '正文' })).toHaveValue('周末在校园里读书。')
  expect(await dialog.evaluate(element => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1)
  await page.screenshot({ path: testInfo.outputPath('card-dialog-narrow.png'), animations: 'disabled' })
})

test('invalid card JSON blocks save without changing the record', async ({ page }) => {
  const { patches } = await cardFixtures(page)
  await page.goto('./apps/content/cards/101')
  await expect(page.getByRole('heading', { name: '卡片 #101' })).toBeVisible({ timeout: 20_000 })
  await page.getByRole('button', { name: '编辑', exact: true }).click()
  await page.getByRole('textbox', { name: '扩展数据 JSON' }).fill('[1, 2]')
  await page.getByRole('button', { name: '保存修改' }).click()
  await expect(page.getByText('扩展数据顶层必须是 JSON 对象。')).toBeVisible()
  expect(patches).toHaveLength(0)
})
