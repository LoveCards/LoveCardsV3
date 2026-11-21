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
    ...(!readOnly ? ['update', 'delete', 'pin', ...(resource === 'comments' ? ['moderate'] : ['approve'])].map(action => ({ capability: `${resource}.${action}`, scope: 'all' })) : []),
  ]
  const rows = Array.from({ length: 8 }, (_, index) => ({
    id: 101 + index, user_id: 201 + index, username: ['林晓', '陈言', '周宁'][index % 3],
    card_id: 51, parent_id: null, content: [
      '周末在校园里发现了一处安静的角落，阳光穿过树叶，适合坐下来读一会儿书。',
      '这个建议很有帮助，希望下次活动能提前公布时间，方便大家安排。',
      '这是一条需要审核的内容，检查上下文之后再决定是否发布。',
      '这是一段很长的评论。'.repeat(24),
    ][index % 4], status: [0, 0, 3, 2][index % 4], goods: index + 2, comments: index,
    is_pinned: index === 1, is_top: index === 1 ? 1 : 0, cover: null, tags: index === 0 && resource === 'cards' ? [7] : [], data: {}, media: [], pictures: [], topics: [], visibility: 'public',
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
    else if (path === '/api/tags') data = [{ id: 7, name: '校园' }]
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
  await expect(page.locator('tbody tr').first()).toContainText('周末在校园', { timeout: 20_000 })
  const rows = page.locator('tbody tr')
  const pinButton = rows.first().getByRole('button', { name: '置顶', exact: true })
  if (await pinButton.count()) await pinButton.click()
  else { await rows.first().getByRole('button', { name: '更多评论操作' }).click(); await page.locator('.v-overlay--active').getByText('置顶', { exact: true }).click() }
  await expect(page.getByRole('dialog', { name: '置顶评论', exact: true })).toBeVisible()
  await page.getByRole('dialog').getByRole('button', { name: '取消', exact: true }).click()
  await expect(rows.nth(2).getByRole('button', { name: '置顶', exact: true })).toHaveCount(0)
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
      {
        const headings = await page.locator('thead tr').first().locator('th, td').allTextContents()
        expect(headings.map(value => value.trim())).toEqual(['', '内容', '用户', '发布时间', '状态', '操作'])
      }
      await expect(page.locator('tbody tr').first()).toContainText('林晓')
      await expect(page.locator('tbody tr').first().locator('.v-chip')).toHaveCount(0)
      await expect(page.getByText('待审核', { exact: true }).first()).toBeVisible()
      const label = resource === 'cards' ? '卡片' : '评论'
      const row = page.locator('tbody tr').first()
      const editButton = row.getByRole('button', { name: '编辑', exact: true })
      await expect(editButton).toBeVisible()
      await expect(row.getByRole('button', { name: '查看', exact: true })).toHaveCount(0)
      const listUrl = page.url()
      await editButton.click()
      await expect(page.getByRole('dialog', { name: `${label}详情` })).toBeVisible()
      await expect(page.getByRole('dialog').getByRole('textbox', { name: resource === 'cards' ? '正文' : '评论正文' })).toHaveValue(/周末在校园/)
      await expect(page).toHaveURL(listUrl)
      await page.getByRole('dialog').locator('.v-card-title').getByRole('button', { name: '关闭', exact: true }).click()
      const deleteButton = row.getByRole('button', { name: '删除', exact: true })
      if (await deleteButton.count()) await deleteButton.click()
      else { await row.getByRole('button', { name: `更多${label}操作` }).click(); await page.locator('.v-overlay--active').getByText('删除', { exact: true }).click() }
      await expect(page.getByRole('dialog')).toBeVisible()
      await page.getByRole('dialog').getByRole('button', { name: '取消', exact: true }).click()
      expect(unexpected).toEqual([])
      expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
    })
  }

  test(`saved diagnostic columns are preserved: ${resource}`, async ({ page }) => {
    await contentFixtures(page, resource, false, true)
    await page.goto(`./apps/content/${resource}`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('tbody tr').first()).toContainText('周末在校园', { timeout: 20_000 })
    await expect(page.locator('thead').getByText('ID', { exact: true })).toBeVisible({ timeout: 20_000 })
    await expect(page.locator('thead').getByText('点赞数', { exact: true })).toBeVisible()
  })

  test(`read-only content actions remain non-destructive: ${resource}`, async ({ page }) => {
    await contentFixtures(page, resource, true)
    await page.goto(`./apps/content/${resource}`)
    const label = resource === 'cards' ? '卡片' : '评论'
    await expect(page.locator('tbody tr').first().getByRole('button', { name: '查看', exact: true })).toBeVisible({ timeout: 20_000 })
    await expect(page.getByRole('button', { name: `更多${label}操作` })).toHaveCount(0)
  })
}

