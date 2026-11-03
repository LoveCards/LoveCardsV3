import { test, expect } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

test('unauthenticated users are sent to login', async ({ page }) => {
  // Visit the public route first so the browser context is initialized, then
  // clear inherited storage before entering the protected route. This is
  // more reliable than relying on an empty storageState during Nuxt HMR.
  await page.goto('./login')
  await page.evaluate(() => localStorage.clear())
  await page.goto('./apps/dashboard')
  await expect(page).toHaveURL(/\/admin\/login$/)
  await expect(page.getByText('管理端登录', { exact: true })).toBeVisible()
})

test('invalid credentials stay on login with an error', async ({ page }) => {
  await page.goto('./login')
  await page.getByLabel('账号或邮箱').fill('invalid-e2e@example.test')
  await page.getByLabel('密码').fill('invalid-password')
  await page.getByRole('button', { name: '登录' }).click()
  await expect(page.getByText('用户不存在', { exact: true })).toBeVisible()
  await expect(page).toHaveURL(/\/admin\/login$/)
})
