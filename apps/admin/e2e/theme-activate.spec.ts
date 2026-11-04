import { expect, test } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'

// 主题 activate 的写路径证据（审计 §5：主题激活此前无浏览器块）。
// mock 端点契约与 contracts/route-manifest 一致：GET /admin/theme/list、POST /admin/theme/activate。
test.use({
  storageState: {
    cookies: [],
    origins: [{
      origin: adminOrigin,
      localStorage: [{ name: 'token', value: 'theme-activate-mock-token' }],
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
          id: 4,
          number: '1000000004',
          username: '主题管理测试员',
          email: 'theme@example.test',
          phone: '',
          avatar: '',
          roles_id: [],
          roles: [],
          capabilities: [],
          grants: [
            { capability: 'admin.access', scope: 'global' },
            { capability: 'theme.read', scope: 'global' },
            { capability: 'theme.activate', scope: 'global' },
          ],
          status: 0,
          stats: { received_likes: 0 },
        },
      }),
    })
  })
})

const theme = (id: string, name: string, release: string, active: boolean) => ({
  id,
  name,
  version: release.split('-').slice(1).join('-') || '1.0.0',
  mode: 'static',
  release,
  author: 'LoveCards 官方',
  description: `${name} 的测试描述`,
  active,
})

test('activating a theme posts its release and refreshes the current-theme marker', async ({ page }) => {
  let activeId = 'default'
  let activateBody: Record<string, unknown> | null = null
  await page.route('**/api/admin/theme/list', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: [
          theme('default', '默认主题', 'default-1.0.0', activeId === 'default'),
          theme('aurora', 'Aurora 主题', 'aurora-1.2.0', activeId === 'aurora'),
        ],
      }),
    })
  })
  await page.route('**/api/admin/theme/activate', async (route) => {
    activateBody = JSON.parse(String(route.request().postData() ?? '{}'))
    activeId = 'aurora'
    await route.fulfill({ status: 204, body: '' })
  })

  await page.goto('./apps/settings/appearance', { waitUntil: 'domcontentloaded' })
  const defaultCard = page.locator('.v-card').filter({ hasText: '默认主题' }).first()
  const auroraCard = page.locator('.v-card').filter({ hasText: 'Aurora 主题' }).first()
  await expect(defaultCard.getByText('当前主题')).toBeVisible()
  await expect(auroraCard.getByRole('button', { name: '设为当前主题', exact: true })).toBeVisible()

  await auroraCard.getByRole('button', { name: '设为当前主题', exact: true }).click()

  await expect.poll(() => activateBody).not.toBeNull()
  expect(activateBody).toMatchObject({ id: 'aurora', release: 'aurora-1.2.0' })
  await expect(auroraCard.getByText('当前主题')).toBeVisible()
  await expect(auroraCard.getByRole('button', { name: '配置主题', exact: true })).toBeVisible()
  await expect(defaultCard.getByRole('button', { name: '设为当前主题', exact: true })).toBeVisible()
})

test('failed activation surfaces a page-level error without switching themes', async ({ page }) => {
  await page.route('**/api/admin/theme/list', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: [
          theme('default', '默认主题', 'default-1.0.0', true),
          theme('aurora', 'Aurora 主题', 'aurora-1.2.0', false),
        ],
      }),
    })
  })
  await page.route('**/api/admin/theme/activate', async (route) => {
    await route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({ code: 1, message: '目标主题发布不存在或与当前运行模式不兼容' }),
    })
  })

  await page.goto('./apps/settings/appearance', { waitUntil: 'domcontentloaded' })
  const auroraCard = page.locator('.v-card').filter({ hasText: 'Aurora 主题' }).first()
  await auroraCard.getByRole('button', { name: '设为当前主题', exact: true }).click()

  // 页面级错误 alert 在 main 内；SDK 全局通知 toast 挂在 main 外，作用域收窄避免 strict 冲突。
  await expect(page.getByRole('main').getByRole('alert')).toContainText('目标主题发布不存在或与当前运行模式不兼容')
  await expect(page.locator('.v-card').filter({ hasText: '默认主题' }).first().getByText('当前主题')).toBeVisible()
  await expect(auroraCard.getByRole('button', { name: '设为当前主题', exact: true })).toBeVisible()
})
