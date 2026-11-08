import { test, expect, type Page } from '@playwright/test'

const routes = [
  '/apps/dashboard',
  '/apps/content/cards',
  '/apps/content/comments',
  '/apps/content/tags',
  '/apps/users',
  '/apps/access/roles',
  '/apps/access/permissions',
  '/apps/settings/storage/files',
  '/apps/settings/storage',
  '/apps/settings/messaging',
  '/apps/settings/verification',
  '/apps/settings/site',
  '/apps/settings/site/policies',
  '/apps/settings/community-rules',
  '/apps/settings/appearance',
  '/apps/settings/appearance/themes/current',
  '/apps/operations/upgrade',
  '/apps/operations/runtime',
  '/apps/community/reports',
  '/apps/operations/deliveries',
  '/apps/operations/audit',
  '/apps/community/insights',
]

const expectedHeadings: Record<string, string> = {
  '/apps/settings/site': '站点信息',
  '/apps/settings/site/policies': '站点策略',
  '/apps/settings/community-rules': '站点策略',
  '/apps/operations/upgrade': '升级与迁移',
  '/apps/operations/runtime': '运行状态',
}

const legacyAliases = [
  ['/apps/cards', '/apps/content/cards'],
  ['/apps/comments', '/apps/content/comments'],
  ['/apps/tags', '/apps/content/tags'],
  ['/apps/governance/reports', '/apps/community/reports'],
  ['/apps/system', '/apps/settings/site'],
  ['/apps/view', '/apps/settings/appearance'],
  ['/apps/view/theme-config', '/apps/settings/appearance/themes/current'],
  ['/apps/settings/appearance/themes', '/apps/settings/appearance/themes/current'],
  ['/apps/storage/files', '/apps/settings/storage/files'],
  ['/apps/storage/config', '/apps/settings/storage'],
  ['/apps/sender/config', '/apps/settings/messaging'],
  ['/apps/captcha/config', '/apps/settings/verification'],
  ['/apps/settings/access', '/apps/settings/site/policies'],
  ['/apps/settings/community-rules', '/apps/settings/site/policies'],
  ['/apps/operations/notification-policies', '/apps/settings/site/policies'],
  ['/apps/system/runtime', '/apps/operations/runtime'],
  ['/apps/system/upgrade', '/apps/operations/upgrade'],
  ['/apps/updata', '/apps/operations/upgrade'],
  ['/apps/roles', '/apps/access/roles'],
  ['/apps/permissions', '/apps/access/permissions'],
] as const

const legacyDetailAliases = [
  ['/apps/governance/reports/1', '/apps/community/reports/1'],
  ['/apps/storage/config/local', '/apps/settings/storage/local'],
  ['/apps/sender/config/smtp', '/apps/settings/messaging/smtp'],
  ['/apps/captcha/config/default', '/apps/settings/verification/default'],
] as const

type ApiCallResult = {
  ok: boolean
  status: number
  body: unknown
}

type DetailFixtureIds = {
  card: number
  comment: number
  file: number
  permissionCapability: string
  permissionScope: string
  role: number
  tag: number
  user: number
}

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null

const unwrapData = (body: unknown): unknown => isRecord(body) && 'data' in body ? body.data : body

const itemId = (value: unknown): number | null => {
  if (!isRecord(value)) return null
  const id = Number(value.id)
  return Number.isInteger(id) && id > 0 ? id : null
}

const firstListId = (body: unknown): number | null => {
  if (Array.isArray(body)) return itemId(body[0])
  if (!isRecord(body) || !Array.isArray(body.data)) return null
  return itemId(body.data[0])
}

