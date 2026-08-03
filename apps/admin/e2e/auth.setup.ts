import { test as setup, expect } from '@playwright/test'

const authFile = 'apps/admin/test-results/.auth/admin.json'

setup('authenticate admin', async ({ page }) => {
  if (process.env.LOVECARDS_E2E_ISOLATED !== '1') {
    throw new Error('Refusing admin E2E tests: set LOVECARDS_E2E_ISOLATED=1')
  }
  if (!/^lovecards_e2e_[a-z0-9_]+$/.test(process.env.LOVECARDS_E2E_DB_NAME || '')) {
    throw new Error('Refusing admin E2E tests: E2E database name is missing or unsafe')
  }

  const account = process.env.LOVECARDS_E2E_ADMIN_ACCOUNT || process.env.LOVECARDS_SDK_TEST_ADMIN_ACCOUNT
  const password = process.env.LOVECARDS_E2E_ADMIN_PASSWORD || process.env.LOVECARDS_SDK_TEST_ADMIN_PASSWORD
  if (!account || !password) {
    throw new Error('Missing LOVECARDS_E2E_ADMIN_ACCOUNT and LOVECARDS_E2E_ADMIN_PASSWORD')
  }

  await page.goto('/login')
  await page.getByLabel('账号或邮箱').fill(account)
  await page.getByLabel('密码').fill(password)
  await page.getByRole('button', { name: '登录' }).click()
  await expect(page).toHaveURL(/\/admin\/apps\/dashboard$/)
  await expect(page.locator('body')).not.toContainText('权限不足')
  await page.context().storageState({ path: authFile })
})
