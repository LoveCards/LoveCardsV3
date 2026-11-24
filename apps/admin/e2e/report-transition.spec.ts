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
  let currentStatus = 'open'
  let releaseRefresh: (() => void) | undefined
  const refreshGate = new Promise<void>(resolve => { releaseRefresh = resolve })
  await page.route('**/api/admin/reports/55/transition', async (route) => {
    transitionBody = JSON.parse(String(route.request().postData() ?? '{}'))
    currentStatus = 'reviewing'
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
    if (currentStatus === 'reviewing') await refreshGate
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: reportFixture(currentStatus) }),
    })
  })

  await page.goto('./apps/community/reports/55', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: '举报 #55', exact: true, level: 1 })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByText('待处理', { exact: true })).toBeVisible()

  await page.getByRole('button', { name: '更新处理状态', exact: true }).click()
  const processing = page.getByRole('region', { name: '处理操作' })
  await expect(processing.getByText('状态变化和原因将写入审计记录', { exact: false })).toBeVisible()

  // 确认按钮在填写“处理原因或结论”前保持禁用；open 状态默认预选第一个可用流转。
  const confirm = processing.getByRole('button', { name: '确认处理', exact: true })
  await expect(confirm).toBeDisabled()
  await processing.getByRole('combobox').filter({ hasText: '目标状态' }).click()
  await page.getByRole('option', { name: '处理中', exact: true }).click()
  await processing.getByLabel('处理原因或结论').fill('已核实上下文，转人工跟进')
  await expect(confirm).toBeEnabled()
  await confirm.click()

  const updateStatus = processing.getByRole('button', { name: '更新处理状态', exact: true })
  try {
    await expect.poll(() => transitionBody).not.toBeNull()
    expect(transitionBody).toMatchObject({ status: 'reviewing', note: '已核实上下文，转人工跟进' })
    await expect(updateStatus).toBeDisabled()
    await expect(processing.getByRole('button', { name: '追加备注', exact: true })).toBeDisabled()
  } finally {
    releaseRefresh?.()
  }
  await expect(page.getByText('处理中', { exact: true })).toBeVisible()
  await expect(updateStatus).toBeEnabled()
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
  const processing = page.getByRole('region', { name: '处理操作' })
  await processing.getByLabel('处理原因或结论').fill('这段文字必须保留')
  await processing.getByRole('button', { name: '确认处理', exact: true }).click()

  // Vuetify 输入框 details 同样带 role="alert"，断言必须按文案收窄到错误 alert。
  await expect(processing.getByRole('alert').filter({ hasText: '目标状态不允许从当前状态流转' })).toBeVisible()
  await expect(processing.getByLabel('处理原因或结论')).toHaveValue('这段文字必须保留')
})

test('private message report opens evidence only in its case dialog and preserves the list filters', async ({ page }) => {
  test.setTimeout(90_000)
  const report = { ...reportFixture('open'), target_type: 'message', target_id: '482' }
  const evidence = '仅在案件详情展示的私信证据'
  await page.route('**/api/admin/reports/55', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          ...report,
          evidence_snapshot: {
            type: 'message',
            captured: { id: 482, conversation_id: 23, sender_id: 92, body: evidence },
          },
        },
      }),
    })
  })
  await page.route('**/api/admin/reports?**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: [report],
        pagination: { currentPage: 1, totalItems: 1 },
      }),
    })
  })
  await page.goto('./apps/community/reports?status=open&resource_type=message&page=1&list_rows=20', { waitUntil: 'domcontentloaded' })
  const row = page.getByRole('row').filter({ hasText: '包含骚扰内容' })
  await expect(row).toContainText('包含骚扰内容', { timeout: 20_000 })
  await expect(page.locator('thead').getByText('ID', { exact: true })).toHaveCount(0)
  await expect(row.getByText('用户 #91', { exact: true })).toBeVisible()
  await expect(row.getByText('私信 #482', { exact: true })).toBeVisible()
  await expect(page.getByText(evidence, { exact: true })).toHaveCount(0)
  const listUrl = page.url()
  const detailRequest = page.waitForRequest(request => request.url().endsWith('/api/admin/reports/55') && request.method() === 'GET')
  await row.getByRole('button', { name: '处理', exact: true }).click()
  await detailRequest
  const dialog = page.getByRole('dialog', { name: '举报详情' })
  await expect(dialog).toBeVisible()
  await expect(dialog.getByText('私信 #482', { exact: true })).toBeVisible()
  await expect(dialog.getByRole('link', { name: '私信 #482', exact: true })).toHaveCount(0)
  await expect(dialog.getByText(evidence, { exact: true })).toBeVisible()
  await expect(dialog.getByText('此操作仅更新举报记录，私信仍可按原规则阅读。')).toBeVisible()
  await expect(dialog).toContainText('包含骚扰内容')
  await expect(dialog).toContainText('连续多条评论引用了同一段私人信息')
  await expect(page).toHaveURL(listUrl)
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await expect(page).toHaveURL(listUrl)
})

test('report processing and note scopes guard draft switches', async ({ page }) => {
  await page.route('**/api/admin/reports/55', async (route) => {
    await route.fulfill({ json: { success: true, data: reportFixture('open') } })
  })
  await page.goto('./apps/community/reports/55', { waitUntil: 'domcontentloaded' })
  await page.getByRole('button', { name: '更新处理状态', exact: true }).click()
  const processing = page.getByRole('region', { name: '处理操作' })
  await processing.getByLabel('处理原因或结论').fill('尚未提交的处理结论')
  await processing.getByRole('button', { name: '改为追加备注' }).click()
  const guard = page.getByRole('dialog', { name: '有未保存的修改' })
  await expect(guard).toBeVisible()
  await guard.getByRole('button', { name: '继续编辑' }).click()
  await expect(processing.getByLabel('处理原因或结论')).toHaveValue('尚未提交的处理结论')
  await processing.getByRole('button', { name: '改为追加备注' }).click()
  await guard.getByRole('button', { name: '放弃修改' }).click()
  await expect(processing.getByLabel('备注内容')).toBeVisible()
  await expect(processing.getByLabel('处理原因或结论')).toHaveCount(0)
})

test('report submission locks its inputs and task switching', async ({ page }) => {
  let release: (() => void) | undefined
  let submitted = false
  await page.route('**/api/admin/reports/55', route => route.fulfill({ json: { success: true, data: reportFixture('open') } }))
  await page.route('**/api/admin/reports/55/transition', async route => {
    submitted = true
    await new Promise<void>(resolve => { release = resolve })
    await route.fulfill({ status: 422, json: { code: 1, message: '模拟处理失败' } })
  })
  await page.goto('./apps/community/reports/55')
  await page.getByRole('button', { name: '更新处理状态', exact: true }).click()
  const input = page.getByRole('textbox', { name: '处理原因或结论' })
  await input.fill('处理中保持这段草稿')
  await page.getByRole('button', { name: '确认处理', exact: true }).click()
  try {
    await expect.poll(() => submitted).toBe(true)
    await expect(input).toBeDisabled()
    await expect(page.getByRole('combobox', { name: '目标状态' })).toBeDisabled()
    await expect(page.getByRole('button', { name: '改为追加备注' })).toBeDisabled()
    await expect(page.getByRole('button', { name: '取消', exact: true })).toBeDisabled()
  } finally { release?.() }
  await expect(input).toBeEnabled()
  await expect(input).toHaveValue('处理中保持这段草稿')
  await expect(page.getByText('模拟处理失败', { exact: true })).toBeVisible()
})
