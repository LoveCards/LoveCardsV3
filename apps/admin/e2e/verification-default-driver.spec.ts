import { expect, test } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

test('verification driver selection reads and writes the configured key', async ({ page }) => {
  test.setTimeout(120_000)
  await page.addInitScript(() => localStorage.setItem('token', 'verification-default-fixture'))
  let selected = 'gd_image'
  let revision = 1
  const writes: unknown[] = []
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (route.request().method() !== 'GET') {
      const body = route.request().postDataJSON()
      writes.push(body)
      expect(body.captcha.__revision).toBe(revision)
      selected = body.captcha?.default_captcha_driver ?? selected
      revision++
      return route.fulfill({ json: { success: true, data: { captcha: { __revision: revision, default_captcha_driver: selected, captcha_enabled: false, code_enabled: true, code_channel: 'smtp' } } } })
    }
    const data: Record<string, unknown> = {
      '/api/me': { id: 9001, username: '界面验收', roles: [], grants: ['admin.access', 'config.read', 'config.update', 'captcha.read'].map(capability => ({ capability, scope: 'global' })), status: 0 },
      '/api/admin/config': {
        user: { __revision: 1, captcha: false },
        captcha: { __revision: revision, default_captcha_driver: selected, captcha_enabled: false, code_enabled: true, code_channel: 'smtp' },
      },
      '/api/captcha/config': { human_ready: true },
      '/api/admin/captcha/drivers': [
        { slug: 'geetest_v4', type: 'captcha', name: '极验验证 v4', fields: [] },
        { slug: 'gd_image', type: 'captcha', name: '图片验证码', fields: [] },
      ],
    }
    return route.fulfill({ json: { success: true, data: data[path] ?? [] } })
  })

  await page.goto('./apps/settings/verification')
  await page.getByRole('tab', { name: '驱动管理' }).click()
  const imageCard = page.locator('.v-card').filter({ hasText: '图片验证码' })
  await expect(imageCard.getByText('默认', { exact: true })).toBeVisible({ timeout: 120_000 })
  const geetestCard = page.locator('.v-card').filter({ hasText: '极验验证 v4' })
  await geetestCard.getByRole('button', { name: '设为默认' }).click()
  await expect(geetestCard.getByText('默认', { exact: true })).toBeVisible()
  expect(writes).toEqual([{ captcha: { __revision: 1, default_captcha_driver: 'geetest_v4' } }])

})

test('verification requirements save user and captcha groups together', async ({ page }) => {
  test.setTimeout(120_000)
  await page.addInitScript(() => localStorage.setItem('token', 'verification-requirements-fixture'))
  let committed: Record<string, unknown> | null = null
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (route.request().method() === 'POST') {
      committed = route.request().postDataJSON() as Record<string, unknown>
      return route.fulfill({ json: { success: true, data: {
        user: { __revision: 4, registration_mode: 'email_or_phone', captcha: true, registration_sms_verification: true },
        captcha: { __revision: 8, captcha_enabled: true, code_enabled: true, code_channel: 'smtp' },
      } } })
    }
    const data: Record<string, unknown> = {
      '/api/me': { id: 9001, username: '界面验收', roles: [], grants: ['admin.access', 'config.read', 'config.update', 'captcha.read'].map(capability => ({ capability, scope: 'global' })), status: 0 },
      '/api/admin/config': {
        user: { __revision: 3, captcha: false },
        captcha: { __revision: 7, captcha_enabled: false, code_enabled: true, code_channel: 'smtp' },
      },
      '/api/admin/captcha/drivers': [{ slug: 'gd_image', type: 'captcha', name: '图片验证码', fields: [] }],
      '/api/captcha/config': { human_ready: true },
    }
    return route.fulfill({ json: { success: true, data: data[path] ?? [] } })
  })

  await page.goto('./apps/settings/verification')
  await expect(page.getByText('登录：人机验证')).toBeVisible({ timeout: 120_000 })
  await page.getByRole('tab', { name: '验证要求' }).click()
  const requirements = page.getByRole('region', { name: '站点验证要求' })
  for (const name of ['注册邮件验证', '注册短信验证', '注册与卡片写入的人机验证']) {
    const select = requirements.getByRole('combobox', { name, exact: true })
    await select.press('ArrowDown')
    await expect(select).toHaveAttribute('aria-controls', /.+/)
    const menuId = await select.getAttribute('aria-controls')
    await page.locator(`[id="${menuId}"]`).getByRole('option', { name: '开启', exact: true }).click()
  }
  await page.getByRole('button', { name: '保存站点验证要求' }).click()
  await expect(page.getByText('站点验证要求已保存。')).toBeVisible()
  expect(committed).toEqual({ user: { registration_mode: 'email_or_phone', captcha: true, registration_sms_verification: true, __revision: 3 }, captcha: { captcha_enabled: true, __revision: 7 } })
})

