import { expect, test, type Page } from '@playwright/test'

test.use({ storageState: { cookies: [], origins: [] } })

async function fixture(page: Page, readonly = false) {
  await page.addInitScript(() => localStorage.setItem('token', 'config-detail-fixture'))
  const writes: string[] = []
  const fields = [{ key: 'host', label: '服务地址', type: 'text' }, { key: 'secret', label: '访问密钥', type: 'password' }]
  const params = { host: 'fixture.invalid', secret: { $secret: 'keep', configured: true } }
  await page.route('**/api/**', async route => {
    // 开发服务的模块路径也可能包含 /api/；只拦截真正的后端请求。
    if (!new URL(route.request().url()).pathname.startsWith('/api/')) return route.continue()
    const path = new URL(route.request().url()).pathname
    if (route.request().method() !== 'GET') {
      writes.push(path)
      return route.fulfill({ status: 422, json: { success: false, message: '模拟保存失败，请检查输入。', code: 422 } })
    }
    const capabilities = ['admin.access', 'config.read', 'sender.read', 'storage.read', 'captcha.read', 'theme.read', 'operations.read', 'audit.read', 'users.security.read', 'reports.read', ...(!readonly ? ['config.update', 'sender.test', 'storage.test', 'theme.update', 'operations.retry', 'users.sessions.revoke', 'reports.manage'] : [])]
    capabilities.push('communications.read')
    const values: Record<string, unknown> = {
      '/api/me': { id: 9001, username: '界面验收', roles: [], grants: capabilities.map(capability => ({ capability, scope: 'global' })), status: 0 },
      '/api/admin/config': { core: { name: '测试站点', url: 'https://fixture.invalid' }, sender_demo: params, storage_demo: params, captcha_demo: params },
      '/api/admin/sender/channels': [{ slug: 'demo', name: '测试消息渠道', fields }],
      '/api/admin/storage/channels': [{ slug: 'demo', name: '测试存储渠道', fields }],
      '/api/admin/captcha/drivers': [{ slug: 'demo', name: '测试验证驱动', fields }],
      '/api/theme/config': { id: 'demo', release: '1.0.0', config_schema: { title: { type: 'text', label: '展示标题' } }, config_values: { title: '测试主题' } },
      '/api/admin/audit-logs/1': { id: 1, actor_id: 101, action: 'admin.user.update', resource_type: 'user', resource_id: '101', request_id: 'request-'.repeat(15), correlation_id: 'correlation-'.repeat(15), payload: { before: { status: 0 }, after: { status: 2 } }, created_at: '2026-09-19 10:00:00' },
      '/api/admin/operations/deliveries/1': { id: 1, event_id: 3, event_type: 'account.updated', handler_key: 'notifications.account', queue_name: 'default', execution_mode: 'async', status: 'dead', attempt_count: 3, last_error: '测试错误说明。'.repeat(20), created_at: '2026-09-19 10:00:00', updated_at: '2026-09-19 10:01:00', available_at: '2026-09-19 10:00:00' },
      '/api/admin/users/101/security': { user: { id: 101, username: '林晓', number: 'fixture_101', status: 2, security_version: 1 }, session_mutation_allowed: true, recovery_challenges: 0 },
      '/api/admin/users/101/sessions': [],
      '/api/admin/reports/1': { id: 1, reporter_id: 101, target_type: 'card', target_id: 3, reason: '内容不当', details: '测试举报上下文', status: 'open', events: [] },
    }
    if (!(path in values)) return route.fulfill({ json: { success: true, data: [] } })
    return route.fulfill({ json: { success: true, data: values[path] } })
  })
  return writes
}

const surfaces: Array<[string, string]> = [
  ['settings/site', '站点地址'],
  ['settings/community-rules', '卡片图集上限'],
  ['settings/messaging/demo', '服务地址'],
  ['settings/storage/demo', '服务地址'],
  ['settings/verification/demo', '服务地址'],
  ['settings/appearance/themes/demo', '展示标题'],
  ['operations/audit/1', '状态变化'],
  ['operations/deliveries/1', '最后失败信息'],
  ['users/101/security', '已冻结'],
  ['community/reports/1', '测试举报上下文'],
]

for (const width of [1440, 390]) {
  for (const [path, content] of surfaces) {
    test(`config and detail surface at ${width}px: ${path}`, async ({ page }, testInfo) => {
      const writes = await fixture(page)
      const errors: string[] = []
      page.on('pageerror', error => errors.push(error.message))
      await page.setViewportSize({ width, height: 900 })
      await page.goto(`./apps/${path}`)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 15_000 })
      await expect(page.locator('.v-skeleton-loader')).toHaveCount(0, { timeout: 20_000 })
      await expect(page.locator('main')).toContainText(content)
      expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
      expect(errors).toEqual([])
      expect(writes).toEqual([])
      await page.screenshot({ path: testInfo.outputPath('surface.png'), fullPage: true })
    })
  }
}

