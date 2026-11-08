import { defineConfig, devices } from '@playwright/test'
import os from 'node:os'
import path from 'node:path'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'
const backendOrigin = process.env.LOVECARDS_DEV_BACKEND_ORIGIN || 'http://127.0.0.1:6001'
const adminPort = new URL(adminOrigin).port || '3000'
// Keep the shared login state outside Playwright's outputDir. Playwright may
// prune outputDir while a long suite is running, which would cascade into
// unrelated 401/redirect failures for later tests.
const authFile = process.env.LOVECARDS_E2E_AUTH_FILE
  || path.join(os.tmpdir(), 'lovecards-admin-e2e-auth.json')
const chromeExecutable = process.env.PLAYWRIGHT_CHROME_PATH
  || (process.platform === 'win32' ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe' : '')
const browserChannel = process.platform === 'win32' ? { channel: 'chrome' as const } : {}
const browserLaunch = chromeExecutable ? { launchOptions: { executablePath: chromeExecutable } } : {}

export default defineConfig({
  testDir: './e2e',
  outputDir: './test-results',
  fullyParallel: false,
  workers: 1,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: `${adminOrigin}/admin/`,
    ...browserChannel,
    ...browserLaunch,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  webServer: [
    {
      command: 'npm run dev:backend',
      cwd: '../..',
      url: `${backendOrigin}/api/cards?list_rows=1`,
      reuseExistingServer: process.env.LOVECARDS_E2E_MANAGED_SERVERS === '1' || !process.env.CI,
      timeout: 120_000,
      env: {
        ...process.env,
        NUXT_PUBLIC_API_BASE: '/api',
        NUXT_PUBLIC_MATOMO_ENABLED: 'false',
      },
    },
    {
      command: `npm run dev --workspace @lovecards/admin -- --host 127.0.0.1 --port ${adminPort}`,
      cwd: '../..',
      url: `${adminOrigin}/admin/login`,
      reuseExistingServer: process.env.LOVECARDS_E2E_MANAGED_SERVERS === '1' || !process.env.CI,
      timeout: 120_000,
      env: {
        ...process.env,
        NUXT_API_PROXY_TARGET: `${backendOrigin}/api`,
        NUXT_PUBLIC_MATOMO_ENABLED: 'false',
      },
    },
  ],
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
      use: { ...browserChannel, ...browserLaunch },
    },
    {
      name: 'chromium',
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        ...browserChannel,
        ...browserLaunch,
        storageState: authFile,
      },
      testIgnore: /auth\.setup\.ts/,
    },
  ],
})
