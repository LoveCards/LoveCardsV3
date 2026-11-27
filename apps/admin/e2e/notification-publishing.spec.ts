import { expect as baseExpect, test, type Page } from '@playwright/test'

const expect = baseExpect.configure({ timeout: 15_000 })
test.use({ storageState: { cookies: [], origins: [] } })
test.setTimeout(60_000)

type BatchStatus = 'prepared' | 'queued' | 'processing' | 'completed' | 'partial_failure' | 'failed'
interface NoticeBatch {
  id: number
  request_key: string
  generation: number
  audience: 'selected' | 'all'
  recipient_ids: number[]
  recipients: Array<{ id: number; username: string }>
  message: string
  action_url: string | null
  severity: 'info' | 'warning'
  status: BatchStatus
  recipient_count: number
  generated_count: number
  skipped_count: number
  pending_count: number
  failed_count: number
  created_at: string
  confirmed_at: string | null
  completed_at: string | null
  can_confirm: boolean
  can_retry: boolean
  diagnostic_event_id: number | null
}

interface PrepareBody {
  request_key: string
  audience: 'selected' | 'all'
  recipient_ids?: number[]
  message: string
  action_url?: string | null
  severity?: 'info' | 'warning'
}

const candidates = [{ id: 48, username: '朝夕' }, { id: 52, username: '山河' }]
const grants = ['notifications.read', 'communications.read', 'notifications.publish', 'notifications.broadcast']

function preparedBatch(overrides: Partial<NoticeBatch> = {}): NoticeBatch {
  return {
    id: 71, request_key: 'prepared-publication-fixture', generation: 0, audience: 'selected', recipient_ids: [48, 52],
    recipients: [...candidates], message: '今晚维护期间可能短暂无法访问，请提前保存内容。', action_url: '/settings',
    severity: 'info', status: 'prepared', recipient_count: 2, generated_count: 0, skipped_count: 0,
    pending_count: 2, failed_count: 0, created_at: '2026-11-27 08:00:00', confirmed_at: null,
    completed_at: null, can_confirm: true, can_retry: false, diagnostic_event_id: null, ...overrides,
  }
}

