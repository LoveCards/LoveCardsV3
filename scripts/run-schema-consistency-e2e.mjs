import { mkdtemp, rm } from 'node:fs/promises'
import { spawn } from 'node:child_process'
import os from 'node:os'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'
import { existsSync } from 'node:fs'
import { resolveSupportedPhp } from './lib/php-runtime.mjs'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const host = process.env.LOVECARDS_E2E_DB_HOST || '127.0.0.1'
const port = process.env.LOVECARDS_E2E_DB_PORT || '3306'
const user = process.env.LOVECARDS_E2E_DB_USER || 'root'
const password = process.env.LOVECARDS_E2E_DB_PASSWORD || ''
if (process.env.LOVECARDS_SCHEMA_E2E_ISOLATED !== '1') {
  throw new Error('Refusing schema E2E: set LOVECARDS_SCHEMA_E2E_ISOLATED=1')
}
if (!['127.0.0.1', 'localhost', '::1'].includes(host) || !/^\d{1,5}$/.test(port) || Number(port) < 1 || Number(port) > 65535) {
  throw new Error('Schema E2E requires a valid localhost database address')
}

const { binary: php } = resolveSupportedPhp()
const tempRoot = await mkdtemp(path.join(os.tmpdir(), 'lovecards-schema-e2e-'))
const nonce = `${Date.now()}_${process.pid}_${Math.random().toString(36).slice(2, 8)}`
const clean = `lovecards_e2e_schema_clean_${nonce}`
const legacy = `lovecards_e2e_schema_legacy_${nonce}`
const bundledOpenSsl = path.join(path.dirname(php), 'extras', 'ssl', 'openssl.cnf')
const openSsl = process.env.OPENSSL_CONF || (existsSync(bundledOpenSsl) ? bundledOpenSsl : '')
const baseEnv = {
  ...process.env,
  LOVECARDS_E2E_ISOLATED: '1',
  LOVECARDS_E2E_DB_HOST: host,
  LOVECARDS_E2E_DB_PORT: port,
  LOVECARDS_E2E_DB_USER: user,
  LOVECARDS_E2E_DB_PASSWORD: password,
  LOVECARDS_E2E_ADMIN_ACCOUNT: '',
  LOVECARDS_E2E_ADMIN_PASSWORD: '',
  LC_ENV_NAME: 'e2e',
  PHP_CACHE_DRIVER: 'file',
  LC_SCHEMA_COMPARE_USER: user,
  LC_SCHEMA_COMPARE_PASSWORD: password,
  ...(openSsl ? { OPENSSL_CONF: openSsl } : {}),
}

function run(args, env = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(php, args, {
      cwd: root,
      env: { ...baseEnv, ...env },
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe'],
    })
    let output = ''
    child.stdout.setEncoding('utf8')
    child.stderr.setEncoding('utf8')
    child.stdout.on('data', (chunk) => { output += chunk })
    child.stderr.on('data', (chunk) => { output += chunk })
    child.once('error', reject)
    child.once('exit', (code) => code === 0 ? resolve(output.trim()) : reject(new Error(output.trim() || `PHP exited ${code}`)))
  })
}

const created = []
try {
  for (const [mode, database] of [['create', clean], ['create-legacy', legacy]]) {
    const instanceRoot = path.join(tempRoot, database)
    await run(['scripts/bootstrap-e2e.php', mode, database], { LC_INSTANCE_ROOT: instanceRoot })
    created.push(database)
    console.log(`[schema-e2e] ${mode} schema settled: ${database}`)
  }
  const dsn = (database) => `mysql:host=${host};port=${port};dbname=${database};charset=utf8mb4`
  console.log(await run(['scripts/verify-schema-consistency.php', dsn(clean), dsn(legacy)]))
} finally {
  let cleanupFailed = false
  for (const database of created.reverse()) {
    try {
      await run(['scripts/bootstrap-e2e.php', 'drop', database])
    } catch (error) {
      cleanupFailed = true
      console.error(`[schema-e2e] database cleanup failed for ${database}: ${error.message}`)
    }
  }
  await rm(tempRoot, { recursive: true, force: true })
  if (cleanupFailed) process.exitCode = 1
}