test('site access remains editable without captcha read permission', async ({ page }) => {
  test.setTimeout(120_000)
  await page.addInitScript(() => localStorage.setItem('token', 'site-access-fixture'))
  let committed: unknown = null
  let captchaRequests = 0
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (path.startsWith('/api/captcha/')) captchaRequests++
    if (route.request().method() === 'POST') {
      committed = route.request().postDataJSON()
      return route.fulfill({ json: { success: true, data: { core: { __revision: 2, visitor_mode: true } } } })
    }
    const data: Record<string, unknown> = {
      '/api/me': { id: 9001, username: '站点编辑', roles: [], grants: ['admin.access', 'config.read', 'config.update'].map(capability => ({ capability, scope: 'global' })), status: 0 },
      '/api/admin/config': { core: { __revision: 1, visitor_mode: false } },
    }
    return route.fulfill({ json: { success: true, data: data[path] ?? [] } })
  })

  await page.goto('./apps/settings/site/policies?tab=access')
  await expect(page.getByRole('tab', { name: '访问设置' })).toBeVisible({ timeout: 120_000 })
  await expect(page.getByText('当前账号无权查看验证设置。')).toBeVisible()
  await expect(page.getByRole('link', { name: '前往验证中心' })).toHaveCount(0)
  await page.getByRole('region', { name: '访问设置' }).getByRole('combobox', { name: '访客浏览', exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: '开启' }).click()
  await page.getByRole('button', { name: '保存访问设置' }).click()
  await expect(page.getByText('访问设置已保存。')).toBeVisible()
  expect(committed).toEqual({ core: { visitor_mode: true, __revision: 1 } })
  expect(captchaRequests).toBe(0)
})

test('verification conflict preserves unsaved requirements', async ({ page }) => {
  test.setTimeout(120_000)
  await page.addInitScript(() => localStorage.setItem('token', 'verification-conflict-fixture'))
  let writes = 0
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (route.request().method() === 'POST') {
      writes++
      return route.fulfill({ status: 409, json: { success: false, code: 409, message: '配置版本冲突' } })
    }
    const data: Record<string, unknown> = {
      '/api/me': { id: 9001, username: '界面验收', roles: [], grants: ['admin.access', 'config.read', 'config.update', 'captcha.read'].map(capability => ({ capability, scope: 'global' })), status: 0 },
      '/api/admin/config': { user: { __revision: 3, captcha: false }, captcha: { __revision: 7, captcha_enabled: false, code_enabled: true, code_channel: 'smtp' } },
      '/api/admin/captcha/drivers': [{ slug: 'gd_image', type: 'captcha', name: '图片验证码', fields: [] }],
      '/api/captcha/config': { human_ready: true },
      '/api/session/policy': { registration: { enabled: true, account_types: ['email', 'phone'], email_verification_required: false, sms_verification_required: false } },
    }
    return route.fulfill({ json: { success: true, data: data[path] ?? [] } })
  })

  await page.goto('./apps/settings/verification')
  await page.getByRole('tab', { name: '验证要求' }).click()
  const select = page.getByRole('region', { name: '站点验证要求' }).getByRole('combobox', { name: '注册邮件验证', exact: true })
  await select.press('ArrowDown')
  await page.getByRole('option', { name: '开启' }).click()
  await page.getByRole('button', { name: '保存站点验证要求' }).click()
  await expect(page.getByText('配置已被其他管理员修改。当前编辑已保留，请重新加载并核对后再保存。')).toBeVisible()
  await expect(select).toHaveValue('开启')
  await expect(page.getByRole('button', { name: '保存站点验证要求' })).toBeEnabled()
  await page.getByRole('tab', { name: '使用场景' }).click()
  const dialog = page.getByRole('dialog', { name: '有未保存的修改' })
  await expect(dialog).toBeVisible()
  await dialog.getByRole('button', { name: '放弃修改' }).click()
  await expect(page.getByRole('tab', { name: '使用场景' })).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByText('以下状态以最近一次读取或保存的配置为准；未保存的修改尚未生效。')).toHaveCount(0)
  await expect(page.locator('.v-list-item').filter({ hasText: '新用户注册：邮件验证码' })).toContainText('无需验证码')
  expect(writes).toBe(1)
})

test('verification draft is protected when switching tabs', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('token', 'verification-draft-fixture'))
  const grants = ['admin.access', 'config.read', 'config.update', 'captcha.read']
    .map(capability => ({ capability, scope: 'global' }))
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    const data: Record<string, unknown> = {
      '/api/me': { id: 9001, username: '配置管理员', roles: [], grants, status: 0 },
      '/api/admin/config': {
        user: { __revision: 1, captcha: false },
        captcha: { __revision: 1, captcha_enabled: false, code_enabled: true, code_channel: 'smtp' },
      },
      '/api/captcha/config': { human_ready: true },
      '/api/admin/captcha/drivers': [],
    }
    await route.fulfill({ json: { success: true, data: data[path] ?? [] } })
  })
  await page.goto('./apps/settings/verification')
  await page.getByRole('tab', { name: '服务设置' }).click()
  const service = page.locator('.v-select').filter({ hasText: '验证码服务' }).first()
  await service.click()
  await page.getByRole('option', { name: '关闭' }).click()
  await page.getByRole('tab', { name: '使用场景' }).click()
  await expect(page.getByRole('dialog', { name: '有未保存的修改' })).toBeVisible()
  await page.getByRole('button', { name: '继续编辑' }).click()
  await expect(page.getByRole('tab', { name: '服务设置' })).toHaveAttribute('aria-selected', 'true')
  await page.getByRole('tab', { name: '使用场景' }).click()
  await page.getByRole('button', { name: '放弃修改' }).click()
  await expect(page.getByRole('tab', { name: '使用场景' })).toHaveAttribute('aria-selected', 'true')
  await page.getByRole('tab', { name: '服务设置' }).click()
  await expect(page.getByRole('combobox', { name: '验证码服务' })).toHaveValue('开启')
})