async function fixtures(page: Page, capabilities: string[] = grants, initialBatch: NoticeBatch | null = null) {
  const state = {
    batch: initialBatch,
    failStats: false,
    losePrepareResponse: false,
    loseConfirmResponse: false,
    prepares: [] as PrepareBody[],
    confirms: [] as number[],
    retries: [] as number[],
    retryBodies: [] as Array<{ expected_generation: number; reason: string }>,
    reads: [] as string[],
    unexpectedWrites: [] as string[],
    oldDetailReads: [] as string[],
    errors: [] as string[],
  }
  await page.addInitScript(() => { localStorage.setItem('token', 'notice-publishing-fixture') })
  page.on('pageerror', error => state.errors.push(error.message))
  await page.route('**/api/**', async route => {
    const request = route.request()
    const url = new URL(request.url())
    const method = request.method()
    if (!url.pathname.startsWith('/api/')) return route.continue()
    if (method === 'GET') state.reads.push(url.pathname + url.search)
    if (url.pathname === '/api/me') return route.fulfill({ json: { success: true, data: {
      id: 9001, username: '通知管理员', roles: [], status: 0,
      grants: [{ capability: 'admin.access', scope: 'global' }, ...capabilities.map(capability => ({ capability, scope: 'global' }))],
    } } })
    if (url.pathname === '/api/admin/operations/notifications') {
      if (state.failStats) return route.fulfill({ status: 503, json: { success: false, message: '通知统计暂不可用' } })
      return route.fulfill({ json: { success: true, data: { total: 25, unread: 9, recipients: 8,
        types: [{ type: 'content.commented', count: 14 }, { type: 'social.followed', count: 7 }, { type: 'system.notice', count: 4 }],
      } } })
    }
    if (url.pathname === '/api/admin/notifications/recipients') {
      const query = (url.searchParams.get('query') || '').toLowerCase()
      return route.fulfill({ json: { success: true, data: candidates.filter(user => `${user.username}#${user.id}`.toLowerCase().includes(query)) } })
    }
    if (url.pathname === '/api/admin/notifications/batches/latest') {
      const requestKey = url.searchParams.get('request_key')
      const batch = requestKey && state.batch?.request_key !== requestKey ? null : state.batch
      return route.fulfill({ json: { success: true, data: { batch } } })
    }
    if (url.pathname === '/api/admin/notifications/batches' && method === 'POST') {
      const body = request.postDataJSON() as PrepareBody
      state.prepares.push(body)
      state.batch = preparedBatch({ request_key: body.request_key, audience: body.audience,
        recipient_ids: body.audience === 'all' ? [] : [...new Set(body.recipient_ids)],
        recipients: body.audience === 'all' ? [] : candidates.filter(user => body.recipient_ids?.includes(user.id)),
        recipient_count: body.audience === 'all' ? 18 : new Set(body.recipient_ids).size,
        pending_count: body.audience === 'all' ? 18 : new Set(body.recipient_ids).size,
        message: body.message, action_url: body.action_url || null, severity: body.severity || 'info',
      })
      if (state.losePrepareResponse) { state.losePrepareResponse = false; return route.abort('failed') }
      return route.fulfill({ status: 201, json: { success: true, data: state.batch } })
    }
    const batchRoute = url.pathname.match(/^\/api\/admin\/notifications\/batches\/(\d+)(?:\/(confirm|retry))?$/)
    if (batchRoute) {
      const id = Number(batchRoute[1])
      if (!state.batch || state.batch.id !== id) return route.fulfill({ status: 404, json: { success: false, message: '通知发布批次不存在' } })
      if (method === 'GET' && !batchRoute[2]) return route.fulfill({ json: { success: true, data: state.batch } })
      if (method === 'POST' && batchRoute[2] === 'confirm') {
        state.confirms.push(id)
        state.batch = { ...state.batch, generation: 1, status: 'queued', can_confirm: false, confirmed_at: '2026-11-27 08:01:00', diagnostic_event_id: 301 }
        if (state.loseConfirmResponse) { state.loseConfirmResponse = false; return route.abort('failed') }
        return route.fulfill({ json: { success: true, data: state.batch } })
      }
      if (method === 'POST' && batchRoute[2] === 'retry') {
        state.retries.push(id)
        state.retryBodies.push(request.postDataJSON() as { expected_generation: number; reason: string })
        state.batch = { ...state.batch, generation: state.batch.generation + 1, status: 'queued', pending_count: state.batch.failed_count, failed_count: 0, can_retry: false }
        return route.fulfill({ json: { success: true, data: state.batch } })
      }
    }
    if (url.pathname === '/api/admin/notifications' || /^\/api\/admin\/notifications\/\d+$/.test(url.pathname)) {
      state.oldDetailReads.push(url.pathname)
      return route.fulfill({ status: 410, json: { success: false, message: '通知明细入口已移除' } })
    }
    if (method !== 'GET') { state.unexpectedWrites.push(`${method} ${url.pathname}`); return route.abort('failed') }
    return route.fulfill({ json: { success: true, data: [] } })
  })
  return state
}

async function selectRecipient(page: Page, username: string, id: number) {
  const input = page.getByRole('combobox', { name: '搜索并选择用户', exact: true })
  await input.fill(username)
  await page.getByRole('option', { name: new RegExp(`${username}\\s*#${id}`) }).click()
  await input.press('Escape')
}

