import { expect, test, type Page } from '@playwright/test'

type Scope = 'all' | 'global'
type Grant = { capability: string; scope: Scope }
type RoleCase = {
  slug: string
  name: string
  grants: Grant[]
  visible: string[]
  hidden: string[]
}

const globalGrant = (capability: string): Grant => ({ capability, scope: 'global' })
const allGrant = (capability: string): Grant => ({ capability, scope: 'all' })

const common = [globalGrant('admin.access'), globalGrant('dashboard.read')]
const roleCases: RoleCase[] = [
  {
    slug: 'root',
    name: '超级管理员',
    grants: [
      ...common,
      allGrant('cards.read'), allGrant('users.read'),
      globalGrant('config.read'), globalGrant('operations.read'),
      globalGrant('audit.read'), globalGrant('roles.read'), globalGrant('permissions.read'),
    ],
    visible: ['卡片', '用户管理', '站点设置', '社区规则', '运行状态', '审计日志', '角色管理', '权限目录'],
    hidden: [],
  },
  {
    slug: 'admin',
    name: '管理员',
    grants: [...common, allGrant('cards.read'), allGrant('comments.read'), allGrant('users.read')],
    visible: ['卡片', '评论', '用户管理'],
    hidden: ['升级与迁移', '审计日志', '角色管理'],
  },
  {
    slug: 'content_operator',
    name: '内容运营',
    grants: [...common, allGrant('cards.read'), allGrant('comments.read'), allGrant('tags.read')],
    visible: ['卡片', '评论', '标签'],
    hidden: ['账号恢复记录', '存储配置', '角色管理'],
  },
  {
    slug: 'community_operator',
    name: '社区运营',
    grants: [...common, allGrant('comments.read'), globalGrant('reports.read'), globalGrant('community.insights.read')],
    visible: ['评论', '举报处理', '社区洞察'],
    hidden: ['用户管理', '升级与迁移', '权限目录'],
  },
  {
    slug: 'reviewer',
    name: '审核员',
    grants: [...common, allGrant('cards.read'), allGrant('comments.read'), globalGrant('reports.read')],
    visible: ['卡片', '评论', '举报处理'],
    hidden: ['站点设置', '社区规则', '通知投递', '角色管理'],
  },
  {
    slug: 'security_admin',
    name: '安全管理员',
    grants: [...common, allGrant('users.read'), globalGrant('users.recovery.read'), globalGrant('audit.read')],
    visible: ['用户管理', '账号恢复记录', '审计日志'],
    hidden: ['卡片', '存储配置', '升级与迁移'],
  },
  {
    slug: 'operations_admin',
    name: '运维管理员',
    grants: [...common, globalGrant('operations.read'), globalGrant('communications.read'), allGrant('files.read')],
    visible: ['文件管理', '运行状态', '通知投递', '升级与迁移'],
    hidden: ['用户管理', '举报处理', '角色管理'],
  },
  {
    slug: 'auditor',
    name: '审计员',
    grants: [...common, globalGrant('audit.read')],
    visible: ['审计日志'],
    hidden: ['卡片', '用户管理', '站点设置', '社区规则', '角色管理'],
  },
]

async function mockCurrentAdministrator(page: Page, roleCase: RoleCase) {
  await page.route('**/api/me', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      status: 200,
      body: JSON.stringify({
        success: true,
        data: {
          id: 9001,
          number: `e2e-${roleCase.slug}`,
          username: roleCase.name,
          email: '',
          phone: '',
          avatar: '',
          roles_id: [9001],
          roles: [{ id: 9001, name: roleCase.name, slug: roleCase.slug }],
          capabilities: roleCase.grants.map((grant) => grant.capability),
          grants: roleCase.grants,
          status: 0,
          stats: { received_likes: 0 },
        },
      }),
    })
  })
}

for (const roleCase of roleCases) {
  test(`${roleCase.name} only sees navigation granted by capability`, async ({ page }) => {
    test.setTimeout(60_000)
    await mockCurrentAdministrator(page, roleCase)
    await page.goto('./apps/dashboard', { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/admin\/login$/)
    await page.getByRole('button', { name: '搜索管理页面' }).click()
    const navigationSearch = page.getByPlaceholder('输入页面名称')
    await expect(navigationSearch).toBeVisible({ timeout: 15_000 })

    for (const title of roleCase.visible) {
      await navigationSearch.fill(title)
      const results = page.getByRole('listbox').last()
      await expect(results.getByRole('link', { name: new RegExp(`^${title}(?:\\s|$)`) })).toHaveCount(1)
    }
    for (const title of roleCase.hidden) {
      await navigationSearch.fill(title)
      const results = page.getByRole('listbox').last()
      await expect(results.getByRole('link', { name: new RegExp(`^${title}(?:\\s|$)`) })).toHaveCount(0)
    }
  })
}

test('a privileged role name cannot reveal pages without matching grants', async ({ page }) => {
  await mockCurrentAdministrator(page, {
    slug: 'root',
    name: '伪造超级管理员名称',
    grants: common,
    visible: [],
    hidden: [],
  })
  await page.goto('./apps/dashboard', { waitUntil: 'domcontentloaded' })
  const navigation = page.getByRole('navigation')
  await expect(navigation.getByRole('link', { name: '角色管理', exact: true })).toHaveCount(0)
  await expect(navigation.getByRole('link', { name: '升级与迁移', exact: true })).toHaveCount(0)
})

test('the workbench is direct and only the current task group expands automatically', async ({ page }) => {
  await mockCurrentAdministrator(page, {
    slug: 'multi_domain_operator',
    name: '跨域运营',
    grants: [...common, allGrant('cards.read'), allGrant('tags.read'), allGrant('users.read')],
    visible: [],
    hidden: [],
  })

  await page.goto('./apps/dashboard', { waitUntil: 'domcontentloaded' })
  const navigation = page.getByRole('navigation')
  await expect(navigation.getByRole('link', { name: '工作台', exact: true })).toBeVisible()
  await expect(navigation.getByRole('link', { name: '卡片', exact: true })).not.toBeVisible()

  await page.goto('./apps/content/tags', { waitUntil: 'domcontentloaded' })
  await expect(navigation.getByRole('link', { name: '标签', exact: true })).toBeVisible()
  await expect(navigation.getByRole('link', { name: '用户管理', exact: true })).not.toBeVisible()
})
