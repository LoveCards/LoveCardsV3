import { expect, test } from '@playwright/test'

const canonicalRoutes = [
  '/apps/dashboard',
  '/apps/content/cards',
  '/apps/content/tags',
  '/apps/users',
  '/apps/settings/site',
  '/apps/settings/site/policies',
  '/apps/operations/audit',
  '/apps/access/roles',
]

for (const route of canonicalRoutes) {
  test(`canonical page has one visible title and reflows at 200% equivalent: ${route}`, async ({ page }) => {
    test.setTimeout(60_000)
    await page.setViewportSize({ width: 640, height: 900 })
    await page.goto(`.${route}`, { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/admin\/login$/)
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1, { timeout: 15_000 })
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 15_000 })
    await page.waitForTimeout(150)

    const overflow = await page.evaluate(() => Math.max(
      document.documentElement.scrollWidth,
      document.body.scrollWidth,
    ) - document.documentElement.clientWidth)
    expect(overflow).toBeLessThanOrEqual(1)
  })
}

test('keyboard users can skip directly to the main content', async ({ page }) => {
  await page.goto('./apps/dashboard', { waitUntil: 'domcontentloaded' })
  const skipLink = page.getByRole('link', { name: '跳到主要内容' })
  await expect(page.getByRole('heading', { name: '工作台', level: 1 })).toBeVisible()
  await expect(skipLink).toBeAttached()
  await page.keyboard.press('Tab')
  await expect(skipLink).toBeFocused()
  await expect(skipLink).toBeVisible()
  await page.keyboard.press('Enter')
  await expect(page.locator('#admin-main-content')).toBeFocused()
})

test('workbench sections follow the same visual and document order', async ({ page }) => {
  await page.goto('./apps/dashboard', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: '我的待办' })).toBeVisible()

  const sections = await page.locator('main section[aria-labelledby]').evaluateAll((elements) => elements.map((element) => ({
    id: element.getAttribute('aria-labelledby'),
    top: element.getBoundingClientRect().top,
  })))
  const ids = sections.map((section) => section.id)
  expect(ids.indexOf('tasks-title')).toBeLessThan(ids.indexOf('responsibility-title'))
  if (ids.includes('runtime-title')) {
    expect(ids.indexOf('runtime-title')).toBeLessThan(ids.indexOf('responsibility-title'))
  }
  if (ids.includes('notifications-title')) {
    expect(ids.indexOf('responsibility-title')).toBeLessThan(ids.indexOf('notifications-title'))
  }
  expect(sections.map((section) => section.top)).toEqual([...sections.map((section) => section.top)].sort((a, b) => a - b))
})

test('reduced-motion preference disables non-essential admin transitions', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('./apps/dashboard', { waitUntil: 'domcontentloaded' })

  const durations = await page.evaluate(() => {
    const probe = document.querySelector('.admin-main') ?? document.body
    const style = window.getComputedStyle(probe)
    return { animation: style.animationDuration, transition: style.transitionDuration }
  })
  expect(['0s', '0.01ms', '0.00001s']).toContain(durations.animation)
  expect(['0s', '0.01ms', '0.00001s']).toContain(durations.transition)
})