test('channel edits use one dirty status, block testing, and preserve failed input', async ({ page }) => {
  const writes = await fixture(page)
  await page.goto('./apps/settings/messaging/demo')
  await expect(page.getByRole('textbox', { name: /访问密钥/ })).toHaveValue('', { timeout: 15_000 })
  await page.getByRole('textbox', { name: /服务地址/ }).fill('edited.invalid')
  await expect(page.getByRole('status')).toHaveText('渠道配置有未保存修改')
  await expect(page.getByRole('button', { name: '测试发信', exact: true })).toBeDisabled()
  await page.getByRole('button', { name: '保存渠道配置', exact: true }).click()
  await expect(page.getByText('模拟保存失败，请检查输入。', { exact: true })).toBeVisible()
  await expect(page.getByRole('textbox', { name: /服务地址/ })).toHaveValue('edited.invalid')
  await page.getByRole('link', { name: '站点信息', exact: true }).click()
  await expect(page.getByRole('dialog', { name: '有未保存的修改' })).toBeVisible()
  await page.getByRole('button', { name: '继续编辑', exact: true }).click()
  await expect(page).toHaveURL(/messaging\/demo/)
  expect(writes).toEqual(['/api/admin/config'])
})

test('site and community rules pages keep independent dirty state and save scope', async ({ page }) => {
  const writes = await fixture(page)
  await page.goto('./apps/settings/site')
  await page.getByRole('textbox', { name: /站点名称/ }).fill('未保存名称')
  await expect(page.getByRole('status')).toHaveText('站点信息有未保存修改', { timeout: 15_000 })
  await expect(page.getByRole('button', { name: '保存站点信息', exact: true })).toBeEnabled()

  // 拆分后社区规则是独立页面：站内离开需经过未保存守卫，放弃后进入的页面没有任何脏状态。
  await page.getByRole('link', { name: '社区规则', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '有未保存的修改' })
  await expect(dialog).toBeVisible()
  await dialog.getByRole('button', { name: '放弃修改', exact: true }).click()
  await expect(page).toHaveURL(/settings\/community-rules$/)
  await expect(page.getByRole('button', { name: '保存社区规则', exact: true })).toBeDisabled()
  await expect(page.getByText('有未保存修改')).toHaveCount(0)

  // 返回站点信息页时重新加载原值：两页不共享草稿，写入始终为零。
  await page.getByRole('link', { name: '站点信息', exact: true }).click()
  await expect(page.getByRole('textbox', { name: /站点名称/ })).not.toHaveValue('未保存名称')
  expect(writes).toEqual([])
})

test('read-only channel shows no save or external test actions', async ({ page }) => {
  const writes = await fixture(page, true)
  await page.goto('./apps/settings/messaging/demo')
  await expect(page.getByRole('textbox', { name: /服务地址/ })).toBeDisabled({ timeout: 15_000 })
  await expect(page.getByRole('button', { name: '保存渠道配置', exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: '测试发信', exact: true })).toHaveCount(0)
  expect(writes).toEqual([])
})

test('detail errors can be retried without showing an empty success state', async ({ page }) => {
  await fixture(page)
  let failed = false
  await page.route('**/api/admin/audit-logs/1', async route => {
    if (failed) return route.fallback()
    failed = true
    return route.fulfill({ status: 503, json: { success: false, message: '模拟服务暂不可用', code: 503 } })
  })
  await page.goto('./apps/operations/audit/1')
  await expect(page.getByRole('alert')).toContainText('模拟服务暂不可用')
  await page.getByRole('button', { name: '重试', exact: true }).click()
  await expect(page.getByRole('heading', { name: '状态变化', exact: true })).toBeVisible()
})

test('missing audit detail retains the return route', async ({ page }) => {
  await fixture(page)
  await page.route('**/api/admin/audit-logs/1', route => route.fulfill({ status: 404, json: { success: false, message: '记录不存在', code: 404 } }))
  await page.goto('./apps/operations/audit/1')
  await expect(page.getByText('审计记录不存在', { exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: '返回列表', exact: true })).toHaveAttribute('href', '/admin/apps/operations/audit')
})

test('forbidden page has one unambiguous return action', async ({ page }) => {
  await fixture(page, true)
  await page.goto('./apps/access/roles')
  await expect(page.getByRole('heading', { name: '无权访问', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: '前往可访问页面', exact: true })).toHaveCount(1)
})
