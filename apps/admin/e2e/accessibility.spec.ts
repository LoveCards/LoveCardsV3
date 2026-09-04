import { expect, test } from '@playwright/test'

const canonicalRoutes = [
  '/apps/dashboard',
  '/apps/content/cards',
  '/apps/users',
  '/apps/settings/site',
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

  await page.getByRole('button', { name: '列设置' }).click()
  const emailColumn = page.getByRole('checkbox', { name: '显示邮箱列' })
  await emailColumn.check()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('columnheader', { name: '邮箱' })).toBeVisible()

  await page.getByRole('button', { name: /保存视图/ }).click()
  await page.getByRole('button', { name: '保存当前视图' }).click()
  await page.getByLabel('视图名称').fill('E2E 用户视图')
  await page.getByRole('button', { name: '保存', exact: true }).click()

  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('columnheader', { name: '邮箱' })).toBeVisible()
  await page.getByRole('button', { name: /保存视图/ }).click()
  await expect(page.getByText('E2E 用户视图', { exact: true })).toBeVisible()

  await page.evaluate(() => {
    for (let index = window.localStorage.length - 1; index >= 0; index -= 1) {
      const key = window.localStorage.key(index)
      if (key?.startsWith('lovecards.admin.table-view.') && key.endsWith('.users')) {
        window.localStorage.removeItem(key)
      }
    }
  })
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
