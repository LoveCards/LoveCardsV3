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
  '/apps/system/upgrade',
  '/apps/system/runtime',
  '/apps/governance/reports',
  '/apps/security/recovery',
  '/apps/operations/deliveries',
  '/apps/operations/audit',
  '/apps/community/insights',
  '/apps/governance/reports/1',
  '/apps/users/1/security',
  '/apps/storage/files/1/usage',
]

for (const route of routes) {
	test(`admin route loads without page errors: ${route}`, async ({ page }) => {
		test.setTimeout(60_000)
		const pageErrors: string[] = []
		page.on('pageerror', (error) => pageErrors.push(error.message))

		// Some administration pages keep a polling request open.  Waiting for
		// networkidle would make the whole route suite time out even though the
		// page is usable, so assert the application shell instead.
		await page.goto(`.${route}`, { waitUntil: 'domcontentloaded' })
		await expect(page.locator('main')).toBeVisible({ timeout: 20_000 })
		await expect(page).not.toHaveURL(/\/admin\/login$/)
		await expect(page.locator('body')).not.toContainText('权限不足')
		await page.waitForTimeout(250)

		expect(pageErrors, pageErrors.join('\n')).toEqual([])
	})
}
