import { test, expect } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

test('unauthenticated users are sent to login', async ({ page }) => {
  await page.goto('/apps/dashboard')
  await expect(page).toHaveURL(/\/admin\/login$/)
  await expect(page.getByRole('heading', { name: '管理端登录' })).toBeVisible()
})

test('invalid credentials stay on login with an error', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('账号或邮箱').fill('invalid-e2e@example.test')
  await page.getByLabel('密码').fill('invalid-password')
  await page.getByRole('button', { name: '登录' }).click()
  await expect(page.getByRole('alert')).toBeVisible()
  await expect(page).toHaveURL(/\/admin\/login$/)
})
