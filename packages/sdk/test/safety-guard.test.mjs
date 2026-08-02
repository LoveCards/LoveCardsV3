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
console.log('SDK integration safety guard tests passed.')
