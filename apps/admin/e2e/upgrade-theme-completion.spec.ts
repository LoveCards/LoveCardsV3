import { expect as baseExpect, test, type Page } from '@playwright/test'
import type { OperationsUpgradePlan } from '@lovecards/sdk'

const expect = baseExpect.configure({ timeout: 15_000 })
test.use({ storageState: { cookies: [], origins: [] } })
test.setTimeout(60_000)
const planFixture = (): OperationsUpgradePlan => ({
  plan_id: 'completion-plan', plan_version: 2, legacy_plan: false, execution_uncertain: false, service_recovered: false,
  release_id: 'release-2', previous_release_id: 'release-1', current_version: '3.0.0', target_version: '3.1.0',
  update_notes: '修正管理端诊断与关联入口。', revision: 2, status: 'failed', current_step: 'health_check',
  contract_approved: false, created_by: 1, package: { checksum: 'a'.repeat(64), size_bytes: 10240 },
  milestones: { activated_at: '2026-11-27 09:00:00', pointer_restored_at: '2026-11-27 09:01:00' },
  steps: [
    { name: 'health_check', state: 'failed', attempts: 1, cursor: null, failure_code: 'health_unavailable' },
    { name: 'contract_migrations', state: 'pending', attempts: 0, cursor: null, failure_code: null },
  ],
})

async function fixtures(page: Page, readOnly = false) {
  const state = { plan: planFixture(), failDelete: false, failUpload: false, listCalls: 0, writes: [] as string[], errors: [] as string[] }
  const themes = [
    { id: 'current', name: '当前主题', version: '1.0.0', release: '1.0.0-aaaaaaaaaaaaaaaa', mode: 'spa', author: 'LoveCards', description: '当前站点使用的主题。', active: true },
    { id: 'archived', name: '备用主题', version: '1.0.0', release: '1.0.0-bbbbbbbbbbbbbbbb', mode: 'spa', author: 'LoveCards', description: '可删除的非当前版本。', active: false },
  ]
  await page.addInitScript(() => localStorage.setItem('token', 'completion-mock'))
  page.on('pageerror', error => state.errors.push(error.message))
  await page.route('**/api/**', async route => {
    const request = route.request()
    const url = new URL(request.url())
    const ok = (data: unknown) => route.fulfill({ json: { success: true, data } })
    if (request.method() !== 'GET') {
      state.writes.push(`${request.method()} ${url.pathname}`)
      if (url.pathname.endsWith('/recover')) { state.plan.service_recovered = true; state.plan.status = 'recovered'; return ok(state.plan) }
      if (url.pathname === '/api/admin/theme/delete') {
        if (state.failDelete) return route.fulfill({ status: 500, json: { error: { code: 50000, message: '主题操作已执行，但结果审计失败；请刷新核对，勿直接重复操作。', details: { operation_id: 'completion-operation', side_effects: 'applied' } } } })
        return route.fulfill({ status: 204 })
      }
      if (url.pathname === '/api/admin/theme/upload') {
        if (state.failUpload) return route.abort('failed')
        return ok(themes[1])
      }
      return route.abort()
    }
    if (url.pathname === '/api/me') return ok({ id: 1, username: '诊断管理员', roles: [], status: 0, grants: [
      { capability: 'admin.access', scope: 'global' },
      ...['operations.read', 'system.update', 'theme.read', ...(readOnly ? [] : ['theme.upload', 'theme.delete', 'theme.activate', 'system.update.contract', 'system.recover'])].map(capability => ({ capability, scope: 'global' })),
    ] })
    if (url.pathname === '/api/admin/theme/list') { state.listCalls++; return ok(themes) }
    if (url.pathname === '/api/admin/operations/upgrades') return ok([state.plan])
    if (url.pathname === '/api/admin/operations/upgrades/completion-plan') return ok(state.plan)
    return ok([])
  })
  return state
}

test('upgrade diagnostics retain exact plan and allow health recovery after pointer restore', async ({ page }) => {
  const state = await fixtures(page)
  await page.goto('./apps/operations/upgrade?plan=completion-plan')
  const detail = page.getByTestId('upgrade-plan-detail')
  await expect(detail).toContainText('health_unavailable')
  await expect(detail).toContainText('尚无服务健康恢复的确认')
  await page.getByRole('button', { name: '刷新', exact: true }).click()
  await expect(page).toHaveURL(/plan=completion-plan/)
  await detail.getByRole('button', { name: '继续恢复', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '继续恢复' })
  await expect(dialog).toContainText('数据库恢复依赖升级前备份')
  await dialog.getByTestId('danger-confirm').click()
  await expect(detail).toContainText('旧版本服务已恢复')
  expect(state.writes).toEqual(['POST /api/admin/operations/upgrades/completion-plan/recover'])
  expect(state.errors).toEqual([])
})

