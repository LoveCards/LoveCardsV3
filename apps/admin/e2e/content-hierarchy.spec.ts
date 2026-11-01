import { expect, test, type Page } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })
test.setTimeout(60_000)

async function contentFixtures(page: Page, resource: 'cards' | 'comments', readOnly = false, savedColumns = false) {
  await page.addInitScript(({ resource, savedColumns }) => {
    localStorage.setItem('token', 'content-layout-fixture')
    if (savedColumns) localStorage.setItem(`lovecards.admin.table-view.9001.content-${resource}`, JSON.stringify({
      columns: ['id', 'content', 'goods', 'created_at', 'status', 'operate'], views: [],
    }))
  }, { resource, savedColumns })
  const grants = [
    { capability: 'admin.access', scope: 'global' },
    { capability: `${resource}.read`, scope: 'all' },
    ...(!readOnly ? ['update', 'delete', 'pin'].map(action => ({ capability: `${resource}.${action}`, scope: 'all' })) : []),
  ]
  const rows = Array.from({ length: 8 }, (_, index) => ({
    id: 101 + index, user_id: 201 + index, username: ['林晓', '陈言', '周宁'][index % 3],
    card_id: 51, parent_id: null, content: [
      '周末在校园里发现了一处安静的角落，阳光穿过树叶，适合坐下来读一会儿书。',
      '这个建议很有帮助，希望下次活动能提前公布时间，方便大家安排。',
      '这是一条需要审核的内容，检查上下文之后再决定是否发布。',
      '这是一段很长的评论。'.repeat(24),
    ][index % 4], status: [0, 0, 3, 2][index % 4], goods: index + 2, comments: index,
    is_pinned: index === 1, is_top: index === 1 ? 1 : 0, cover: null, tags: [], data: {},
    created_at: '2026-09-18 09:20:00', updated_at: null, mentions: [], deleted: false,
  }))
  const unexpected: string[] = []
  await page.route('**/api/**', async route => {
    // 开发服务的模块路径也可能包含 /api/；只拦截真正的后端请求。
    if (!new URL(route.request().url()).pathname.startsWith('/api/')) return route.continue()
    const request = route.request()
    if (request.method() !== 'GET') {
      unexpected.push(`${request.method()} ${request.url()}`)
      return route.abort()
    }
    const path = new URL(request.url()).pathname
    let data: unknown = []
    if (path === '/api/me') data = { id: 9001, username: '布局测试管理员', roles: [], grants, status: 0 }
    else if (path === `/api/admin/${resource}`) {
      return route.fulfill({ json: { success: true, data: rows, pagination: { totalItems: rows.length, currentPage: 1, totalPages: 1, itemsPerPage: 10 } } })
    } else if (path.startsWith(`/api/admin/${resource}/`)) data = rows.find(row => path.endsWith(`/${row.id}`))
    await route.fulfill({ json: { success: true, data } })
  })
  return unexpected
}

test('comment pin actions retain eligibility and confirmation', async ({ page }) => {
  const unexpected = await contentFixtures(page, 'comments')
  await page.goto('./apps/content/comments')
  const rows = page.locator('tbody tr')
  await rows.first().getByRole('button', { name: '更多评论操作' }).click()
  await page.getByText('置顶评论', { exact: true }).click()
  await expect(page.getByRole('dialog', { name: '置顶评论', exact: true })).toBeVisible()
  await page.getByRole('dialog').getByRole('button', { name: '取消', exact: true }).click()
  await rows.nth(2).getByRole('button', { name: '更多评论操作' }).click()
  await expect(page.getByText('置顶评论', { exact: true })).toHaveCount(0)
  expect(unexpected).toEqual([])
})

for (const resource of ['cards', 'comments'] as const) {
  for (const width of [1440, 390]) {
    test(`content-first hierarchy at ${width}px: ${resource}`, async ({ page }, testInfo) => {
      const unexpected = await contentFixtures(page, resource)
      await page.setViewportSize({ width, height: 960 })
      await page.goto(`./apps/content/${resource}`)
      await expect(page.locator('tbody tr').first()).toContainText('周末在校园', { timeout: 20_000 })
      await page.screenshot({ path: testInfo.outputPath('content-list.png'), fullPage: true, animations: 'disabled' })
      const headings = await page.locator('thead tr').first().locator('th, td').allTextContents()
      expect(headings.map(value => value.trim())).toEqual(['', '内容', '发布时间', '状态', '操作'])
      await expect(page.locator('tbody tr').first()).toContainText('林晓')
      await expect(page.locator('tbody tr').first().locator('.v-chip')).toHaveCount(0)
      await expect(page.getByText('待审核', { exact: true }).first()).toBeVisible()
      const label = resource === 'cards' ? '卡片' : '评论'
      const row = page.locator('tbody tr').first()
      await expect(row.getByRole('button', { name: `编辑${label}`, exact: true })).toBeVisible()
      await expect(page.getByRole('button', { name: `删除${label}`, exact: true })).toHaveCount(0)
      await row.getByRole('button', { name: `更多${label}操作` }).click()
      await page.getByText(`删除${label}`, { exact: true }).click()
      await expect(page.getByRole('dialog')).toBeVisible()
      await page.getByRole('dialog').getByRole('button', { name: '取消', exact: true }).click()
      expect(unexpected).toEqual([])
      expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
    })
  }

  test(`saved diagnostic columns are preserved: ${resource}`, async ({ page }) => {
    await contentFixtures(page, resource, false, true)
    await page.goto(`./apps/content/${resource}`)
    await expect(page.locator('thead').getByText('ID', { exact: true })).toBeVisible()
    await expect(page.locator('thead').getByText('点赞数', { exact: true })).toBeVisible()
  })

  test(`read-only content actions remain non-destructive: ${resource}`, async ({ page }) => {
    await contentFixtures(page, resource, true)
    await page.goto(`./apps/content/${resource}`)
    const label = resource === 'cards' ? '卡片' : '评论'
    await expect(page.locator('tbody tr').first().getByRole('button', { name: `查看${label}`, exact: true })).toBeVisible({ timeout: 20_000 })
    await expect(page.getByRole('button', { name: `更多${label}操作` })).toHaveCount(0)
  })
}