async function callApi(page: Page, path: string, method = 'GET', data?: Record<string, unknown>): Promise<ApiCallResult> {
  return page.evaluate(async ({ path, method, data }) => {
    const token = window.localStorage.getItem('token')
    const response = await window.fetch(path, {
      method,
      headers: {
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}`, 'X-Token': token } : {}),
        ...(data ? { 'Content-Type': 'application/json' } : {}),
      },
      body: data ? JSON.stringify(data) : undefined,
    })
    const text = await response.text()
    let body: unknown = null
    try {
      body = text ? JSON.parse(text) : null
    } catch {
      body = text
    }
    return { ok: response.ok, status: response.status, body }
  }, { path, method, data })
}

function requireSuccessful(result: ApiCallResult, action: string): unknown {
  expect(result.ok, `${action} failed (${result.status}): ${JSON.stringify(result.body)}`).toBe(true)
  return unwrapData(result.body)
}

async function ensureCard(page: Page): Promise<number> {
  const listed = await callApi(page, '/api/admin/cards?list_rows=1')
  const existing = firstListId(requireSuccessful(listed, 'list cards'))
  if (existing) return existing

  const created = await callApi(page, '/api/me/cards', 'POST', { content: 'Admin detail route E2E fixture' })
  const card = requireSuccessful(created, 'create card')
  const id = itemId(card)
  expect(id, 'created card response must contain an ID').not.toBeNull()

  if (isRecord(card) && Number(card.status) !== 0) {
    const approved = await callApi(page, `/api/admin/cards/${id}`, 'PATCH', {
      status: 0,
      reason: '准备管理端详情路由 E2E 数据',
    })
    requireSuccessful(approved, 'approve card fixture')
  }
  return id as number
}

async function ensureComment(page: Page, cardId: number): Promise<number> {
  const listed = await callApi(page, '/api/admin/comments?list_rows=1')
  const existing = firstListId(requireSuccessful(listed, 'list comments'))
  if (existing) return existing

  const created = await callApi(page, `/api/cards/${cardId}/comments`, 'POST', { content: 'Admin comment detail route fixture' })
  const comment = requireSuccessful(created, 'create comment')
  const id = itemId(comment)
  expect(id, 'created comment response must contain an ID').not.toBeNull()
  return id as number
}

async function ensureTag(page: Page): Promise<number> {
  const listed = await callApi(page, '/api/admin/tags?list_rows=1')
  const existing = firstListId(requireSuccessful(listed, 'list tags'))
  if (existing) return existing

  const created = requireSuccessful(
    await callApi(page, '/api/me/tags', 'POST', { name: '路由测试标签' }),
    'create tag',
  )
  const id = itemId(created)
  expect(id, 'created tag response must contain an ID').not.toBeNull()
  return id as number
}

async function ensureFile(page: Page): Promise<number> {
  const listed = await callApi(page, '/api/admin/files?list_rows=1')
  const existing = firstListId(requireSuccessful(listed, 'list files'))
  if (existing) return existing

  const uploaded = await page.evaluate(async () => {
    const token = window.localStorage.getItem('token')
    const bytes = Uint8Array.from(window.atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlT8L8AAAAASUVORK5CYII='), character => character.charCodeAt(0))
    const form = new FormData()
    form.set('file', new Blob([bytes], { type: 'image/png' }), 'admin-route-fixture.png')
    form.set('scene', 'direct')
    const response = await window.fetch('/api/me/files', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}`, 'X-Token': token } : {}),
      },
      body: form,
    })
    const text = await response.text()
    let body: unknown = null
    try {
      body = text ? JSON.parse(text) : null
    } catch {
      body = text
    }
    return { ok: response.ok, status: response.status, body }
  })
  const file = requireSuccessful(uploaded, 'upload file')
  const id = itemId(file)
  expect(id, 'uploaded file response must contain an ID').not.toBeNull()
  return id as number
}

async function resolveDetailFixtures(page: Page): Promise<DetailFixtureIds> {
  await page.goto('./apps/dashboard', { waitUntil: 'domcontentloaded' })
  await expect(page).not.toHaveURL(/\/admin\/login$/)

  const users = await callApi(page, '/api/admin/users?list_rows=1')
  const user = firstListId(requireSuccessful(users, 'list users'))
  expect(user, 'the isolated database must contain an administrator user').not.toBeNull()

  const roles = await callApi(page, '/api/admin/roles?list_rows=1')
  const role = firstListId(requireSuccessful(roles, 'list roles'))
  expect(role, 'the isolated database must contain an administrator role').not.toBeNull()

  const permissions = requireSuccessful(
    await callApi(page, '/api/admin/permissions/all'),
    'list permissions',
  )
  expect(Array.isArray(permissions) && permissions.length > 0, 'the capability catalog must not be empty').toBe(true)
  const permission = Array.isArray(permissions) && isRecord(permissions[0]) ? permissions[0] : null
  const permissionCapability = String(permission?.capability || '')
  const permissionScope = String(permission?.scope || '')
  expect(permissionCapability, 'the first capability must have an identifier').not.toBe('')
  expect(permissionScope, 'the first capability must have a scope').not.toBe('')

  const card = await ensureCard(page)
  return {
    card,
    comment: await ensureComment(page, card),
    file: await ensureFile(page),
    permissionCapability,
    permissionScope,
    role: role as number,
    tag: await ensureTag(page),
    user: user as number,
  }
}

async function expectDetailHeading(page: Page, route: string, heading: string | RegExp) {
  await page.goto(`.${route}`, { waitUntil: 'domcontentloaded' })
  await expect(page).toHaveURL(new RegExp(`/admin${route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`))
  const detailHeading = page.getByRole('heading', { name: heading, exact: typeof heading === 'string', level: 1 })
  await expect(detailHeading).toHaveCount(1)
  await expect(detailHeading).toBeVisible()
}