async function fillSelectedNotice(page: Page) {
  await page.getByRole('tab', { name: '发送系统通知', exact: true }).click()
  await expect(page.getByTestId('notice-compose')).toBeVisible()
  await selectRecipient(page, '朝夕', 48)
  await selectRecipient(page, '山河', 52)
  await page.getByLabel('正文', { exact: true }).fill('今晚维护期间可能短暂无法访问，请提前保存内容。')
  await page.getByLabel('站内跳转（可选）', { exact: true }).fill('/settings')
}

async function confirmPublication(page: Page) {
  await page.getByRole('button', { name: '确认发送', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '确认发送系统通知', exact: true })
  await expect(dialog).toBeVisible()
  await dialog.getByRole('button', { name: '确认发送', exact: true }).click()
  await expect(dialog).not.toBeVisible()
}

test('notification overview shows aggregate facts without ordinary recipient records', async ({ page }) => {
  const state = await fixtures(page, ['notifications.read'])
  await page.goto('./apps/operations/notifications')
  const overview = page.getByTestId('notice-overview')
  await expect(overview).toBeVisible()
  await expect(overview).toContainText('25')
  await expect(overview).toContainText('16')
  await expect(overview).toContainText('9')
  await expect(overview).toContainText('8')
  await expect(page.getByRole('tab', { name: '发送系统通知', exact: true })).toHaveCount(0)
  await expect(page.getByRole('columnheader', { name: /接收用户|已读状态|操作/ })).toHaveCount(0)
  expect(state.oldDetailReads).toEqual([])
  expect(state.prepares).toEqual([])
  expect(state.confirms).toEqual([])
  expect(state.unexpectedWrites).toEqual([])
  expect(state.errors).toEqual([])
})

test('existing communications reader can open statistics without acquiring publishing access', async ({ page }) => {
  const state = await fixtures(page, ['communications.read'])
  await page.goto('./apps/operations/notifications?view=send')
  await expect(page.getByTestId('notice-overview')).toBeVisible()
  await expect(page.getByTestId('notice-compose')).not.toBeVisible()
  expect(state.reads.some(url => url.includes('/recipients') || url.includes('/batches'))).toBe(false)
  expect(state.unexpectedWrites).toEqual([])
})

test('notification statistics keep a marked snapshot on refresh failure', async ({ page }) => {
  const state = await fixtures(page, ['notifications.read'])
  await page.goto('./apps/operations/notifications')
  await expect(page.getByTestId('notice-overview')).toContainText('25')
  state.failStats = true
  await page.getByRole('button', { name: '刷新', exact: true }).click()
  await expect(page.getByTestId('notice-overview')).toContainText('25')
  await expect(page.locator('main')).toContainText(/上次|旧数据|未能更新|统计暂不可用/)
  state.failStats = false
  await page.getByRole('button', { name: '刷新', exact: true }).click()
  await expect(page.getByTestId('notice-overview')).toContainText('25')
  expect(state.errors).toEqual([])
})

test('selected recipients freeze in preview and only publish after explicit confirmation', async ({ page }) => {
  const state = await fixtures(page)
  await page.goto('./apps/operations/notifications')
  await fillSelectedNotice(page)
  await page.getByRole('button', { name: '预览收件范围', exact: true }).click()
  await expect(page.getByTestId('notice-preview')).toBeVisible()
  await expect(page.getByTestId('notice-preview')).toContainText('今晚维护期间可能短暂无法访问')
  expect(state.prepares).toHaveLength(1)
  expect(state.prepares[0]?.recipient_ids).toEqual([48, 52])
  expect(state.confirms).toEqual([])
  await confirmPublication(page)
  const receipt = page.getByTestId('notice-receipt')
  await expect(receipt).toBeVisible()
  await expect(page).toHaveURL(/batch=71/)
  await expect(receipt).not.toContainText('已送达')
  expect(state.confirms).toEqual([71])
  expect(state.unexpectedWrites).toEqual([])
  state.batch = { ...state.batch!, status: 'completed', generated_count: 2, pending_count: 0, completed_at: '2026-11-27 08:02:00' }
  await page.getByRole('button', { name: '刷新结果', exact: true }).click()
  await expect(receipt).toContainText(/已完成|处理完成/)
  expect(state.confirms).toEqual([71])
  expect(state.errors).toEqual([])
})

