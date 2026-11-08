import { spawn } from 'node:child_process'
import { existsSync } from 'node:fs'
import { mkdtemp, readFile, realpath, rm } from 'node:fs/promises'
import net from 'node:net'
import os from 'node:os'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'
import { resolveSupportedPhp } from './lib/php-runtime.mjs'
import { detectUtf8mb4Collation } from './lib/mysql-collation.mjs'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const backendPort = Number(process.env.LOVECARDS_E2E_BACKEND_PORT || process.env.LOVECARDS_DEV_BACKEND_PORT || 6001)
const adminPort = Number(process.env.LOVECARDS_E2E_ADMIN_PORT || 3000)
const database = process.env.LOVECARDS_E2E_DB_NAME || `lovecards_e2e_${Date.now()}`
const adminAccount = process.env.LOVECARDS_E2E_ADMIN_ACCOUNT || process.env.LOVECARDS_SDK_TEST_ADMIN_ACCOUNT || ''
const adminPassword = process.env.LOVECARDS_E2E_ADMIN_PASSWORD || process.env.LOVECARDS_SDK_TEST_ADMIN_PASSWORD || ''
const databaseHost = process.env.LOVECARDS_E2E_DB_HOST || '127.0.0.1'
const npmCommand = process.platform === 'win32' ? 'npm.cmd' : 'npm'
const managedGroups = new WeakSet()

if (process.env.LOVECARDS_E2E_ISOLATED !== '1') {
  throw new Error('Refusing admin E2E tests: set LOVECARDS_E2E_ISOLATED=1')
}
if (!/^lovecards_e2e_[a-z0-9_]+$/.test(database)) {
  throw new Error('LOVECARDS_E2E_DB_NAME must start with lovecards_e2e_')
}
if (!adminAccount || !adminPassword) {
  throw new Error('Missing LOVECARDS_E2E_ADMIN_ACCOUNT and LOVECARDS_E2E_ADMIN_PASSWORD')
}
if (!['127.0.0.1', 'localhost', '::1'].includes(databaseHost)) {
  throw new Error('Refusing admin E2E tests: LOVECARDS_E2E_DB_HOST must be local')
}

function listening(port) {
  return new Promise((resolve) => {
    const socket = net.createConnection({ host: '127.0.0.1', port })
    socket.once('connect', () => { socket.destroy(); resolve(true) })
    socket.once('error', () => resolve(false))
  })
}

function start(command, args, env, cwd = root, managed = false) {
  const isWindowsCommand = process.platform === 'win32' && /\.cmd$/i.test(command)
  const executable = isWindowsCommand ? process.env.ComSpec || 'cmd.exe' : command
  const executableArgs = isWindowsCommand
    ? ['/d', '/s', '/c', [command, ...args].map((value) => String(value).includes(' ') ? `"${value}"` : value).join(' ')]
    : args
  const child = spawn(executable, executableArgs, {
    cwd,
    env: { ...process.env, ...env },
    stdio: 'inherit',
    windowsHide: true,
    detached: managed && process.platform !== 'win32',
  })
  if (managed && process.platform !== 'win32') managedGroups.add(child)
  child.once('error', (error) => console.error(`[e2e] failed to start ${command}: ${error.message}`))
  return child
}

function waitForTermination(child, timeout) {
  if (child.exitCode !== null || child.signalCode !== null) return Promise.resolve(true)
  return new Promise((resolve) => {
    let timer
    const finish = (exited) => {
      clearTimeout(timer)
      child.off('exit', onExit)
      resolve(exited)
    }
    const onExit = () => finish(true)
    timer = setTimeout(() => finish(false), timeout)
    child.once('exit', onExit)
    if (child.exitCode !== null || child.signalCode !== null) finish(true)
  })
}

async function stop(child) {
  if (!child) return
  if (process.platform === 'win32') {
    if (child.killed || child.exitCode !== null || child.signalCode !== null) return
    await new Promise((resolve) => {
      const killer = spawn('taskkill.exe', ['/pid', String(child.pid), '/t', '/f'], { windowsHide: true, stdio: 'ignore' })
      killer.once('error', resolve)
      killer.once('exit', resolve)
    })
    return
  }
  const signal = (value) => {
    try {
      if (managedGroups.has(child)) process.kill(-child.pid, value)
      else child.kill(value)
    } catch (error) {
      if (error?.code !== 'ESRCH') throw error
    }
  }
  signal('SIGTERM')
  if (await waitForTermination(child, 10_000)) return
  signal('SIGKILL')
  await waitForTermination(child, 5_000)
}

