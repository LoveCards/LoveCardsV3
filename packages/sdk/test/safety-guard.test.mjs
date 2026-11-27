import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const testDir = path.dirname(fileURLToPath(import.meta.url))
const entry = path.join(testDir, 'run-sdk-tests.mjs')
const run = (env) => spawnSync(process.execPath, [entry], { env: { ...process.env, ...env }, encoding: 'utf8' })

const defaultRun = run({ LOVECARDS_SDK_TEST_ISOLATED: '', LOVECARDS_SDK_TEST_BASE_URL: '', LOVECARDS_SDK_TEST_ADMIN_ACCOUNT: '', LOVECARDS_SDK_TEST_ADMIN_PASSWORD: '' })
assert.notEqual(defaultRun.status, 0)
assert.match(defaultRun.stderr, /Refusing SDK integration tests/)

const remoteRun = run({
  LOVECARDS_SDK_TEST_ISOLATED: '1',
  LOVECARDS_SDK_TEST_BASE_URL: 'https://example.com/api',
  LOVECARDS_SDK_TEST_ADMIN_ACCOUNT: 'test@example.com',
  LOVECARDS_SDK_TEST_ADMIN_PASSWORD: 'secret',
})
assert.notEqual(remoteRun.status, 0)
assert.match(remoteRun.stderr, /localhost or 127\.0\.0\.1/)

const deceptiveHostRun = run({
  LOVECARDS_SDK_TEST_ISOLATED: '1',
  LOVECARDS_SDK_TEST_BASE_URL: 'https://127.0.0.1.example.com/api',
  LOVECARDS_SDK_TEST_ADMIN_ACCOUNT: 'test@example.com',
  LOVECARDS_SDK_TEST_ADMIN_PASSWORD: 'secret',
})
assert.notEqual(deceptiveHostRun.status, 0)
assert.match(deceptiveHostRun.stderr, /localhost or 127\.0\.0\.1/)

const wrongPathRun = run({
  LOVECARDS_SDK_TEST_ISOLATED: '1',
  LOVECARDS_SDK_TEST_BASE_URL: 'http://127.0.0.1:6001/not-api',
  LOVECARDS_SDK_TEST_ADMIN_ACCOUNT: 'test@example.com',
  LOVECARDS_SDK_TEST_ADMIN_PASSWORD: 'secret',
})
assert.notEqual(wrongPathRun.status, 0)
assert.match(wrongPathRun.stderr, /must end with \/api/)

// 通知发布测试额外运行 Scheduler，必须在加载 SDK 或发出 HTTP 前验证数据库和实例边界。
const noticeEntry = path.join(testDir, 'system-notice-integration.mjs')
const noticeEnv = {
  LOVECARDS_SDK_TEST_ISOLATED: '1', LOVECARDS_E2E_ISOLATED: '1', LC_ENV_NAME: 'e2e',
  LOVECARDS_SDK_TEST_BASE_URL: 'http://127.0.0.1:38761/api',
  LOVECARDS_E2E_DB_NAME: 'lovecards_e2e_notice_guard',
  LC_INSTANCE_ROOT: path.resolve(testDir, '../../../../lovecards_e2e_notice_guard/instance'),
  LC_INSTANCE_ROOT_RESOLVED: '',
  DATABASE_DATABASE: 'lovecards_e2e_notice_guard', DATABASE_HOSTNAME: '127.0.0.1',
  PHP_DATABASE_DATABASE: 'lovecards_e2e_notice_guard', PHP_DATABASE_HOSTNAME: '127.0.0.1',
}
for (const overrides of [
  { LOVECARDS_SDK_TEST_ISOLATED: '' },
  { LOVECARDS_E2E_ISOLATED: '' },
  { LC_ENV_NAME: 'dev' },
  { LOVECARDS_SDK_TEST_BASE_URL: 'http://127.0.0.1.example.com:38761/api' },
  { LOVECARDS_SDK_TEST_BASE_URL: 'http://127.0.0.1:6001/api' },
  { LOVECARDS_SDK_TEST_BASE_URL: 'http://127.0.0.1:20089/api' },
  { LOVECARDS_SDK_TEST_BASE_URL: 'http://127.0.0.1:20087/api' },
  { LOVECARDS_SDK_TEST_BASE_URL: 'http://127.0.0.1:38761/not-api' },
  { DATABASE_DATABASE: 'active_database' },
  { PHP_DATABASE_HOSTNAME: 'remote.example.com' },
  { LC_INSTANCE_ROOT: path.join(testDir, 'lovecards_e2e_notice_guard/instance') },
  { LC_INSTANCE_ROOT_RESOLVED: path.resolve(testDir, '../../../../another_instance') },
]) {
  const result = spawnSync(process.execPath, [noticeEntry], {
    env: { ...process.env, ...noticeEnv, ...overrides }, encoding: 'utf8',
  })
  assert.notEqual(result.status, 0)
  assert.match(result.stderr, /Refusing system-notice integration outside a named disposable localhost instance/)
}
console.log('SDK integration safety guard tests passed.')