test('publication task screenshots cover the editable form and narrow failure receipt', async ({ page }, testInfo) => {
  const state = await fixtures(page)
  await page.setViewportSize({ width: 1440, height: 960 })
  await page.goto('./apps/operations/notifications')
  await expect(page.getByTestId('notice-overview')).toBeVisible()
  await page.screenshot({ path: testInfo.outputPath('overview-desktop.png'), fullPage: true, animations: 'disabled' })
  await fillSelectedNotice(page)
  await page.screenshot({ path: testInfo.outputPath('compose-desktop.png'), fullPage: true, animations: 'disabled' })
  state.batch = preparedBatch({ generation: 1, status: 'partial_failure', can_confirm: false, can_retry: true,
    generated_count: 1, failed_count: 1, pending_count: 0, confirmed_at: '2026-11-27 08:01:00', diagnostic_event_id: 301 })
  await page.addInitScript(() => localStorage.setItem('lovecards.admin.theme', 'dark'))
  for (const width of [390, 320]) {
    await page.setViewportSize({ width, height: 960 })
    await page.goto('./apps/operations/notifications?view=send&batch=71')
    await expect(page.getByTestId('notice-receipt')).toBeVisible()
    await expect(page.getByTestId('notice-receipt')).not.toContainText('尚未生成的通知保留在')
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
    await page.screenshot({ path: testInfo.outputPath(`receipt-dark-${width}.png`), fullPage: true, animations: 'disabled' })
  }
})

test('publisher without broadcast permission cannot select all users', async ({ page }) => {
  const state = await fixtures(page, ['notifications.read', 'notifications.publish'])
  await page.goto('./apps/operations/notifications?view=send')
  await expect(page.getByTestId('notice-compose')).toBeVisible()
  await page.getByRole('combobox', { name: '收件范围', exact: true }).press('ArrowDown')
  await expect(page.getByRole('option', { name: '全站用户', exact: true })).toHaveCount(0)
  await page.keyboard.press('Escape')
  expect(state.prepares).toEqual([])
})

test('all-user publication uses one frozen batch rather than one browser request per recipient', async ({ page }) => {
  const state = await fixtures(page)
  await page.goto('./apps/operations/notifications?view=send')
  await page.getByRole('combobox', { name: '收件范围', exact: true }).press('ArrowDown')
  await page.getByRole('option', { name: '全站用户', exact: true }).click()
  await page.getByLabel('正文', { exact: true }).fill('全站维护安排，请保存当前编辑。')
  await page.getByRole('button', { name: '预览收件范围', exact: true }).click()
  await expect(page.getByTestId('notice-preview')).toContainText('18')
  expect(state.prepares).toHaveLength(1)
  expect(state.prepares[0]?.audience).toBe('all')
  expect(state.prepares[0]?.recipient_ids ?? []).toEqual([])
  expect(state.confirms).toEqual([])
  await confirmPublication(page)
  await expect(page.getByTestId('notice-receipt')).toBeVisible()
  expect(state.confirms).toEqual([71])
  expect(state.prepares).toHaveLength(1)
  expect(state.unexpectedWrites).toEqual([])
})

test('lost prepare response is recovered by the same request key before publishing', async ({ page }) => {
  const state = await fixtures(page)
  state.losePrepareResponse = true
  await page.goto('./apps/operations/notifications')
  await fillSelectedNotice(page)
  await page.getByRole('button', { name: '预览收件范围', exact: true }).click()
  await expect(page.getByRole('button', { name: '重新核对', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: '重新核对', exact: true })).toBeFocused()
  expect(state.prepares).toHaveLength(1)
  expect(state.confirms).toEqual([])
  await page.getByRole('button', { name: '重新核对', exact: true }).click()
  await expect(page.getByTestId('notice-preview')).toBeVisible()
  expect(state.reads.some(url => url.includes(`request_key=${encodeURIComponent(state.prepares[0]!.request_key)}`))).toBe(true)
  expect(state.prepares).toHaveLength(1)
  expect(state.confirms).toEqual([])
})

