import { expect, test, type Page } from '@playwright/test'

async function mockRole(page: Page, capabilities: string[]) {
  await page.addInitScript(() => localStorage.setItem('token', 'admin-workflows-fixture'))
  const grants = ['admin.access', ...capabilities].map(capability => ({ capability, scope: 'global' }))
  await page.route('**/api/me', route => route.fulfill({ json: { success: true, data: { id: 23, username: '测试管理员', status: 0, grants, roles: [] } } }))
}

test('report triage and terminal resolution use different form fields', async ({ page }) => {
  await mockRole(page, ['reports.read', 'reports.manage'])
  let status = 'open'
  const writes: Record<string, unknown>[] = []
  const report = () => ({ id: 9, target_type: 'card', target_id: '12', reporter_id: 24, status, reason: '测试举报', events: [] })
  await page.route('**/api/admin/reports/9', route => route.fulfill({ json: { success: true, data: report() } }))
  await page.route('**/api/admin/reports/9/transition', route => {
    const body = route.request().postDataJSON()
    writes.push(body)
    status = body.status
    return route.fulfill({ json: { success: true, data: { ...report(), resolution: body.resolution } } })
  })
  await page.goto('./apps/community/reports/9', { waitUntil: 'domcontentloaded' })
  await page.getByRole('button', { name: '更新处理状态' }).click()
  const dialog = page.getByRole('dialog', { name: '变更举报状态' })
  await expect(dialog.getByLabel('内部备注（选填）')).toBeVisible()
  await dialog.getByRole('button', { name: '确认' }).click()
  await expect.poll(() => writes[0]).toEqual({ status: 'reviewing', expected_status: 'open' })
  await page.getByRole('button', { name: '更新处理状态' }).click()
  await dialog.getByRole('combobox').click()
  await page.getByRole('option', { name: '已解决' }).click()
  const confirm = dialog.getByRole('button', { name: '确认' })
  await expect(confirm).toBeDisabled()
  await dialog.getByLabel('处理结论').fill('审'.repeat(256))
  await expect(confirm).toBeDisabled()
  await dialog.getByLabel('处理结论').fill('审'.repeat(255))
  await confirm.click()
  await expect.poll(() => writes[1]).toEqual({ status: 'resolved', expected_status: 'reviewing', resolution: '审'.repeat(255) })
})

test('global search opens the registration requirement directly and preserves its tab', async ({ page }) => {
  await mockRole(page, ['config.read', 'captcha.read'])
  await page.route('**/api/admin/config', route => route.fulfill({ json: { success: true, data: { captcha: { code_enabled: true, code_channel: 'sms' }, user: { captcha: true } } } }))
  await page.route('**/api/admin/captcha/drivers', route => route.fulfill({ json: { success: true, data: [] } }))
  await page.route('**/api/session/policy', route => route.fulfill({ json: { success: true, data: { registration: { email_verification_required: true, email_verification_available: false, email_verification_status: 'mail_channel_required' }, human_verification: { enabled: false, available: false } } } }))
  await page.goto('./apps/settings/verification', { waitUntil: 'domcontentloaded' })
  await expect(page.getByText('当前选择短信渠道，邮件验证码不可用').first()).toBeVisible()
  await page.getByRole('button', { name: '搜索管理页面' }).click()
  await page.getByRole('textbox', { name: '搜索页面' }).fill('注册邮件验证')
  await page.getByRole('link', { name: /注册邮件验证/ }).click()
  await expect(page).toHaveURL(/verification\?tab=requirements$/)
  await page.reload()
  await expect(page.getByRole('tab', { name: '验证要求' })).toHaveAttribute('aria-selected', 'true')
})

test('file detail links a failed cleanup task and explains the retained tombstone', async ({ page }) => {
  await mockRole(page, ['files.usage.read', 'communications.read'])
  let purged = false
  await page.route('**/api/admin/files/27/usage', route => route.fulfill({ json: { success: true, data: {
    file: { id: 27, purge_status: purged ? 'purged' : 'failed' }, references: [], orphaned: true,
    purge_task: { id: 91, status: purged ? 'completed' : 'dead' },
  } } }))
  await page.goto('./apps/settings/storage/files/27/usage', { waitUntil: 'domcontentloaded' })
  await expect(page.getByText('清理任务已失败，可在任务详情检查并重试')).toBeVisible()
  await expect(page.getByRole('link', { name: '查看清理任务' })).toHaveAttribute('href', '/admin/apps/operations/deliveries/91?file=27')
  purged = true
  await page.getByRole('button', { name: '刷新' }).click()
  await expect(page.getByText('永久清理完成，文件记录已移除')).toBeVisible()
})

test('an older theme release link requires opening the active binding', async ({ page }) => {
  await mockRole(page, ['theme.read', 'theme.update'])
  await page.route('**/api/theme/config', route => route.fulfill({ json: { success: true, data: { id: 'sample', release: '2.0', config_schema: { title: { type: 'string', label: '展示标题' } }, config_values: { title: '当前版本' } } } }))
  await page.goto('./apps/settings/appearance/themes/sample?release=1.0', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('textbox', { name: '展示标题' })).toHaveCount(0)
  await page.getByRole('link', { name: '打开当前版本' }).click()
  await expect(page).toHaveURL(/release=2\.0$/)
  await expect(page.getByRole('textbox', { name: '展示标题' })).toHaveValue('当前版本')
})

test('operations readers see the running release and plan steps without write actions', async ({ page }) => {
  await mockRole(page, ['operations.read'])
  const plan = { plan_id: 'smoke-1', release_id: 'next', target_version: '3.1.0', revision: 3, status: 'waiting', current_step: 'contract_migrations', contract_approved: false, milestones: {}, steps: [{ name: 'contract_migrations', state: 'waiting', attempts: 0 }] }
  await page.route('**/api/admin/operations/release-status', route => route.fulfill({ json: { success: true, data: { version: '3.0.0', build: 26, release_id: 'old' } } }))
  await page.route('**/api/admin/operations/upgrades?**', route => route.fulfill({ json: { success: true, data: [plan] } }))
  await page.route('**/api/admin/operations/upgrades/smoke-1', route => route.fulfill({ json: { success: true, data: plan } }))
  await page.goto('./apps/operations/upgrade', { waitUntil: 'domcontentloaded' })
  await expect(page.getByText('3.0.0', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: '上传升级包' })).toHaveCount(0)
  await page.getByRole('link', { name: '查看计划' }).click()
  await expect(page.getByRole('heading', { name: '升级计划' })).toBeVisible()
  await expect(page.getByRole('region', { name: '执行步骤' }).getByText('收缩数据库结构')).toBeVisible()
  await expect(page.getByRole('button', { name: '批准数据库收缩' })).toHaveCount(0)
  await page.getByRole('link', { name: '系统升级' }).last().click()
  await expect(page).toHaveURL(/\/operations\/upgrade$/)
})
