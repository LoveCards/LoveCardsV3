import { expect as baseExpect, test, type Page } from '@playwright/test'

const expect = baseExpect.configure({ timeout: 15_000 })

type Grant = { capability: string; scope: 'global' | 'all' }
const defaultGrants: Grant[] = [
  { capability: 'reports.read', scope: 'global' },
  { capability: 'reports.manage', scope: 'global' },
  { capability: 'storage.read', scope: 'global' },
  { capability: 'config.read', scope: 'global' },
  { capability: 'files.read', scope: 'all' },
  { capability: 'communications.read', scope: 'global' },
]
const channelSlug = 'local & backup'

async function mockReadOnlyAdministrator(page: Page, grants = defaultGrants) {
  await page.addInitScript(() => window.localStorage.setItem('token', 'navigation-closure-mock-token'))
  const queries = { reports: [] as URL[], files: [] as URL[], events: [] as URL[], writes: [] as string[] }
  // 整个用例只允许 mock GET；任何未预期的写入均阻断，不接触 dev 数据库。
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    if (route.request().method() !== 'GET') {
      queries.writes.push(`${route.request().method()} ${url.pathname}`)
      return route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ code: 1, message: '导航测试不允许写入' }) })
    }
    let data: unknown = []
    let paginated = false
    if (url.pathname === '/api/me') data = {
      id: 3, number: '1000000003', username: '导航测试员', email: 'navigation@example.test', phone: '', avatar: '', roles: [], roles_id: [],
      capabilities: [], grants: [{ capability: 'admin.access', scope: 'global' }, ...grants], status: 0, stats: { received_likes: 0 },
    }
    else if (url.pathname === '/api/admin/reports') {
      queries.reports.push(url)
      paginated = true
      data = ['open', 'reviewing'].map((status, index) => ({
        id: 55 + index, target_type: 'card', target_id: '482', reporter_id: 91, reason: '待办范围测试', details: '',
        status, assignee_id: null, resolution: null, created_at: '2026-11-26 09:00:00', updated_at: '2026-11-26 09:00:00',
      }))
    } else if (url.pathname === '/api/admin/storage/channels') data = [{ slug: channelSlug, name: '备份存储', configured: true, available: true }]
    else if (url.pathname === '/api/admin/storage/channel-stats') data = { [channelSlug]: { file_count: 1, total_size: 100 } }
    else if (url.pathname === '/api/admin/config') data = { storage: { __revision: 1, default: channelSlug, rate_limit_max: 10, rate_limit_window: 60, direct_upload_expire: 3600 } }
    else if (url.pathname === '/api/admin/files') { queries.files.push(url); data = []; paginated = true }
    else if (url.pathname === '/api/admin/operations/deliveries/19') data = {
      id: 19, event_id: 7, event_type: 'message.sent', display_name: '私信提醒', handler_key: 'notifications.message', queue_name: 'notifications',
      execution_mode: 'async', status: 'done', attempt_count: 1, available_at: '2026-11-26 09:00:00', created_at: '2026-11-26 09:00:00',
      updated_at: '2026-11-26 09:00:00', policy_decision: 'applied', last_error: null, correlation_id: null,
    }
    else if (url.pathname === '/api/admin/operations/events') {
      queries.events.push(url)
      paginated = true
      data = [{ id: 7, event_type: 'message.sent', event_version: 1, aggregate_type: 'message', aggregate_key: '19', occurred_at: '2026-11-26 09:00:00', created_at: '2026-11-26 09:00:00', deliveries: [] }]
    }
    const total = Array.isArray(data) ? data.length : 0
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data, ...(paginated ? { pagination: { currentPage: 1, totalPages: 1, totalItems: total, itemsPerPage: 20 } } : {}) }) })
  })
  return queries
}

async function saveView(page: Page, name: string) {
  await page.getByRole('button', { name: '视图设置', exact: true }).click()
  await page.getByText('保存当前视图', { exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '保存当前视图' })
  await dialog.getByLabel('视图名称').fill(name)
  await dialog.getByRole('button', { name: '保存', exact: true }).click()
  await expect(dialog).toBeHidden()
}

async function applyView(page: Page, name: string) {
  await page.getByRole('button', { name: '视图设置', exact: true }).click()
  await page.getByText(name, { exact: true }).click()
}

async function resetView(page: Page) {
  await page.getByRole('button', { name: '视图设置', exact: true }).click()
  await page.getByRole('button', { name: '恢复默认视图', exact: true }).click()
}