test('lost confirmation response is verified without a duplicate publish request', async ({ page }) => {
  const state = await fixtures(page)
  state.loseConfirmResponse = true
  await page.goto('./apps/operations/notifications')
  await fillSelectedNotice(page)
  await page.getByRole('button', { name: '预览收件范围', exact: true }).click()
  await confirmPublication(page)
  await expect(page.getByRole('button', { name: '重新核对', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: '重新核对', exact: true })).toBeFocused()
  expect(state.confirms).toEqual([71])
  await page.getByRole('button', { name: '重新核对', exact: true }).click()
  await expect(page.getByTestId('notice-receipt')).toBeVisible()
  expect(state.confirms).toEqual([71])
  expect(state.prepares).toHaveLength(1)
})

test('confirmation can be cancelled by keyboard without issuing a publication', async ({ page }) => {
  const state = await fixtures(page, grants, preparedBatch())
  await page.goto('./apps/operations/notifications?view=send&batch=71')
  const trigger = page.getByRole('button', { name: '确认发送', exact: true })
  await trigger.click()
  const dialog = page.getByRole('dialog', { name: '确认发送系统通知', exact: true })
  await expect(dialog).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(dialog).not.toBeVisible()
  await expect(trigger).toBeFocused()
  expect(state.confirms).toEqual([])
})

test('batch reference survives reload without storing notification body in local storage', async ({ page }) => {
  const state = await fixtures(page, grants, preparedBatch())
  await page.goto('./apps/operations/notifications?view=send&batch=71')
  await expect(page.getByTestId('notice-preview')).toContainText('今晚维护期间可能短暂无法访问')
  await page.reload()
  await expect(page.getByTestId('notice-preview')).toContainText('今晚维护期间可能短暂无法访问')
  const persisted = await page.evaluate(() => JSON.stringify({ ...localStorage }))
  expect(persisted).not.toContain('今晚维护期间')
  expect(state.prepares).toEqual([])
  expect(state.confirms).toEqual([])
})

test('returning from a frozen preview changes the request key only for a new preview', async ({ page }) => {
  const original = preparedBatch()
  const state = await fixtures(page, grants, original)
  await page.goto('./apps/operations/notifications?view=send&batch=71')
  await expect(page.getByTestId('notice-preview')).toBeVisible()
  await page.getByRole('button', { name: '返回修改', exact: true }).click()
  await expect(page.getByLabel('正文', { exact: true })).toHaveValue(original.message)
  await page.getByLabel('正文', { exact: true }).fill('这是修改后重新预览的内容。')
  await expect(page.getByRole('button', { name: '刷新', exact: true })).toBeDisabled()
  await page.getByRole('button', { name: '预览收件范围', exact: true }).click()
  await expect(page.getByTestId('notice-preview')).toContainText('这是修改后重新预览的内容。')
  expect(state.prepares).toHaveLength(1)
  expect(state.prepares[0]?.request_key).not.toBe(original.request_key)
  expect(state.confirms).toEqual([])
})

test('new compose does not persist body while statistics tab preserves the in-memory draft', async ({ page }) => {
  const state = await fixtures(page)
  await page.goto('./apps/operations/notifications')
  await fillSelectedNotice(page)
  await page.getByRole('tab', { name: '概览', exact: true }).click()
  await expect(page.getByTestId('notice-overview')).toBeVisible()
  await page.getByRole('tab', { name: '发送系统通知', exact: true }).click()
  await expect(page.getByLabel('正文', { exact: true })).toHaveValue('今晚维护期间可能短暂无法访问，请提前保存内容。')
  expect(await page.evaluate(() => JSON.stringify({ ...localStorage, ...sessionStorage }))).not.toContain('今晚维护期间')
  expect(state.prepares).toEqual([])
  expect(state.confirms).toEqual([])
})