test('card and comment details reuse the same editing surface in dialogs', async ({ page }) => {
  const unexpected = await contentFixtures(page, 'cards')
  await page.goto('./apps/content/cards')
  const row = page.locator('tbody tr').first()
  await row.getByRole('button', { name: '编辑', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '卡片详情' })
  await expect(dialog.getByRole('textbox', { name: '正文' })).toHaveValue(/周末在校园/)
  await dialog.getByRole('button', { name: '取消编辑' }).click()
  await expect(dialog.getByRole('textbox', { name: '正文' })).toHaveCount(0)
  expect(unexpected).toEqual([])
})

test('saved card relation columns display readable names', async ({ page }) => {
  await contentFixtures(page, 'cards')
  await page.goto('./apps/content/cards')
  await page.getByRole('button', { name: '视图设置' }).click()
  await page.getByRole('checkbox', { name: '显示标签列' }).click()
  await page.keyboard.press('Escape')
  await expect(page.locator('tbody tr').first()).toContainText('林晓 #201')
  await expect(page.locator('tbody tr').first()).toContainText('校园 #7')
})

test('detail dialog restores keyboard focus to its row action', async ({ page }) => {
  await contentFixtures(page, 'cards')
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('./apps/content/cards')
  await expect(page.locator('tbody tr').first()).toContainText('周末在校园', { timeout: 20_000 })
  const edit = page.locator('tbody tr').first().getByRole('button', { name: '编辑', exact: true })
  await expect(edit).toBeVisible()
  await edit.focus()
  await edit.press('Enter')
  const dialog = page.getByRole('dialog', { name: '卡片详情' })
  await expect(dialog).toBeVisible()
  await dialog.locator('.v-card-title').getByRole('button', { name: '关闭', exact: true }).click()
  await expect(dialog).toBeHidden()
  await expect(edit).toBeFocused()
})


test('comment dialog closes with Escape and restores focus under reduced motion', async ({ page }) => {
  await contentFixtures(page, 'comments')
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('./apps/content/comments')
  await expect(page.locator('tbody tr').first()).toContainText('周末在校园', { timeout: 20_000 })
  const edit = page.locator('tbody tr').first().getByRole('button', { name: '编辑', exact: true })
  await edit.focus()
  await edit.press('Enter')
  const dialog = page.getByRole('dialog', { name: '评论详情' })
  await expect(dialog).toBeVisible()
  const transitionDuration = await dialog.evaluate(element => getComputedStyle(element).transitionDuration)
  expect(Math.max(...transitionDuration.split(',').map(value => parseFloat(value)))).toBeLessThanOrEqual(0.001)
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await expect(edit).toBeFocused()
})

test('Escape keeps a dirty card dialog open until changes are resolved', async ({ page }) => {
  await contentFixtures(page, 'cards')
  await page.goto('./apps/content/cards')
  const row = page.locator('tbody tr').first()
  await expect(row).toContainText('周末在校园', { timeout: 20_000 })
  const edit = row.getByRole('button', { name: '编辑', exact: true })
  await edit.click()
  const dialog = page.getByRole('dialog', { name: '卡片详情' })
  const body = dialog.getByRole('textbox', { name: '正文' })
  await expect(body).toBeVisible()
  await body.fill('未保存的卡片正文')
  await page.keyboard.press('Escape')
  await expect(dialog).toBeVisible()
  await expect(dialog).toContainText('有未保存修改')
  await dialog.getByRole('button', { name: '继续编辑' }).click()
  await expect(body).toHaveValue('未保存的卡片正文')
  await dialog.locator('.v-card-title').getByRole('button', { name: '关闭', exact: true }).click()
  await dialog.getByRole('button', { name: '放弃修改' }).click()
  await expect(dialog).toBeHidden()
  await expect(edit).toBeFocused()
})
