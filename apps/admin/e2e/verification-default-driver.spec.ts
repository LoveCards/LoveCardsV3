import { expect, test } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

test('verification driver selection reads and writes the configured key', async ({ page }) => {
  test.setTimeout(120_000)
  await page.addInitScript(() => localStorage.setItem('token', 'verification-default-fixture'))
  let selected = 'gd_image'
  const writes: unknown[] = []
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (route.request().method() !== 'GET') {
      const body = route.request().postDataJSON()
      writes.push(body)
      selected = body.captcha?.default_captcha_driver ?? selected
      return route.fulfill({ status: 204, body: '' })
    }
    const data: Record<string, unknown> = {
      '/api/me': { id: 9001, username: '界面验收', roles: [], grants: ['admin.access', 'config.read', 'config.update', 'captcha.read'].map(capability => ({ capability, scope: 'global' })), status: 0 },
      '/api/admin/config': { captcha: { default_captcha_driver: selected, captcha_enabled: false, code_enabled: true, code_channel: 'smtp' } },
      '/api/admin/captcha/drivers': [
        { slug: 'geetest_v4', type: 'captcha', name: '极验验证 v4', fields: [] },
        { slug: 'gd_image', type: 'captcha', name: '图片验证码', fields: [] },
      ],
    }
    return route.fulfill({ json: { success: true, data: data[path] ?? [] } })
  })

  await page.goto('./apps/settings/verification')
  const imageCard = page.locator('.v-card').filter({ hasText: '图片验证码' })
  await expect(imageCard.getByText('默认', { exact: true })).toBeVisible({ timeout: 120_000 })
  const geetestCard = page.locator('.v-card').filter({ hasText: '极验验证 v4' })
  await geetestCard.getByRole('button', { name: '设为默认' }).click()
  await expect(geetestCard.getByText('默认', { exact: true })).toBeVisible()
  expect(writes).toEqual([{ captcha: { default_captcha_driver: 'geetest_v4' } }])

})