test('a missing or foreign batch cannot turn into a new publication automatically', async ({ page }) => {
  const state = await fixtures(page, grants, preparedBatch())
  await page.goto('./apps/operations/notifications?view=send&batch=999')
  await expect(page.locator('main')).toContainText('发送批次不存在或无权查看')
  expect(state.prepares).toEqual([])
  expect(state.confirms).toEqual([])
  expect(state.retries).toEqual([])
})

test('retrying failed work preserves the batch and already generated notification count', async ({ page }) => {
  const state = await fixtures(page, grants, preparedBatch({ generation: 1, status: 'partial_failure', can_confirm: false,
    can_retry: true, recipient_count: 2, generated_count: 1, pending_count: 0, failed_count: 1,
    confirmed_at: '2026-11-27 08:01:00', diagnostic_event_id: 301,
  }))
  await page.goto('./apps/operations/notifications?view=send&batch=71')
  await expect(page.getByTestId('notice-receipt')).toBeVisible()
  await expect(page.getByRole('button', { name: '重试失败任务', exact: true })).toBeDisabled()
  await page.getByLabel('重试原因', { exact: true }).fill('基础故障已恢复，继续本批次失败任务。')
  await page.getByRole('button', { name: '重试失败任务', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '重试失败任务', exact: true })
  await dialog.getByRole('button', { name: '确认重试', exact: true }).click()
  await expect(dialog).not.toBeVisible()
  await expect(page.getByTestId('notice-receipt')).toContainText(/等待|处理中|已受理/)
  expect(state.retries).toEqual([71])
  expect(state.retryBodies).toEqual([{ expected_generation: 1, reason: '基础故障已恢复，继续本批次失败任务。' }])
  expect(state.batch?.generated_count).toBe(1)
  expect(state.prepares).toEqual([])
  expect(state.confirms).toEqual([])
})

test('fanout failure explains why pending recipients do not mean active processing', async ({ page }) => {
  await fixtures(page, grants, preparedBatch({ generation: 1, status: 'failed', can_confirm: false, can_retry: true,
    generated_count: 0, failed_count: 0, pending_count: 2, diagnostic_event_id: 301, confirmed_at: '2026-11-27 08:01:00',
  }))
  await page.goto('./apps/operations/notifications?view=send&batch=71')
  const receipt = page.getByTestId('notice-receipt')
  await expect(receipt).toBeVisible()
  await expect(receipt).toContainText('处理失败')
  await expect(page.locator('main')).toContainText(/分发|失败|未完成/)
  await expect(receipt).not.toContainText('后台仍在处理')
})

for (const [width, theme] of [[1440, 'light'], [960, 'light'], [720, 'light'], [1440, 'dark'], [390, 'dark'], [320, 'dark']] as const) {
  test(`notification overview and preview reflow with ${theme} theme at ${width}px`, async ({ page }, info) => {
    const state = await fixtures(page, grants, preparedBatch())
    await page.addInitScript(value => localStorage.setItem('lovecards.admin.theme', value), theme)
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize({ width, height: 960 })
    await page.goto('./apps/operations/notifications')
    await expect(page.getByTestId('notice-overview')).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
    await page.screenshot({ path: info.outputPath('notifications-overview.png'), fullPage: true, animations: 'disabled' })
    await page.goto('./apps/operations/notifications?view=send&batch=71')
    await expect(page.getByTestId('notice-preview')).toContainText('今晚维护期间')
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
    await page.screenshot({ path: info.outputPath('notifications-preview.png'), fullPage: true, animations: 'disabled' })
    expect(state.unexpectedWrites).toEqual([])
    expect(state.errors).toEqual([])
  })
}
