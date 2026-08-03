import { test, expect } from '@playwright/test'

const routes = [
  '/apps/dashboard',
  '/apps/cards',
  '/apps/comments',
  '/apps/tags',
  '/apps/users',
  '/apps/roles',
  '/apps/permissions',
  '/apps/storage/files',
  '/apps/storage/config',
  '/apps/sender/config',
  '/apps/captcha/config',
  '/apps/system',
  '/apps/view',
  '/apps/view/theme-config',
  '/apps/updata',
]

test('all fixed admin routes load without page errors', async ({ page }) => {
  const pageErrors: string[] = []
  page.on('pageerror', (error) => pageErrors.push(error.message))

  for (const route of routes) {
    await page.goto(`.${route}`, { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/admin\/login$/)
    await expect(page.locator('body')).not.toContainText('权限不足')
  }

  expect(pageErrors, pageErrors.join('\n')).toEqual([])
})