function waitForExit(child) {
  if (child.exitCode !== null) return Promise.resolve(child.exitCode)
  if (child.signalCode !== null) return Promise.resolve(1)
  return new Promise((resolve) => {
    child.once('error', () => resolve(1))
    child.once('exit', (code) => resolve(code ?? 1))
  })
}

async function waitFor(url, timeout = 120_000) {
  const deadline = Date.now() + timeout
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url)
      if (response.status < 500) return
    } catch {
    }
    await new Promise((resolve) => setTimeout(resolve, 500))
  }
  throw new Error(`Timed out waiting for ${url}`)
}

async function installDefaultTheme(origin, token) {
  const manifest = JSON.parse(await readFile(path.join(root, 'apps/default-theme/theme.json'), 'utf8'))
  const archivePath = process.env.LOVECARDS_E2E_THEME_ZIP
    || path.join(root, 'apps/default-theme/release', `${manifest.id}-${manifest.version}.zip`)
  const archive = await readFile(archivePath)
  const form = new FormData()
  form.set('file', new Blob([archive], { type: 'application/zip' }), path.basename(archivePath))
  const headers = { Authorization: `Bearer ${token}`, 'X-Token': token }
  const upload = await fetch(`${origin}/api/admin/theme/upload`, { method: 'POST', headers, body: form })
  const payload = await upload.json().catch(() => null)
  if (!upload.ok || !payload?.data?.id || !payload?.data?.release) {
    throw new Error(`Theme E2E install failed (${upload.status}): ${JSON.stringify(payload).slice(0, 500)}`)
  }
  const activate = await fetch(`${origin}/api/admin/theme/activate`, {
    method: 'POST',
    headers: { ...headers, 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: payload.data.id, release: payload.data.release }),
  })
  if (activate.status !== 204) throw new Error(`Theme E2E activation failed: ${activate.status} ${await activate.text()}`)
}