test('pending reports retain the group through refresh and saved views and clear it on reset', async ({ page }) => {
  const queries = await mockReadOnlyAdministrator(page)
  await page.goto('./apps/community/reports?status_group=pending', { waitUntil: 'domcontentloaded' })
  await expect(page.getByText('待办（待处理、处理中）', { exact: true })).toBeVisible()
  await expect.poll(() => queries.reports.at(-1)?.searchParams.get('status_group')).toBe('pending')
  expect(queries.reports.at(-1)?.searchParams.has('status')).toBe(false)
  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.getByText('待办（待处理、处理中）', { exact: true })).toBeVisible()
  await saveView(page, '我的待办举报')
  await page.getByRole('button', { name: '清除筛选', exact: true }).click()
  await expect.poll(() => queries.reports.at(-1)?.searchParams.has('status_group')).toBe(false)
  await applyView(page, '我的待办举报')
  await expect.poll(() => queries.reports.at(-1)?.searchParams.get('status_group')).toBe('pending')
  await resetView(page)
  await expect.poll(() => queries.reports.at(-1)?.searchParams.has('status_group')).toBe(false)
  await expect(page).not.toHaveURL(/status_group=/)
  expect(queries.writes).toEqual([])
})

test('storage channel navigation preserves an encoded exact filter in views and reset removes it', async ({ page }) => {
  const queries = await mockReadOnlyAdministrator(page)
  await page.goto('./apps/settings/storage', { waitUntil: 'domcontentloaded' })
  const filesLink = page.getByRole('link', { name: '查看文件', exact: true })
  await expect(filesLink).toBeVisible()
  expect(new URL(await filesLink.getAttribute('href') ?? '', page.url()).searchParams.get('channel_slug')).toBe(channelSlug)
  await filesLink.click()
  await expect.poll(() => queries.files.at(-1)?.searchParams.get('channel_slug')).toBe(channelSlug)
  await expect(page.getByText(`渠道：${channelSlug}`, { exact: true })).toBeVisible()
  expect(new URL(page.url()).searchParams.get('view')).toBe('normal')
  await saveView(page, '备份渠道文件')
  await resetView(page)
  await expect.poll(() => queries.files.at(-1)?.searchParams.has('channel_slug')).toBe(false)
  await applyView(page, '备份渠道文件')
  await expect.poll(() => queries.files.at(-1)?.searchParams.get('channel_slug')).toBe(channelSlug)
  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.getByText(`渠道：${channelSlug}`, { exact: true })).toBeVisible()
  await resetView(page)
  await expect(page).not.toHaveURL(/channel_slug=/)
  expect(queries.writes).toEqual([])
})

test('storage readers without file permission do not receive a file navigation action', async ({ page }) => {
  const queries = await mockReadOnlyAdministrator(page, defaultGrants.filter(grant => grant.capability !== 'files.read'))
  await page.goto('./apps/settings/storage', { waitUntil: 'domcontentloaded' })
  await expect(page.getByText('备份存储', { exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: '查看文件', exact: true })).toHaveCount(0)
  expect(queries.files).toEqual([])
  expect(queries.writes).toEqual([])
})

test('delivery source event navigation locates and expands the exact event and can be cleared', async ({ page }) => {
  const queries = await mockReadOnlyAdministrator(page)
  await page.goto('./apps/operations/deliveries/19', { waitUntil: 'domcontentloaded' })
  await page.getByRole('link', { name: '定位所属事件 #7', exact: true }).click()
  await expect(page).toHaveURL(/tab=events.*event_id=7/)
  await expect(page.getByLabel('事件 ID', { exact: true })).toHaveValue('7')
  await expect.poll(() => queries.events.at(-1)?.searchParams.get('event_id')).toBe('7')
  await expect(page.getByText('这条事实没有注册处理器，因此没有投递任务。')).toBeVisible()
  await page.reload({ waitUntil: 'domcontentloaded' })
  await expect(page.getByLabel('事件 ID', { exact: true })).toHaveValue('7')
  await expect(page.getByText('这条事实没有注册处理器，因此没有投递任务。')).toBeVisible()
  await page.getByRole('button', { name: '清除筛选', exact: true }).click()
  await expect.poll(() => queries.events.at(-1)?.searchParams.has('event_id')).toBe(false)
  await expect(page).not.toHaveURL(/event_id=/)
  expect(queries.writes).toEqual([])
})

test('invalid event identifiers cannot silently broaden the event query on narrow screens', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.emulateMedia({ reducedMotion: 'reduce', colorScheme: 'dark' })
  const queries = await mockReadOnlyAdministrator(page)
  await page.goto('./apps/operations/eventing?tab=events&event_id=1e2', { waitUntil: 'domcontentloaded' })
  await expect(page.getByText('事件 ID 必须是有效的正整数；请修正或清除筛选。').first()).toBeVisible()
  expect(queries.events).toEqual([])
  await page.getByLabel('事件 ID', { exact: true }).fill('7')
  await page.getByLabel('事件 ID', { exact: true }).press('Enter')
  await expect.poll(() => queries.events.at(-1)?.searchParams.get('event_id')).toBe('7')
  await expect(page.getByText('这条事实没有注册处理器，因此没有投递任务。')).toBeVisible()
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1)
  expect(overflow).toBe(false)
  expect(queries.writes).toEqual([])
})
