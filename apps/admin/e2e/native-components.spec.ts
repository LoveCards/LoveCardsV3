import { expect, test } from '@playwright/test'

for (const width of [1440, 390]) {
  for (const route of ['content/tags', 'access/roles', 'settings/storage/files', 'operations/runtime', 'settings/site', 'settings/site/policies', 'settings/storage', 'settings/redis', 'settings/messaging', 'settings/verification', 'settings/appearance']) {
    test(`native admin surfaces reflow at ${width}px: ${route}`, async ({ page }, testInfo) => {
      test.setTimeout(60_000)
      await page.setViewportSize({ width, height: 960 })
      await page.goto(`./apps/${route}`, { waitUntil: 'domcontentloaded' })
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 15_000 })
      await expect(page.locator('.v-skeleton-loader')).toHaveCount(0, { timeout: 20_000 })
      if (route === 'access/roles') {
        await expect(page.getByRole('button', { name: '创建角色', exact: true })).toBeVisible()
        await expect(page.getByLabel('批量操作')).toHaveCount(0)
      }
      if (route === 'settings/storage/files') {
        await expect(page.getByRole('button', { name: '处理过期上传', exact: true })).toBeVisible()
      }
      const surfaces = page.locator('main .v-card:not(.v-overlay .v-card)')
      for (const surface of await surfaces.all()) {
        await expect(surface).toHaveClass(/v-card--variant-flat/)
        await expect(surface).toHaveClass(/v-card--border/)
        const shadow = await surface.evaluate((element) => getComputedStyle(element).boxShadow)
        if (shadow !== 'none') {
          // Vuetify 的 flat 使用零尺寸阴影，而非 CSS 字面值 none。
          const lengths = [...shadow.matchAll(/(-?[\d.]+)px/g)].map((match) => Number(match[1]))
          expect(lengths.length).toBeGreaterThan(0)
          expect(lengths.every((length) => length === 0)).toBe(true)
        }
      }
      expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
      if (route === 'content/tags') {
        const emptyState = page.locator('.v-empty-state')
        if (await emptyState.isVisible()) {
          const bounds = await emptyState.boundingBox()
          expect(bounds).not.toBeNull()
          expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width)
        }
      }
      await page.screenshot({ path: testInfo.outputPath('page.png'), fullPage: true })
      if (['settings/storage', 'settings/verification'].includes(route)) {
        await page.getByRole('tab', { name: route === 'settings/verification' ? '服务设置' : '基础设置' }).click()
        await expect(page.getByRole('button', { name: /保存.*设置/ })).toBeVisible()
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1)
        await page.screenshot({ path: testInfo.outputPath('settings.png'), fullPage: true })
      }
    })
  }
}