async function installDefaultThemeForTest(origin) {
  const login = await fetch(`${origin}/api/session/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ account: adminAccount, password: adminPassword }),
  })
  const payload = await login.json().catch(() => null)
  const token = payload?.data?.token
  if (!login.ok || typeof token !== 'string' || token === '') throw new Error('Theme E2E login failed')
  await installDefaultTheme(origin, token)
}

if (await listening(backendPort) || await listening(adminPort)) {
  throw new Error(`Refusing E2E tests while ports ${backendPort} or ${adminPort} are already in use`)
}

const { binary: php } = resolveSupportedPhp()
const temporaryRoot = await mkdtemp(path.join(os.tmpdir(), 'lovecards-admin-e2e-'))
const instanceRoot = path.join(temporaryRoot, 'instance')
const adminRoot = path.join(root, 'apps/admin')
const nuxtCli = path.join(adminRoot, 'node_modules/nuxt/bin/nuxt.mjs')
const bundledOpenSsl = path.join(path.dirname(php), 'extras', 'ssl', 'openssl.cnf')
const openSslConfig = process.env.OPENSSL_CONF || (existsSync(bundledOpenSsl) ? bundledOpenSsl : '')
const databaseEnv = {
  DATABASE_HOSTNAME: databaseHost,
  DATABASE_HOSTPORT: process.env.LOVECARDS_E2E_DB_PORT || '3306',
  DATABASE_USERNAME: process.env.LOVECARDS_E2E_DB_USER || 'root',
  DATABASE_PASSWORD: process.env.LOVECARDS_E2E_DB_PASSWORD || '',
}
const databaseCollation = await detectUtf8mb4Collation(php, databaseEnv)
const dbEnv = {
  LOVECARDS_E2E_DB_NAME: database,
  DATABASE_DRIVER: 'mysql',
  DATABASE_TYPE: 'mysql',
  DATABASE_HOSTNAME: databaseHost,
  DATABASE_HOSTPORT: process.env.LOVECARDS_E2E_DB_PORT || '3306',
  DATABASE_DATABASE: database,
  DATABASE_USERNAME: process.env.LOVECARDS_E2E_DB_USER || 'root',
  DATABASE_PASSWORD: process.env.LOVECARDS_E2E_DB_PASSWORD || '',
  PHP_DATABASE_DRIVER: 'mysql',
  PHP_DATABASE_TYPE: 'mysql',
  PHP_DATABASE_HOSTNAME: databaseHost,
  PHP_DATABASE_HOSTPORT: process.env.LOVECARDS_E2E_DB_PORT || '3306',
  PHP_DATABASE_DATABASE: database,
  PHP_DATABASE_USERNAME: process.env.LOVECARDS_E2E_DB_USER || 'root',
  PHP_DATABASE_PASSWORD: process.env.LOVECARDS_E2E_DB_PASSWORD || '',
  PHP_DATABASE_COLLATION: databaseCollation,
  // Guest permission tests require a visitor token. Keep the policy scoped to
  // this disposable backend process instead of relying on a persistent write.
  // ThinkPHP env() 只读 PHP_ 前缀（Env::getEnv），旧无前缀键从未生效；
  // 隔离库 configs 无对应行时访客模式会被判为关闭。
  PHP_CORE_VISITOR_MODE: 'true',
  PHP_USER_CAPTCHA: 'false',
  PHP_CAPTCHA_CAPTCHA_ENABLED: 'false',
  APP_DEBUG: 'true',
  PHP_APP_DEBUG: 'true',
  // 入口 loadEnv 只读 .env.e2e（不存在）：带 .env 的开发仓库里后端进程必须
  // 严格使用上面注入的隔离库连接参数（CI 裸检出无 .env，行为不变）。
  LC_ENV_NAME: 'e2e',
  LC_INSTANCE_ROOT: instanceRoot,
  ...(openSslConfig ? { OPENSSL_CONF: openSslConfig } : {}),
  RANDFILE: path.join(temporaryRoot, 'openssl.rand'),
}
let backend
let admin
let databaseCreated = false
let exitCode = 1
try {
  const bootstrap = start(php, ['scripts/bootstrap-e2e.php', 'create', database], dbEnv)
  console.log(`[e2e] bootstrapping ${database}`)
  const bootstrapExit = await waitForExit(bootstrap)
  if (bootstrapExit !== 0) throw new Error('E2E database bootstrap failed')
  databaseCreated = true
  console.log('[e2e] database ready')

  backend = start(process.execPath, ['scripts/dev-backend.mjs'], {
    ...dbEnv,
    LOVECARDS_DEV_BACKEND_PORT: String(backendPort),
    // PHP parses multipart bodies before ThinkPHP runs. Keep isolated E2E
    // uploads above the signed Release limit (512 MiB) unless the caller
    // deliberately supplies a stricter/alternate profile.
    LOVECARDS_DEV_PHP_POST_MAX_SIZE: process.env.LOVECARDS_E2E_PHP_POST_MAX_SIZE
      || process.env.LOVECARDS_DEV_PHP_POST_MAX_SIZE
      || '768M',
    LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE: process.env.LOVECARDS_E2E_PHP_UPLOAD_MAX_FILESIZE
      || process.env.LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE
      || '512M',
    LOVECARDS_DEV_PHP_INI: process.env.LOVECARDS_E2E_PHP_INI || process.env.LOVECARDS_DEV_PHP_INI || '',
    LOVECARDS_E2E_ISOLATED: '1',
    LOVECARDS_ALLOW_UNSUPPORTED_PHP: process.env.LOVECARDS_ALLOW_UNSUPPORTED_PHP || '0',
  }, root, true)
  console.log(`[e2e] starting backend :${backendPort}`)
  await waitFor(`http://127.0.0.1:${backendPort}/api/cards?list_rows=1`)

  let themeInstalled = false
  // SDK 套件也会验证主题配置接口，因此即使不跑主题浏览器测试，也需要安装隔离主题夹具。
  if (process.env.LOVECARDS_E2E_RUN_SDK_INTEGRATION === '1') {
    await installDefaultThemeForTest(`http://127.0.0.1:${backendPort}`)
    themeInstalled = true
  }

  if (process.env.LOVECARDS_E2E_RUN_SDK_INTEGRATION === '1') {
    const sdk = start(npmCommand, ['run', 'test:sdk:integration'], {
      ...dbEnv,
      LOVECARDS_SDK_TEST_ISOLATED: '1',
      LOVECARDS_SDK_TEST_BASE_URL: `http://127.0.0.1:${backendPort}/api`,
      LOVECARDS_SDK_TEST_ADMIN_ACCOUNT: adminAccount,
      LOVECARDS_SDK_TEST_ADMIN_PASSWORD: adminPassword,
    })
    if (await waitForExit(sdk) !== 0) throw new Error('SDK integration suite failed')
  }

  // Keep the long-lived Nuxt process as our direct child. On Windows an
  // npm.cmd wrapper can exit before Nuxt and leave an orphan that holds the
  // E2E terminal open after every assertion has passed.
  admin = start(process.execPath, [nuxtCli, 'dev', '--host', '127.0.0.1', '--port', String(adminPort)], {
    LOVECARDS_E2E_ISOLATED: '1',
    LOVECARDS_E2E_NUXT_BUILD_DIR: path.join(temporaryRoot, 'nuxt'),
    LOVECARDS_DEV_BACKEND_ORIGIN: `http://127.0.0.1:${backendPort}`,
    NUXT_PUBLIC_API_BASE: '/api',
    NUXT_API_PROXY_TARGET: `http://127.0.0.1:${backendPort}/api`,
    NUXT_PUBLIC_MATOMO_ENABLED: 'false',
  }, adminRoot, true)
  console.log(`[e2e] starting admin :${adminPort}`)
  await waitFor(`http://127.0.0.1:${adminPort}/admin/login`)
  const test = start(process.execPath, ['node_modules/playwright/cli.js', 'test', '--config', 'apps/admin/playwright.config.ts', ...process.argv.slice(2)], {
    ...dbEnv,
    LOVECARDS_E2E_ISOLATED: '1',
    LOVECARDS_E2E_MANAGED_SERVERS: '1',
    LOVECARDS_DEV_BACKEND_ORIGIN: `http://127.0.0.1:${backendPort}`,
    LOVECARDS_DEV_ADMIN_ORIGIN: `http://127.0.0.1:${adminPort}`,
  })
  exitCode = await waitForExit(test)
  if (exitCode === 0 && process.env.LOVECARDS_E2E_RUN_THEME === '1') {
    if (!themeInstalled) await installDefaultThemeForTest(`http://127.0.0.1:${backendPort}`)
    const theme = start(process.execPath, ['scripts/run-theme-e2e.mjs'], {
      LOVECARDS_THEME_ORIGIN: `http://127.0.0.1:${backendPort}`,
      LOVECARDS_THEME_TEST_ACCOUNT: adminAccount,
      LOVECARDS_THEME_TEST_PASSWORD: adminPassword,
      LOVECARDS_THEME_TEST_ISOLATED: '1',
    })
    exitCode = await waitForExit(theme)
  }
} finally {
  await Promise.all([stop(admin), stop(backend)])
  if (databaseCreated) {
    const cleanup = start(php, ['scripts/bootstrap-e2e.php', 'drop', database], {
      LOVECARDS_E2E_ISOLATED: '1',
      LOVECARDS_E2E_DB_HOST: databaseHost,
      LOVECARDS_E2E_DB_PORT: process.env.LOVECARDS_E2E_DB_PORT || '3306',
      LOVECARDS_E2E_DB_USER: process.env.LOVECARDS_E2E_DB_USER || 'root',
      LOVECARDS_E2E_DB_PASSWORD: process.env.LOVECARDS_E2E_DB_PASSWORD || '',
    })
    const cleanupExit = await waitForExit(cleanup)
    if (cleanupExit !== 0) {
      console.error(`[e2e] failed to clean up ${database}`)
      exitCode = 1
    }
  }
  try {
    const temp = await realpath(os.tmpdir())
    const resolved = await realpath(temporaryRoot)
    if (resolved !== temp && resolved.startsWith(`${temp}${path.sep}`)) {
      await rm(resolved, { recursive: true, force: true })
    } else {
      throw new Error(`Refusing to remove non-temporary E2E directory: ${resolved}`)
    }
  } catch (error) {
    console.error(`[e2e] temporary cleanup failed: ${error.message}`)
    exitCode = 1
  }
}

process.exit(exitCode)
