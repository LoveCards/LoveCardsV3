import { expect, test, type Page, type Locator } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })
// 每项使用独立浏览器上下文和只读模拟 API，可并行验证布局。
test.describe.configure({ mode: 'parallel' })

const resources = [
  { key: 'cards', path: 'content/cards', text: '动作位置验收卡片', field: '正文', record: { id: 101, user_id: 201, username: '林晓', content: '动作位置验收卡片', status: 0, is_top: 0, tags: [], topics: [], media: [], pictures: [], cover: null, data: {}, goods: 2, comments: 0, views: 5, created_at: '2026-11-01 10:00:00' } },
  { key: 'comments', path: 'content/comments', text: '动作位置验收评论', field: '评论正文', record: { id: 101, user_id: 201, username: '林晓', card_id: 202, content: '动作位置验收评论', status: 0, is_pinned: false, deleted: false, parent_id: null, goods: 0, created_at: '2026-11-01 10:00:00' } },
  { key: 'tags', path: 'content/tags', text: '动作位置验收标签', field: '标签名称', record: { id: 101, aid: 1, user_id: 201, name: '动作位置验收标签', status: 0, created_at: '2026-11-01 10:00:00' } },
  { key: 'roles', path: 'access/roles', text: '动作位置验收角色', field: '角色名称', record: { id: 101, name: '动作位置验收角色', slug: 'layout_editor', description: '管理内容', is_system: 0, status: 0 } },
  { key: 'users', path: 'users', text: '动作位置验收用户', field: '用户名', record: { id: 101, username: '动作位置验收用户', number: '1000000101', email: 'layout@example.test', phone: '', avatar: '', status: 0, roles_id: [4], roles: [{ id: 4, name: '访客', slug: 'visitor' }] } },
]

async function fixture(page: Page, readonly = false, failRecord = false) {
  await page.addInitScript(() => {
    localStorage.setItem('token', 'detail-action-layout-fixture')
    localStorage.setItem('lovecards.admin.theme', 'system')
  })
  const capabilities = [
    ['admin.access', 'global'], ['roles.read', 'global'], ['permissions.read', 'global'], ['users.security.read', 'global'],
    ...['cards', 'comments', 'tags', 'users'].map(key => [`${key}.read`, 'all']),
    ...(!readonly ? [...['cards', 'comments', 'tags', 'users'].map(key => [`${key}.update`, 'all']), ['roles.update', 'global'], ['roles.assign', 'global']] : []),
  ]
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (route.request().method() !== 'GET') throw new Error(`Unexpected write: ${path}`)
    if (path === '/api/me') return route.fulfill({ json: { success: true, data: { id: 9001, username: '布局验收员', status: 0, roles: [], grants: capabilities.map(([capability, scope]) => ({ capability, scope })) } } })
    if (path === '/api/admin/permissions') return route.fulfill({ json: { success: true, data: [{ capability: 'cards.read', scope: 'all', description: '读取卡片' }] } })
    if (path.endsWith('/101/grants')) return route.fulfill({ json: { success: true, data: [] } })
    const resource = resources.find(item => path === `/api/admin/${item.key}/101` || path === `/api/admin/${item.key}`)
    if (resource) {
      if (path.endsWith('/101')) return route.fulfill(failRecord ? { status: 503, json: { code: 1, message: '详情读取失败' } } : { json: { success: true, data: resource.record } })
      const records = resource.key === 'roles' ? [resource.record, { id: 4, slug: 'visitor', name: '访客', is_system: 1 }] : [resource.record]
      return route.fulfill({ json: { success: true, data: records, pagination: { totalItems: records.length, currentPage: 1, totalPages: 1, itemsPerPage: 20 } } })
    }
    return route.fulfill({ json: { success: true, data: [] } })
  })
}

