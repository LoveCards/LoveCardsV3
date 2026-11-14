import { expect, test, type Page } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

test.use({
  storageState: {
    cookies: [],
    origins: [{ origin: adminOrigin, localStorage: [{ name: 'token', value: 'redis-settings-mock-token' }] }],
  },
})

type RedisState = { enabled: boolean; host: string; port: number; database: number; passwordConfigured: boolean }

async function mockAdmin(page: Page) {
  await page.route('**/api/me', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true,
    data: {
      id: 2, number: '1000000002', username: '运维测试员', email: 'operations@example.test', phone: '', avatar: '',
      roles_id: [], roles: [], capabilities: [], status: 0, stats: { received_likes: 0 },
      grants: [
        { capability: 'admin.access', scope: 'global' },
        { capability: 'config.read', scope: 'global' },
        { capability: 'config.update', scope: 'global' },
        { capability: 'operations.runtime.configure', scope: 'global' },
      ],
    },
  }) }))
}

test('default Redis is disabled; saved password stays masked and the connection test reports its result', async ({ page }) => {
  await mockAdmin(page)
  const state: RedisState = { enabled: false, host: '', port: 6379, database: 0, passwordConfigured: true }
  let tests = 0
  await page.route('**/api/admin/config', route => {
    expect(route.request().method()).toBe('GET')
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {
      redis: { __revision: 1, ...state, password: { $secret: 'keep', configured: state.passwordConfigured } },
    } }) })
  })
  await page.route('**/api/admin/config/redis/test', route => {
    expect(route.request().method()).toBe('POST')
    tests++
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {
      ready: false, reason_code: 'redis-disabled', source: 'database',
    } }) })
  })

  await page.goto('./apps/settings/redis', { waitUntil: 'domcontentloaded' })
  await expect(page.getByRole('heading', { name: 'Redis 连接' })).toBeVisible()
  await expect(page.getByLabel('启用 Redis 连接')).not.toBeChecked()
  await expect(page.getByLabel('主机地址')).toHaveValue('')
  await expect(page.getByLabel('使用 TLS 加密连接并验证证书')).not.toBeChecked()
  await expect(page.getByText('已配置密码。', { exact: false })).toBeVisible()
  await expect(page.getByRole('button', { name: '保留' })).toHaveClass(/v-btn--active/)
  await expect(page.getByLabel('新密码')).toHaveCount(0)
  await page.getByRole('button', { name: '测试当前连接' }).click()
  await expect(page.getByText('连接测试未通过：尚未启用 Redis')).toBeVisible()
  expect(tests).toBe(1)
})

test('TLS settings use the shared Redis config and expose certificate identity', async ({ page }) => {
  await mockAdmin(page)
  const writes: Array<Record<string, unknown>> = []
  await page.route('**/api/admin/config', route => {
    if (route.request().method() === 'POST') {
      writes.push(route.request().postDataJSON() as Record<string, unknown>)
      const saved = route.request().postDataJSON().redis
      expect(saved.__revision).toBe(writes.length)
      return route.fulfill({ json: { success: true, data: { redis: { ...saved, __revision: writes.length + 1, password: { $secret: 'keep', configured: false } } } } })
    }
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {
      redis: { __revision: 1, enabled: false, host: '127.0.0.1', port: 6379, database: 0, tls: false, tls_server_name: '', password: { $secret: 'keep', configured: false } },
    } }) })
  })
  await page.route('**/api/admin/config/redis/test', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true, data: { ready: false, reason_code: 'redis-disabled', source: 'database' },
  }) }))
  await page.goto('./apps/settings/redis', { waitUntil: 'domcontentloaded' })
  await page.getByLabel('使用 TLS 加密连接并验证证书').check()
  await expect(page.getByLabel('TLS 证书名称')).toBeVisible()
  await page.getByLabel('TLS 证书名称').fill('redis.example.test')
  await page.getByRole('button', { name: '保存设置' }).click()
  await expect.poll(() => writes.length).toBe(1)
  expect(writes[0]?.redis).toMatchObject({ tls: true, tls_server_name: 'redis.example.test' })
})

