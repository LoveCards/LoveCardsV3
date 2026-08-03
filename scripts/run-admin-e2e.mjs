import { spawn } from 'node:child_process'
import net from 'node:net'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const backendPort = Number(process.env.LOVECARDS_E2E_BACKEND_PORT || process.env.LOVECARDS_DEV_BACKEND_PORT || 6001)
const adminPort = Number(process.env.LOVECARDS_E2E_ADMIN_PORT || 3000)
const database = process.env.LOVECARDS_E2E_DB_NAME || `lovecards_e2e_${Date.now()}`

if (process.env.LOVECARDS_E2E_ISOLATED !== '1') {
  throw new Error('Refusing admin E2E tests: set LOVECARDS_E2E_ISOLATED=1')
}
if (!/^lovecards_e2e_[a-z0-9_]+$/.test(database)) {
  throw new Error('LOVECARDS_E2E_DB_NAME must start with lovecards_e2e_')
}

function listening(port) {
  return new Promise((resolve) => {
    const socket = net.createConnection({ host: '127.0.0.1', port })
    socket.once('connect', () => { socket.destroy(); resolve(true) })
    socket.once('error', () => resolve(false))
  })
}

function start(command, args, env) {
  const isWindowsCommand = process.platform === 'win32' && /\.cmd$/i.test(command)
  const executable = isWindowsCommand ? process.env.ComSpec || 'cmd.exe' : command
  const executableArgs = isWindowsCommand
    ? ['/d', '/s', '/c', [command, ...args].map((value) => String(value).includes(' ') ? `"${value}"` : value).join(' ')]
    : args
  const child = spawn(executable, executableArgs, {
    cwd: root,
    env: { ...process.env, ...env },
    stdio: 'inherit',
    windowsHide: true,
  })
  child.once('error', (error) => console.error(`[e2e] failed to start ${command}: ${error.message}`))
  return child
}

function stop(child) {
  if (!child || child.killed) return
  if (process.platform === 'win32') {
    spawn('taskkill.exe', ['/pid', String(child.pid), '/t', '/f'], { windowsHide: true, stdio: 'ignore' })
  } else {
    child.kill('SIGTERM')
  }
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

if (await listening(backendPort) || await listening(adminPort)) {
  throw new Error('Refusing E2E tests while ports 6001 or 3000 are already in use')
}

const php = process.env.PHP_BINARY || 'C:\\software\\phpstudy_pro\\Extensions\\php\\php8.0.2nts\\php.exe'
const dbEnv = {
  LOVECARDS_E2E_DB_NAME: database,
  PHP_DATABASE_DRIVER: 'mysql',
  PHP_DATABASE_TYPE: 'mysql',
  PHP_DATABASE_HOSTNAME: process.env.LOVECARDS_E2E_DB_HOST || '127.0.0.1',
  PHP_DATABASE_HOSTPORT: process.env.LOVECARDS_E2E_DB_PORT || '3306',
  PHP_DATABASE_DATABASE: database,
  PHP_DATABASE_USERNAME: process.env.LOVECARDS_E2E_DB_USER || 'root',
  PHP_DATABASE_PASSWORD: process.env.LOVECARDS_E2E_DB_PASSWORD || '',
  PHP_APP_DEBUG: 'true',
}

const bootstrap = start(php, ['scripts/bootstrap-e2e.php', 'create', database], dbEnv)
console.log(`[e2e] bootstrapping ${database}`)
const bootstrapExit = await new Promise((resolve) => bootstrap.once('exit', (code) => resolve(code ?? 1)))
if (bootstrapExit !== 0) throw new Error('E2E database bootstrap failed')
console.log('[e2e] database ready')

const backend = start(process.execPath, ['scripts/dev-backend.mjs'], {
  ...dbEnv,
  LOVECARDS_DEV_BACKEND_PORT: String(backendPort),
  LOVECARDS_E2E_ISOLATED: '1',
  LOVECARDS_ALLOW_UNSUPPORTED_PHP: process.env.LOVECARDS_ALLOW_UNSUPPORTED_PHP || '0',
})
const admin = start('npm.cmd', ['run', 'dev', '--workspace', '@lovecards/admin', '--', '--host', '127.0.0.1', '--port', String(adminPort)], {
  LOVECARDS_E2E_ISOLATED: '1',
  LOVECARDS_DEV_BACKEND_ORIGIN: `http://127.0.0.1:${backendPort}`,
  NUXT_API_PROXY_TARGET: `http://127.0.0.1:${backendPort}/api`,
  NUXT_PUBLIC_MATOMO_ENABLED: 'false',
})
console.log(`[e2e] starting backend :${backendPort} and admin :${adminPort}`)

let exitCode = 1
try {
  await waitFor(`http://127.0.0.1:${backendPort}/api/cards?list_rows=1`)
  await waitFor(`http://127.0.0.1:${adminPort}/admin/login`)
  const test = start(process.execPath, ['node_modules/playwright/cli.js', 'test', '--config', 'apps/admin/playwright.config.ts', ...process.argv.slice(2)], {
    ...dbEnv,
    LOVECARDS_E2E_ISOLATED: '1',
    LOVECARDS_E2E_MANAGED_SERVERS: '1',
    LOVECARDS_DEV_BACKEND_ORIGIN: `http://127.0.0.1:${backendPort}`,
    LOVECARDS_DEV_ADMIN_ORIGIN: `http://127.0.0.1:${adminPort}`,
  })
  exitCode = await new Promise((resolve) => test.once('exit', (code) => resolve(code ?? 1)))
} finally {
  stop(admin)
  stop(backend)
  const cleanup = start(php, ['scripts/bootstrap-e2e.php', 'drop', database], {
    LOVECARDS_E2E_DB_HOST: process.env.LOVECARDS_E2E_DB_HOST || '127.0.0.1',
    LOVECARDS_E2E_DB_PORT: process.env.LOVECARDS_E2E_DB_PORT || '3306',
    LOVECARDS_E2E_DB_USER: process.env.LOVECARDS_E2E_DB_USER || 'root',
    LOVECARDS_E2E_DB_PASSWORD: process.env.LOVECARDS_E2E_DB_PASSWORD || '',
  })
  await new Promise((resolve) => cleanup.once('exit', resolve))
}

process.exit(exitCode)