async function assertFits(container: Locator) {
  expect(await container.evaluate(element => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1)
  const buttons = container.getByRole('button')
  const bounds = await container.boundingBox()
  expect(bounds).not.toBeNull()
  for (const button of await buttons.all()) {
    if (!await button.isVisible()) continue
    const box = await button.boundingBox()
    expect(box).not.toBeNull()
    expect(box!.x).toBeGreaterThanOrEqual(bounds!.x - 1)
    expect(box!.x + box!.width).toBeLessThanOrEqual(bounds!.x + bounds!.width + 1)
  }
}

for (const resource of resources) {
  for (const width of [1440, 720, 600, 390]) {
    test(`record actions share the same edge and fit both containers: ${resource.key} ${width}`, async ({ page }, testInfo) => {
      test.setTimeout(90_000)
      await fixture(page)
      await page.setViewportSize({ width, height: 900 })
      await page.emulateMedia({ colorScheme: width === 390 ? 'dark' : 'light', reducedMotion: 'reduce' })
      await page.goto(`./apps/${resource.path}/101`)
      await expect(page.locator('.v-application').first()).toHaveClass(width === 390 ? /v-theme--indigoDarkTheme/ : /v-theme--indigoTheme/, { timeout: 20_000 })
      const edit = page.getByRole('button', { name: '编辑', exact: true })
      await expect(edit).toBeVisible({ timeout: 20_000 })
      const footer = edit.locator('xpath=ancestor::*[contains(@class,"v-card-actions")]')
      await assertFits(footer)
      const before = await edit.boundingBox()
      await edit.click()
      const save = page.getByRole('button', { name: '保存修改', exact: true })
      await expect(save).toBeVisible()
      const after = await save.boundingBox()
      expect(Math.abs((before!.x + before!.width) - (after!.x + after!.width))).toBeLessThan(2)
      await assertFits(save.locator('xpath=ancestor::*[contains(@class,"v-card-actions")]'))
      await page.screenshot({ path: testInfo.outputPath('page-edit.jpg'), fullPage: true, type: 'jpeg', quality: 65, animations: 'disabled' })
      await page.getByRole('button', { name: '取消编辑', exact: true }).click()
      await page.goto(`./apps/${resource.path}`)
      const row = page.getByRole('row').filter({ hasText: resource.text })
      await expect(row).toBeVisible({ timeout: 20_000 })
      const action = row.getByRole('button', { name: '编辑', exact: true })
      if (await action.isVisible()) await action.click()
      else { await row.getByRole('button', { name: /更多/ }).click(); await page.getByRole('menuitem', { name: '编辑', exact: true }).click() }
      const dialog = page.locator('.v-dialog.v-overlay--active')
      await expect(dialog.getByRole('textbox', { name: resource.field, exact: true })).toBeVisible({ timeout: 20_000 })
      await expect(dialog.getByRole('button', { name: '保存修改', exact: true })).toHaveCount(1)
      await assertFits(dialog.getByRole('button', { name: '保存修改', exact: true }).locator('xpath=ancestor::*[contains(@class,"v-card-actions")]'))
      expect(await dialog.locator('.v-card-actions').count()).toBe(1)
      for (const section of await dialog.locator('.admin-detail-section').all()) {
        const borders = await section.evaluate(element => { const style = getComputedStyle(element); return [style.borderLeftWidth, style.borderRightWidth, style.borderBottomWidth, style.borderRadius] })
        expect(borders).toEqual(['0px', '0px', '0px', '0px'])
      }
      await dialog.getByRole('button', { name: '保存修改', exact: true }).scrollIntoViewIfNeeded()
      await page.screenshot({ path: testInfo.outputPath('dialog-edit.jpg'), type: 'jpeg', quality: 65, animations: 'disabled' })
      expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
    })
  }
}

test('read-only records never render empty edit footers', async ({ page }) => {
  test.setTimeout(90_000)
  await fixture(page, true)
  for (const resource of resources) {
    await page.goto(`./apps/${resource.path}/101`)
    await expect(page.locator('main')).toContainText(resource.text, { timeout: 20_000 })
    await expect(page.getByRole('button', { name: '编辑', exact: true })).toHaveCount(0)
    const footers = page.locator('main .v-card-actions')
    for (const footer of await footers.all()) expect(await footer.locator('button, a[href]').count()).toBeGreaterThan(0)
  }
})

test('record load failures do not show editing actions', async ({ page }) => {
  test.setTimeout(90_000)
  await fixture(page, false, true)
  for (const resource of resources) {
    await page.goto(`./apps/${resource.path}/101`)
    await expect(page.locator('main')).toContainText('详情读取失败', { timeout: 20_000 })
    await expect(page.getByRole('button', { name: '编辑', exact: true })).toHaveCount(0)
    await expect(page.getByRole('button', { name: '保存修改', exact: true })).toHaveCount(0)
  }
})

test('account security navigation protects the shared dialog draft', async ({ page }) => {
  test.setTimeout(60_000)
  await fixture(page)
  await page.goto('./apps/users')
  await page.getByRole('row').filter({ hasText: '动作位置验收用户' }).getByRole('button', { name: '编辑', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '用户详情' })
  const listUrl = page.url()
  await dialog.getByRole('textbox', { name: '用户名' }).fill('尚未保存的名字')
  await dialog.getByRole('link', { name: '账号安全', exact: true }).click()
  const confirm = page.getByRole('dialog', { name: '有未保存的修改' })
  await expect(confirm).toBeVisible()
  await confirm.getByRole('button', { name: '继续编辑' }).click()
  await expect(dialog.getByRole('textbox', { name: '用户名' })).toHaveValue('尚未保存的名字')
  await expect(page).toHaveURL(listUrl)
  await dialog.getByRole('link', { name: '账号安全', exact: true }).click()
  await confirm.getByRole('button', { name: '放弃修改' }).click()
  await expect(page).toHaveURL(/\/apps\/users\/101\/security$/)
})

test('account status submission locks conflicting profile operations', async ({ page }) => {
  test.setTimeout(60_000)
  await fixture(page)
  let release: (() => void) | undefined
  let entered = false
  const writes: unknown[] = []
  await page.route('**/api/admin/users/101', async route => {
    if (route.request().method() !== 'PATCH') return route.fallback()
    writes.push(route.request().postDataJSON())
    entered = true
    await new Promise<void>(resolve => { release = resolve })
    await route.fulfill({ status: 204, body: '' })
  })
  await page.goto('./apps/users/101')
  await page.getByRole('button', { name: '编辑', exact: true }).click()
  await page.getByRole('textbox', { name: '用户名' }).fill('待保存资料')
  await page.locator('.v-select').filter({ hasText: '账号状态' }).click()
  await page.getByRole('option', { name: '封禁', exact: true }).click()
  await page.getByRole('button', { name: '封禁用户', exact: true }).click()
  try {
    await expect.poll(() => entered).toBe(true)
    await expect(page.getByRole('button', { name: '保存修改', exact: true })).toBeDisabled()
    await expect(page.getByRole('button', { name: '取消编辑', exact: true })).toBeDisabled()
    await expect(page.getByRole('textbox', { name: '用户名' })).toBeDisabled()
    expect(writes).toEqual([{ status: 1 }])
  } finally { release?.() }
  await expect(page.getByRole('textbox', { name: '用户名' })).toHaveValue('待保存资料')
  await expect(page.getByRole('button', { name: '保存修改', exact: true })).toBeEnabled()
})

test('file operation sections require an action valid for the current state', async ({ page }) => {
  test.setTimeout(60_000)
  await fixture(page, true)
  for (const deleted of [false, true]) {
    await page.route('**/api/me', route => route.fulfill({ json: { success: true, data: { id: 9001, username: '文件验收员', status: 0, roles: [], grants: [
      { capability: 'admin.access', scope: 'global' }, { capability: 'files.read', scope: 'all' },
      { capability: deleted ? 'files.delete' : 'files.restore', scope: 'all' },
    ] } } }))
    await page.route('**/api/admin/files/101', route => route.fulfill({ json: { success: true, data: { id: 101, user_id: 201, original_name: 'layout.txt', file_size: 100, mime_type: 'text/plain', status: 0, is_public: false, is_deleted: deleted } } }))
    await page.goto('./apps/settings/storage/files/101')
    await expect(page.locator('main')).toContainText('layout.txt', { timeout: 20_000 })
    await expect(page.getByRole('heading', { name: '文件操作', exact: true })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /移入回收站|恢复文件|永久删除文件/ })).toHaveCount(0)
  }
})
