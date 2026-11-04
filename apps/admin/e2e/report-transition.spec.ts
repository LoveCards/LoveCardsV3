import { expect, test } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

// 举报 transition 的真实写路径（审计 §5：举报处置此前无浏览器证据）。
// 与 operations-details 同款做法：mock 数据端点 + 伪造 grants，断言对话框契约、
// 确认按钮门控、成功后状态芯片刷新与失败时保留输入。
test.use({
  storageState: {
    cookies: [],
    origins: [{
      origin: adminOrigin,
      localStorage: [{ name: 'token', value: 'report-transition-mock-token' }],
    }],
  },
})

test.beforeEach(async ({ page }) => {
  await page.route('**/api/me', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          id: 3,
          number: '1000000003',
          username: '举报处置测试员',
          email: 'reports@example.test',
          phone: '',
          avatar: '',
          roles_id: [],
          roles: [],
          capabilities: [],
          grants: [
            { capability: 'admin.access', scope: 'global' },
            { capability: 'reports.read', scope: 'global' },
            { capability: 'reports.manage', scope: 'global' },
          ],
          status: 0,
          stats: { received_likes: 0 },
        },
      }),
    })
  })
})

const reportFixture = (status: string, events: unknown[] = []) => ({
  id: 55,
  target_type: 'card',
  target_id: 482,
  reporter_id: 91,
  reason: '包含骚扰内容',
  details: '连续多条评论引用了同一段私人信息',
  status,
  assignee_id: null,
  resolution: null,
  created_at: '2026-11-04 09:00:00',
  updated_at: '2026-11-04 09:00:00',
  events,
})

test('report transition moves open report to reviewing and preserves the audit copy', async ({ page }) => {
  let transitionBody: Record<string, unknown> | null = null
  await page.route('**/api/admin/reports/55/transition', async (route) => {
    transitionBody = JSON.parse(String(route.request().postData() ?? '{}'))
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: reportFixture('reviewing', [
          { id: 1, event_type: 'report.created', actor_id: 91, created_at: '2026-11-04 09:00:00', payload: { reason: '包含骚扰内容' } },
          { id: 2, event_type: 'report.status_changed', actor_id: 3, created_at: '2026-11-04 09:05:00', payload: { note: '已核实上下文，转人工跟进' } },
        ]),
      }),
    })
  })
  await page.route('**/api/admin/reports/55', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: reportFixture('open') }),
    })
  })

  await page.goto('./apps/community/reports/55', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: '举报 #55', exact: true, level: 1 })).toBeVisible()
  await expect(page.getByText('待处理', { exact: true })).toBeVisible()

  await page.getByRole('button', { name: '更新处理状态', exact: true }).click()
  const dialog = page.getByRole('dialog').filter({ hasText: '更新举报状态' })
  await expect(dialog.getByText('状态变化和原因将写入审计记录。')).toBeVisible()

  // 确认按钮在填写“处理原因或结论”前保持禁用；open 状态默认预选第一个可用流转。
  const confirm = dialog.getByRole('button', { name: '确认', exact: true })
  await expect(confirm).toBeDisabled()
  await dialog.getByRole('combobox').filter({ hasText: '目标状态' }).click()
  await page.getByRole('option', { name: '处理中', exact: true }).click()
  await dialog.getByLabel('处理原因或结论').fill('已核实上下文，转人工跟进')
  await expect(confirm).toBeEnabled()
  await confirm.click()

  await expect.poll(() => transitionBody).not.toBeNull()
  expect(transitionBody).toMatchObject({ status: 'reviewing', note: '已核实上下文，转人工跟进' })
  await expect(page.getByRole('dialog')).toBeHidden()
  await expect(page.getByText('处理中', { exact: true })).toBeVisible()
})

test('failed transition keeps the dialog open with the resolution text preserved', async ({ page }) => {
  await page.route('**/api/admin/reports/55/transition', async (route) => {
    // 与后端 ApiResponse 错误包一致（code+message），SDK 才会透传业务文案。
    await route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({ code: 1, message: '目标状态不允许从当前状态流转' }),
    })
  })
  await page.route('**/api/admin/reports/55', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: reportFixture('open') }),
    })
  })

  await page.goto('./apps/community/reports/55', { waitUntil: 'domcontentloaded' })
  await page.getByRole('button', { name: '更新处理状态', exact: true }).click()
  const dialog = page.getByRole('dialog').filter({ hasText: '更新举报状态' })
  await dialog.getByLabel('处理原因或结论').fill('这段文字必须保留')
  await dialog.getByRole('button', { name: '确认', exact: true }).click()

  // Vuetify 输入框 details 同样带 role="alert"，断言必须按文案收窄到错误 alert。
  await expect(dialog.getByRole('alert').filter({ hasText: '目标状态不允许从当前状态流转' })).toBeVisible()
  await expect(dialog.getByLabel('处理原因或结论')).toHaveValue('这段文字必须保留')
})