for (const route of routes) {
	test(`admin route loads without page errors: ${route}`, async ({ page }) => {
		test.setTimeout(60_000)
		const pageErrors: string[] = []
		page.on('pageerror', (error) => pageErrors.push(error.message))

		// Some administration pages keep a polling request open.  Waiting for
		// networkidle would make the whole route suite time out even though the
		// page is usable, so assert the application shell instead.
		await page.goto(`.${route}`, { waitUntil: 'domcontentloaded' })
		const shellTimeout = route === '/apps/settings/site' ? 60_000 : 20_000
		await expect(page.locator('main')).toBeVisible({ timeout: shellTimeout })
		await expect(page).not.toHaveURL(/\/admin\/login$/)
		await expect(page.locator('body')).not.toContainText('权限不足')
		if (expectedHeadings[route]) {
			await expect(page.getByRole('heading', { name: expectedHeadings[route], exact: true })).toBeVisible()
		}
		await page.waitForTimeout(250)

		expect(pageErrors, pageErrors.join('\n')).toEqual([])
	})
}

const legacyPolicyTabs: Record<string, string> = {
  '/apps/settings/access': 'access',
  '/apps/settings/community-rules': 'content',
  '/apps/operations/notification-policies': 'notifications',
}

for (const [legacyPath, canonicalPath] of legacyAliases) {
  test(`legacy route redirects to canonical path: ${legacyPath}`, async ({ page }) => {
    await page.goto(`.${legacyPath}?source=legacy#route-alias`, { waitUntil: 'domcontentloaded' })
    await expect(page.locator('main')).toBeVisible({ timeout: 20_000 })

    const url = new URL(page.url())
    expect(url.pathname).toBe(`/admin${canonicalPath}`)
    expect(url.searchParams.get('source')).toBe('legacy')
    expect(url.hash).toBe('#route-alias')
    const policyTab = legacyPolicyTabs[legacyPath]
    if (policyTab) expect(url.searchParams.get('tab')).toBe(policyTab)
  })
}

