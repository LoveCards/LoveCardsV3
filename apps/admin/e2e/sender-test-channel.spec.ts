import { expect, test } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

// 渠道 testChannel 写路径证据（审计 §5：渠道测试此前无浏览器块）。
// 端点契约与 contracts/route-manifest 一致：GET /admin/sender/channels、GET /admin/config、POST /admin/sender/test-channel。
test.use({
  storageState: {
    cookies: [],
    origins: [{
      origin: adminOrigin,
      localStorage: [{ name: 'token', value: 'sender-test-mock-token' }],
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
          id: 5,
          number: '1000000005',
          username: '渠道测试员',
          email: 'sender@example.test',
          phone: '',
          avatar: '',
          roles_id: [],
          roles: [],
          capabilities: [],
          grants: [
            { capability: 'admin.access', scope: 'global' },
            { capability: 'sender.read', scope: 'global' },
            { capability: 'sender.test', scope: 'global' },
            { capability: 'config.read', scope: 'global' },
          ],
          status: 0,
          stats: { received_likes: 0 },
        },
      }),
    })
  })
  await page.route('**/api/admin/sender/channels', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: [{
          slug: 'smtp',
          name: 'SMTP 邮件',
          fields: [
            { key: 'host', label: 'SMTP 服务器', type: 'text', default: '' },
            { key: 'port', label: '端口', type: 'number', default: 465 },
          ],
        }],
      }),
    })
  })
  await page.route('**/api/admin/config', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: { sender_smtp: { host: 'smtp.example.test', port: 465, type: 'smtp' } },
      }),
    })
  })
})

test('channel test dialog validates input locally then reports the server outcome', async ({ page }) => {
  let requests = 0
  let testBody: Record<string, unknown> | null = null
  await page.route('**/api/admin/sender/test-channel', async (route) => {
    requests += 1
    testBody = JSON.parse(String(route.request().postData() ?? '{}'))
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { success: true, message: '测试邮件已投递到目标邮箱' } }),
    })
  })

  await page.goto('./apps/settings/messaging/smtp', { waitUntil: 'domcontentloaded' })
  await page.getByRole('button', { name: '测试发信', exact: true }).click()
  const dialog = page.getByRole('dialog').filter({ hasText: '测试发信' })
  await expect(dialog.getByText('测试使用服务端已保存的渠道配置，不会使用尚未保存的表单内容。')).toBeVisible()

  // 空收件人本地拦截：不发请求，直接给出错误提示。
  await dialog.getByRole('button', { name: '发送测试', exact: true }).click()
  await expect(dialog.getByRole('alert').filter({ hasText: '请输入收件邮箱' })).toBeVisible()
  expect(requests).toBe(0)

  await dialog.getByLabel('收件邮箱').fill('target@example.test')
  await dialog.getByRole('button', { name: '发送测试', exact: true }).click()

  await expect.poll(() => testBody).not.toBeNull()
  expect(testBody).toMatchObject({ channel: 'smtp', to: 'target@example.test' })
  await expect(dialog.getByRole('alert').filter({ hasText: '测试邮件已投递到目标邮箱' })).toBeVisible()
})

test('channel returning failure shows the channel message as an error', async ({ page }) => {
  await page.route('**/api/admin/sender/test-channel', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { success: false, message: '远端 SMTP 拒绝建立连接' } }),
    })
  })

  await page.goto('./apps/settings/messaging/smtp', { waitUntil: 'domcontentloaded' })
  await page.getByRole('button', { name: '测试发信', exact: true }).click()
  const dialog = page.getByRole('dialog').filter({ hasText: '测试发信' })
  await dialog.getByLabel('收件邮箱').fill('target@example.test')
  await dialog.getByRole('button', { name: '发送测试', exact: true }).click()

  await expect(dialog.getByRole('alert').filter({ hasText: '远端 SMTP 拒绝建立连接' })).toBeVisible()
})