test('password replacement and clearing use explicit secret operations', async ({ page }) => {
  await mockAdmin(page)
  const state: RedisState = { enabled: false, host: '127.0.0.1', port: 6379, database: 0, passwordConfigured: false }
  const writes: Array<Record<string, unknown>> = []
  let reads = 0
  await page.route('**/api/admin/config', route => {
    if (route.request().method() === 'POST') {
      const data = route.request().postDataJSON() as { redis: Record<string, unknown> }
      writes.push(data)
      const secret = data.redis.password as { $secret?: string } | undefined
      if (secret?.$secret === 'replace') state.passwordConfigured = true
      if (secret?.$secret === 'clear') state.passwordConfigured = false
      state.enabled = data.redis.enabled === true
      const saved = route.request().postDataJSON().redis
      expect(saved.__revision).toBe(writes.length)
      return route.fulfill({ json: { success: true, data: { redis: { ...saved, __revision: writes.length + 1, password: { $secret: 'keep', configured: state.passwordConfigured } } } } })
    }
    reads++
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {
      redis: { __revision: 1, ...state, password: { $secret: 'keep', configured: state.passwordConfigured } },
    } }) })
  })
  await page.route('**/api/admin/config/redis/test', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true, data: { ready: false, reason_code: 'redis-disabled', source: 'database' },
  }) }))

  await page.goto('./apps/settings/redis', { waitUntil: 'domcontentloaded' })
  await expect(page.getByText('未配置密码。', { exact: false })).toBeVisible()
  await page.getByRole('button', { name: '替换' }).click()
  await expect(page.getByRole('button', { name: '保存设置' })).toBeDisabled()
  await page.getByLabel('新密码').fill('test-only-redis-password')
  await page.getByRole('button', { name: '保存设置' }).click()
  await expect.poll(() => writes.length).toBe(1)
  expect(writes[0]?.redis).toMatchObject({ password: { $secret: 'replace', value: 'test-only-redis-password' } })
  await expect(page.getByText('已配置密码。', { exact: false })).toBeVisible()
  await expect(page.getByLabel('新密码')).toHaveCount(0)

  await page.getByRole('button', { name: '清空' }).click()
  await expect(page.getByText('保存后将移除 Redis 认证密码。')).toBeVisible()
  await page.getByRole('button', { name: '保存设置' }).click()
  await expect.poll(() => writes.length).toBe(2)
  expect(writes[1]?.redis).toMatchObject({ password: { $secret: 'clear' } })
  await expect(page.getByText('未配置密码。', { exact: false })).toBeVisible()
  expect(reads).toBe(1)
})

test('Redis settings remain editable when a stale test response reports env source', async ({ page }) => {
  await mockAdmin(page)
  let writes = 0
  await page.route('**/api/admin/config', route => {
    if (route.request().method() !== 'GET') writes++
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: {
      redis: { __revision: 1, enabled: true, host: 'redis.internal', port: 6379, database: 0, password: { $secret: 'keep', configured: true } },
    } }) })
  })
  await page.route('**/api/admin/config/redis/test', route => route.fulfill({ contentType: 'application/json', body: JSON.stringify({
    success: true, data: { ready: true, reason_code: null, source: 'env' },
  }) }))

  await page.goto('./apps/settings/redis', { waitUntil: 'domcontentloaded' })
  await expect(page.getByLabel('启用 Redis 连接')).toBeVisible({ timeout: 15_000 })
  await page.getByRole('button', { name: '测试当前连接' }).click()
  await expect(page.getByText('连接测试通过。')).toBeVisible()
  await page.getByLabel('主机地址').fill('redis.example.test')
  await expect(page.getByRole('button', { name: '保存设置' })).toBeEnabled()
  expect(writes).toBe(0)
})


test('config conflict preserves edits and never refreshes the loaded revision before saving', async ({ page }) => {
  await mockAdmin(page)
  let reads = 0
  const writes: Array<Record<string, unknown>> = []
  await page.route('**/api/admin/config', route => {
    if (route.request().method() === 'POST') {
      const body = route.request().postDataJSON()
      writes.push(body.redis)
      return route.fulfill({ status: 409, json: { success: false, code: 9016, message: '配置版本冲突', details: { reason: 'stale-config-revision' } } })
    }
    reads++
    return route.fulfill({ json: { success: true, data: { redis: { __revision: 7, enabled: false, host: 'old.invalid', port: 6379, database: 0, password: { $secret: 'keep', configured: false } } } } })
  })
  await page.goto('./apps/settings/redis', { waitUntil: 'domcontentloaded' })
  await page.getByLabel('主机地址').fill('edited.invalid')
  await page.getByRole('button', { name: '保存设置' }).click()
  await expect(page.getByText('配置已被其他管理员修改。当前编辑已保留，请重新加载并核对后再保存。')).toBeVisible()
  await expect(page.getByLabel('主机地址')).toHaveValue('edited.invalid')
  expect(writes).toHaveLength(1)
  expect(writes[0]).toMatchObject({ __revision: 7, host: 'edited.invalid' })
  expect(reads).toBe(1)
})