for (const [legacyPath, canonicalPath] of legacyDetailAliases) {
  test(`legacy detail route redirects to canonical path: ${legacyPath}`, async ({ page }) => {
    await page.goto(`.${legacyPath}?source=legacy#detail-alias`, { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(new RegExp(`/admin${canonicalPath.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\?`))

    const url = new URL(page.url())
    expect(url.pathname).toBe(`/admin${canonicalPath}`)
    expect(url.searchParams.get('source')).toBe('legacy')
    expect(url.hash).toBe('#detail-alias')
  })
}

test('legacy theme config query resolves to its canonical theme slug', async ({ page }) => {
  await page.goto('./apps/view/theme-config?id=default-theme&release=1.0.0&source=legacy#theme', {
    waitUntil: 'domcontentloaded',
  })
  await expect(page).toHaveURL(/\/admin\/apps\/settings\/appearance\/themes\/default-theme\?/, { timeout: 20_000 })

  const url = new URL(page.url())
  expect(url.pathname).toBe('/admin/apps/settings/appearance/themes/default-theme')
  expect(url.searchParams.get('id')).toBe('default-theme')
  expect(url.searchParams.get('release')).toBe('1.0.0')
  expect(url.searchParams.get('source')).toBe('legacy')
  expect(url.hash).toBe('#theme')
})

test('content resource routes resolve to unified list handling panels while complex resources keep detail pages', async ({ page }) => {
  test.setTimeout(90_000)
  const ids = await resolveDetailFixtures(page)

  await page.goto(`./apps/content/cards/${ids.card}`, { waitUntil: 'domcontentloaded' })
  await expect(page.locator('main')).toBeVisible({ timeout: 20_000 })
  let url = new URL(page.url())
  expect(url.pathname).toBe('/admin/apps/content/cards')
  expect(url.searchParams.get('inspect')).toBe(String(ids.card))
  await expect(page.getByRole('dialog')).toContainText(/(?:查看|审核|编辑)卡片/)
  await expect(page.getByRole('heading', { name: '卡片', exact: true, level: 1 })).toBeVisible()
  await expect(page.locator('body')).not.toContainText('卡片不存在')

  await page.goto(`./apps/content/comments/${ids.comment}`, { waitUntil: 'domcontentloaded' })
  await expect(page.locator('main')).toBeVisible({ timeout: 20_000 })
  url = new URL(page.url())
  expect(url.pathname).toBe('/admin/apps/content/comments')
  expect(url.searchParams.get('inspect')).toBe(String(ids.comment))
  await expect(page.getByRole('dialog')).toContainText(/(?:查看|审核|编辑)评论/)
  await expect(page.getByRole('heading', { name: '评论', exact: true, level: 1 })).toBeVisible()
  await expect(page.locator('body')).not.toContainText('评论不存在')

  await page.goto(`./apps/content/tags/${ids.tag}`, { waitUntil: 'domcontentloaded' })
  await expect(page.locator('main')).toBeVisible({ timeout: 20_000 })
  url = new URL(page.url())
  expect(url.pathname).toBe('/admin/apps/content/tags')
  expect(url.searchParams.get('inspect')).toBe(String(ids.tag))
  await expect(page.getByRole('dialog')).toContainText(/(?:查看|编辑)标签/)
  await expect(page.getByRole('heading', { name: '标签', exact: true, level: 1 })).toBeVisible()

  await expectDetailHeading(page, `/apps/users/${ids.user}/security`, /^账号安全 · .+$/)
  await expect(page.getByRole('heading', { name: 'Session', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: '用户管理', exact: true, level: 1 })).toHaveCount(0)

  await expectDetailHeading(page, `/apps/users/${ids.user}`, /^用户 · .+$/)
  await expect(page.getByRole('heading', { name: '用户管理', exact: true, level: 1 })).toHaveCount(0)

  await expectDetailHeading(page, `/apps/access/roles/${ids.role}`, /^角色 · .+$/)
  await expect(page.getByRole('heading', { name: '角色管理', exact: true, level: 1 })).toHaveCount(0)

  await expectDetailHeading(
    page,
    `/apps/access/permissions/${encodeURIComponent(ids.permissionCapability)}/${encodeURIComponent(ids.permissionScope)}`,
    `权限 · ${ids.permissionCapability}`,
  )
  await expect(page.getByRole('heading', { name: '权限管理', exact: true, level: 1 })).toHaveCount(0)

  await expectDetailHeading(page, `/apps/settings/storage/files/${ids.file}/usage`, `文件 #${ids.file}`)
  await expect(page.getByRole('heading', { name: '文件信息', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: '资源引用', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: '文件管理', exact: true, level: 1 })).toHaveCount(0)
})

test('legacy detail aliases redirect to canonical resource routes', async ({ page }) => {
  test.setTimeout(60_000)
  const { card, comment, file } = await resolveDetailFixtures(page)

  await page.goto(`./apps/cards/${card}?source=legacy#card`, { waitUntil: 'domcontentloaded' })
  await expect(page.locator('main')).toBeVisible({ timeout: 20_000 })
  let url = new URL(page.url())
  expect(url.pathname).toBe('/admin/apps/content/cards')
  expect(url.searchParams.get('inspect')).toBe(String(card))
  expect(url.searchParams.get('source')).toBe('legacy')
  expect(url.hash).toBe('#card')
  await expect(page.getByRole('dialog')).toContainText(/(?:查看|审核|编辑)卡片/)

  await page.goto(`./apps/comments/${comment}?source=legacy#comment`, { waitUntil: 'domcontentloaded' })
  await expect(page.locator('main')).toBeVisible({ timeout: 20_000 })
  url = new URL(page.url())
  expect(url.pathname).toBe('/admin/apps/content/comments')
  expect(url.searchParams.get('inspect')).toBe(String(comment))
  expect(url.searchParams.get('source')).toBe('legacy')
  expect(url.hash).toBe('#comment')
  await expect(page.getByRole('dialog')).toContainText(/(?:查看|审核|编辑)评论/)

  await page.goto(`./apps/storage/files/${file}/usage?source=legacy#usage`, { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: `文件 #${file}`, exact: true, level: 1 })).toBeVisible()
  await expect(page.getByRole('heading', { name: '文件信息', exact: true })).toBeVisible()

  url = new URL(page.url())
  expect(url.pathname).toBe(`/admin/apps/settings/storage/files/${file}/usage`)
  expect(url.searchParams.get('source')).toBe('legacy')
  expect(url.hash).toBe('#usage')
})

test('card handling panel keeps document overflow contained on a narrow viewport', async ({ page }) => {
  test.setTimeout(60_000)
  await page.setViewportSize({ width: 390, height: 844 })
  const { card } = await resolveDetailFixtures(page)
  await page.goto(`./apps/content/cards/${card}`, { waitUntil: 'domcontentloaded' })
  await expect(page.locator('main')).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('dialog')).toContainText(/(?:查看|审核|编辑)卡片/)
  await page.waitForTimeout(250)

  const overflow = await page.evaluate(() => Math.max(
    document.documentElement.scrollWidth,
    document.body.scrollWidth,
  ) - document.documentElement.clientWidth)
  expect(overflow).toBeLessThanOrEqual(1)
})
