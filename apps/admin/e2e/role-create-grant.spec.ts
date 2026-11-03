import { expect, test } from '@playwright/test'

// 角色创建与授权真实写路径（审计 §5：写动作浏览器测试此前只有投递重试）。
// 依赖 root 夹具：与 CI 相同设置 LOVECARDS_E2E_RUN_SDK_INTEGRATION=1 运行本套件时，
// bootstrap-e2e 会把隔离夹具账号绑定为 root（role_id=1）。数据库为一次性隔离库，
// 角色随库销毁，无需在收尾删除。
const suffix = Date.now().toString(36)
// 后端规则：角色名称只允许汉字、字母、数字、下划线、破折号（不带空格），见 POST /api/admin/roles 400。
const roleName = `E2E角色生命周期_${suffix}`
const roleSlug = `e2e_role_${suffix}`

test('created custom role persists grants across reload', async ({ page }) => {
  test.setTimeout(90_000)
  await page.goto('./apps/access/roles')
  await expect(page.getByRole('heading', { name: '角色', exact: true })).toBeVisible({ timeout: 15_000 })

  // 1. 创建：对话框填写名称/slug/描述并提交
  await page.getByRole('button', { name: '创建角色' }).first().click()
  const createDialog = page.locator('.v-dialog:visible').filter({ hasText: '创建角色后还需要分配权限' })
  await createDialog.getByLabel('角色名称').fill(roleName)
  await createDialog.getByLabel('角色标识（Slug）').fill(roleSlug)
  await createDialog.getByLabel('角色描述').fill('E2E 浏览器写路径验收角色')
  await createDialog.getByRole('button', { name: '创建角色' }).click()
  await expect(createDialog).toBeHidden({ timeout: 15_000 })

  // 2. 列表检索可命中新角色（检索走服务端分页；Vuetify 输入框存在双 label，用 role 定位）
  await page.getByRole('textbox', { name: '搜索角色名称' }).fill(roleName)
  const roleRow = page.getByRole('row').filter({ hasText: roleSlug }).first()
  await expect(roleRow).toBeVisible({ timeout: 15_000 })

  // 3. 授权：勾选目录中真实存在的三个 grant（GET /api/admin/permissions/all 事实：跨 users/permissions 两域含 global scope）
  await roleRow.getByRole('button', { name: '分配权限' }).click()
  const assignDialog = page.locator('.v-dialog:visible').filter({ hasText: '权限调整会影响该角色可见的页面' })
  await expect(assignDialog).toBeVisible({ timeout: 15_000 })
  for (const grantKey of ['users.read:all', 'users.read:own', 'permissions.read:global']) {
    const checkbox = assignDialog.locator(`input[type="checkbox"][value="${grantKey}"]`)
    await expect(checkbox).toBeVisible({ timeout: 15_000 })
    await checkbox.check()
  }
  await assignDialog.getByRole('button', { name: '保存权限' }).click()
  await expect(assignDialog).toBeHidden({ timeout: 15_000 })

  // 4. 重开授权对话框：勾选状态来自服务端回读，证明持久化
  await roleRow.getByRole('button', { name: '分配权限' }).click()
  const reopenDialog = page.locator('.v-dialog:visible').filter({ hasText: '权限调整会影响该角色可见的页面' })
  await expect(reopenDialog).toBeVisible({ timeout: 15_000 })
  for (const grantKey of ['users.read:all', 'users.read:own', 'permissions.read:global']) {
    await expect(reopenDialog.locator(`input[type="checkbox"][value="${grantKey}"]`)).toBeChecked({ timeout: 15_000 })
  }
  await reopenDialog.getByRole('button', { name: '取消' }).click()
  await expect(reopenDialog).toBeHidden()
})

test('role list exposes the new role through the detail page', async ({ page }) => {
  test.setTimeout(60_000)
  await page.goto('./apps/access/roles')
  await page.getByRole('textbox', { name: '搜索角色名称' }).fill(roleName)
  const roleRow = page.getByRole('row').filter({ hasText: roleSlug }).first()
  await expect(roleRow).toBeVisible({ timeout: 15_000 })

  // 编辑入口：修改名称后列表回读（第二个写动作，防止写链路只测一半）
  await roleRow.getByRole('button', { name: '编辑角色' }).click()
  const editDialog = page.locator('.v-dialog:visible').filter({ has: page.getByLabel('角色名称') })
  await expect(editDialog).toBeVisible({ timeout: 15_000 })
  const renamed = `${roleName}_已改名`
  await editDialog.getByLabel('角色名称').fill(renamed)
  await editDialog.getByRole('button', { name: '保存修改' }).click()
  await expect(editDialog).toBeHidden({ timeout: 15_000 })

  await page.getByRole('textbox', { name: '搜索角色名称' }).fill(renamed)
  await expect(page.getByRole('row').filter({ hasText: roleSlug }).first()).toBeVisible({ timeout: 15_000 })
})