test('uncertain and legacy upgrade plans keep diagnosis visible without execution controls', async ({ page }) => {
  const state = await fixtures(page)
  state.plan.execution_uncertain = true
  await page.goto('./apps/operations/upgrade?plan=completion-plan')
  const detail = page.getByTestId('upgrade-plan-detail')
  await expect(detail).toContainText('执行结果待核实')
  await expect(detail.getByRole('button', { name: /继续升级|继续恢复|批准数据库收缩/ })).toHaveCount(0)
  state.plan.execution_uncertain = false; state.plan.legacy_plan = true
  await page.getByRole('button', { name: '刷新', exact: true }).click()
  await expect(detail).toContainText('历史计划（只读）')
  await expect(detail.getByRole('button', { name: /继续升级|继续恢复|批准数据库收缩/ })).toHaveCount(0)
  expect(state.writes).toEqual([])
})

test('theme upload uses one native dialog and permission hides mutations', async ({ page }) => {
  const state = await fixtures(page)
  await page.goto('./apps/settings/appearance')
  await page.getByRole('button', { name: '上传主题', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '上传主题' })
  await dialog.locator('input[type=file]').setInputFiles({ name: 'theme.zip', mimeType: 'application/zip', buffer: Buffer.from('mock theme archive') })
  await dialog.getByRole('button', { name: '上传', exact: true }).click()
  await expect(dialog).not.toBeVisible()
  expect(state.writes).toEqual(['POST /api/admin/theme/upload'])
  expect(state.listCalls).toBeGreaterThanOrEqual(2)
  expect(state.errors).toEqual([])
})

test('theme audit result failure requires verification and never repeats delete automatically', async ({ page }) => {
  const state = await fixtures(page)
  state.failDelete = true
  await page.goto('./apps/settings/appearance')
  await expect(page.getByRole('button', { name: '删除', exact: true })).toHaveCount(1)
  await page.getByRole('button', { name: '删除', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '删除主题版本' })
  await expect(dialog).toContainText('备用主题')
  await dialog.getByTestId('danger-confirm').click()
  await expect(dialog).toContainText('completion-operation')
  await expect(dialog.getByTestId('danger-confirm')).toHaveText('刷新核对')
  await dialog.getByTestId('danger-confirm').click()
  await expect(dialog).not.toBeVisible()
  expect(state.writes).toEqual(['DELETE /api/admin/theme/delete'])
  expect(state.listCalls).toBeGreaterThanOrEqual(2)
  expect(state.errors).toEqual([])
})

test('theme readers have no upload delete or activation controls', async ({ page }) => {
  const state = await fixtures(page, true)
  await page.goto('./apps/settings/appearance')
  await expect(page.getByText('备用主题', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: /上传主题|删除|设为当前主题/ })).toHaveCount(0)
  expect(state.writes).toEqual([])
})

test('theme upload network uncertainty changes retry into a readonly verification', async ({ page }) => {
  const state = await fixtures(page)
  state.failUpload = true
  await page.goto('./apps/settings/appearance')
  await page.getByRole('button', { name: '上传主题', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: '上传主题' })
  await dialog.locator('input[type=file]').setInputFiles({ name: 'theme.zip', mimeType: 'application/zip', buffer: Buffer.from('mock') })
  await dialog.getByRole('button', { name: '上传', exact: true }).click()
  await expect(dialog.getByRole('button', { name: '刷新核对', exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: '刷新核对', exact: true }).click()
  await expect(dialog).not.toBeVisible()
  expect(state.writes).toEqual(['POST /api/admin/theme/upload'])
  expect(state.listCalls).toBeGreaterThanOrEqual(2)
})

for (const [width, theme] of [[1440, 'light'], [720, 'light'], [390, 'dark'], [320, 'dark']] as const) {
  test(`upgrade and theme layouts reflow at ${width}px in ${theme}`, async ({ page }, info) => {
    await fixtures(page)
    await page.addInitScript(value => localStorage.setItem('lovecards.admin.theme', value), theme)
    await page.setViewportSize({ width, height: 960 })
    await page.emulateMedia({ reducedMotion: 'reduce' })
    for (const [name, url, expected] of [['upgrade', './apps/operations/upgrade?plan=completion-plan', '升级诊断'], ['theme', './apps/settings/appearance', '备用主题']] as const) {
      await page.goto(url)
      await expect(page.locator('main')).toContainText(expected)
      expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
      await page.screenshot({ path: info.outputPath(`${name}.png`), fullPage: true, animations: 'disabled' })
    }
  })
}
