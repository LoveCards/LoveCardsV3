import { defineConfig, devices } from '@playwright/test'

const adminOrigin = process.env.LOVECARDS_DEV_ADMIN_ORIGIN || 'http://127.0.0.1:3000'
const backendOrigin = process.env.LOVECARDS_DEV_BACKEND_ORIGIN || 'http://127.0.0.1:6001'
const adminPort = new URL(adminOrigin).port || '3000'
const authFile = 'apps/admin/test-results/.auth/admin.json'
const chromeExecutable = process.env.PLAYWRIGHT_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'

export default defineConfig({
  testDir: './e2e',
  outputDir: 'apps/admin/test-results',
  fullyParallel: false,
  workers: 1,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: `${adminOrigin}/admin`,
    channel: 'chrome',
    launchOptions: { executablePath: chromeExecutable },
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
      use: { channel: 'chrome' },
    },
    {
      name: 'chromium',
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        channel: 'chrome',
        storageState: authFile,
      },
      testIgnore: /auth\.setup\.ts/,
    },
  ],
})
