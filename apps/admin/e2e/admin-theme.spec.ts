import { expect, test } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

test('admin theme follows system changes and restores the saved preference', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'light' })
  await page.goto('./login', { waitUntil: 'domcontentloaded' })
  await page.evaluate(() => window.localStorage.removeItem('lovecards.admin.theme'))
  await page.reload({ waitUntil: 'domcontentloaded' })

  const app = page.locator('.v-application').first()
  const choose = async (label: string) => {
    await page.getByRole('button', { name: /界面主题/ }).click()
    await page.getByText(label, { exact: true }).click()
  }

  await expect(app).toHaveClass(/v-theme--indigoTheme/)
  await choose('跟随系统')
  await expect(app).toHaveClass(/v-theme--indigoTheme/)
  await page.emulateMedia({ colorScheme: 'dark' })
  await expect(app).toHaveClass(/v-theme--indigoDarkTheme/)
  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(app).toHaveClass(/v-theme--indigoDarkTheme/)
  await expect(page.getByRole('button', { name: '界面主题：跟随系统' })).toBeVisible()

  await choose('浅色')
  await expect(app).toHaveClass(/v-theme--indigoTheme/)
  await page.emulateMedia({ colorScheme: 'light' })
  await page.emulateMedia({ colorScheme: 'dark' })
  await expect(app).toHaveClass(/v-theme--indigoTheme/)
})