test('table column preferences and named views survive reload for the same administrator', async ({ page }) => {
  test.setTimeout(60_000)
  await page.goto('./apps/users', { waitUntil: 'domcontentloaded' })

  await page.getByRole('button', { name: /视图设置/ }).click()
  const emailColumn = page.getByRole('checkbox', { name: '显示邮箱列' })
  await emailColumn.check()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('columnheader', { name: '邮箱' })).toBeVisible()

  const search = page.getByRole('textbox', { name: '搜索用户名', exact: true })
  await search.fill('e2e-no-user-view')
  await expect(page).toHaveURL(/search_value=e2e-no-user-view/)

  await page.getByRole('button', { name: /视图设置/ }).click()
  await page.getByText('保存当前视图', { exact: true }).click()
  const saveDialog = page.getByRole('dialog', { name: '保存当前视图' })
  await expect(saveDialog.locator('.v-card')).toHaveClass(/v-card--variant-elevated/)
  await page.getByLabel('视图名称').fill('E2E 用户视图')
  await page.getByRole('button', { name: '保存', exact: true }).click()

  await search.fill('')
  await expect(page).not.toHaveURL(/search_value=/)

  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('columnheader', { name: '邮箱' })).toBeVisible()
  await page.getByRole('button', { name: /视图设置/ }).click()
  await expect(page.getByText('E2E 用户视图', { exact: true })).toBeVisible()
  await page.getByText('E2E 用户视图', { exact: true }).click()
  await expect(page).toHaveURL(/search_value=e2e-no-user-view/)
  await expect(search).toHaveValue('e2e-no-user-view')
  await expect(page.locator('.v-data-table-rows-no-data')).toBeVisible()

  await page.evaluate(() => {
    for (let index = window.localStorage.length - 1; index >= 0; index -= 1) {
      const key = window.localStorage.key(index)
      if (key?.startsWith('lovecards.admin.table-view.') && key.endsWith('.users')) {
        window.localStorage.removeItem(key)
      }
    }
  })
})

test('the tag list keeps secondary controls progressive and fits a narrow viewport', async ({ page }) => {
  test.setTimeout(60_000)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('./apps/content/tags', { waitUntil: 'domcontentloaded' })

  await expect(page.getByRole('heading', { name: '标签', level: 1 })).toBeVisible({ timeout: 15_000 })
  await expect(page.locator('.admin-page-header').getByRole('button', { name: '创建标签' })).toBeVisible()
  await expect(page.getByRole('button', { name: '搜索与排序' })).toBeVisible()
  await expect(page.getByRole('button', { name: /视图设置/ })).toBeVisible()
  await expect(page.getByLabel('批量操作')).toHaveCount(0)
  const tableHeader = page.locator('thead')
  await expect(tableHeader.getByText('标签名', { exact: true })).toBeVisible()
  await expect(tableHeader.getByText('ID', { exact: true })).toHaveCount(0)

  const overflow = await page.evaluate(() => Math.max(
    document.documentElement.scrollWidth,
    document.body.scrollWidth,
  ) - document.documentElement.clientWidth)
  expect(overflow).toBeLessThanOrEqual(1)
})

test('configuration pages guard unsaved changes on in-app navigation', async ({ page }) => {
  test.setTimeout(60_000)
  await page.goto('./apps/settings/site', { waitUntil: 'domcontentloaded' })
  const siteName = page.getByLabel('站点名称')
  await expect(siteName).toBeEditable({ timeout: 15_000 })
  await siteName.fill(`${await siteName.inputValue()}-未保存`)

  await page.getByRole('link', { name: '返回管理工作台' }).click()
  const dialog = page.getByRole('dialog', { name: '有未保存的修改' })
  await expect(dialog).toBeVisible()
  await dialog.getByRole('button', { name: '继续编辑' }).click()
  await expect(page).toHaveURL(/\/admin\/apps\/settings\/site$/)

  await page.getByRole('link', { name: '返回管理工作台' }).click()
  await dialog.getByRole('button', { name: '放弃修改' }).click()
  await expect(page).toHaveURL(/\/admin\/apps\/dashboard$/)
})

test('site policy tabs guard unsaved changes on in-app navigation', async ({ page }) => {
  test.setTimeout(60_000)
  await page.goto('./apps/settings/site/policies?tab=content', { waitUntil: 'domcontentloaded' })
  const pictureLimit = page.getByLabel('卡片图集上限')
  await expect(pictureLimit).toBeEditable({ timeout: 15_000 })
  await pictureLimit.fill('9')

  await page.getByRole('link', { name: '返回管理工作台' }).click()
  const dialog = page.getByRole('dialog', { name: '有未保存的修改' })
  await expect(dialog).toBeVisible()
  await dialog.getByRole('button', { name: '继续编辑' }).click()
  await expect(page).toHaveURL(/\/admin\/apps\/settings\/site\/policies\?tab=content$/)

  await page.getByRole('link', { name: '返回管理工作台' }).click()
  await dialog.getByRole('button', { name: '放弃修改' }).click()
  await expect(page).toHaveURL(/\/admin\/apps\/dashboard$/)
})
