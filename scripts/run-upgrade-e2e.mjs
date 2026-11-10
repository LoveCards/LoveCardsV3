import { chmod, cp, lstat, mkdtemp, readdir, readFile, realpath, rm, stat, writeFile } from 'node:fs/promises'
import { existsSync, openAsBlob } from 'node:fs'
import { spawn } from 'node:child_process'
import net from 'node:net'
import os from 'node:os'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'
import { resolveSupportedPhp } from './lib/php-runtime.mjs'
import { detectUtf8mb4Collation } from './lib/mysql-collation.mjs'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const backendRoot = path.join(root, 'apps', 'backend')
const database = process.env.LOVECARDS_UPGRADE_E2E_DB_NAME || `lovecards_e2e_upgrade_${Date.now()}`
const databaseHost = process.env.LOVECARDS_E2E_DB_HOST || '127.0.0.1'
const databasePort = process.env.LOVECARDS_E2E_DB_PORT || '3306'
const databaseUser = process.env.LOVECARDS_E2E_DB_USER || 'root'
const databasePassword = process.env.LOVECARDS_E2E_DB_PASSWORD || ''
const backendPort = Number(process.env.LOVECARDS_UPGRADE_E2E_PORT || 16003)
const adminAccount = process.env.LOVECARDS_E2E_ADMIN_ACCOUNT || ''
const adminPassword = process.env.LOVECARDS_E2E_ADMIN_PASSWORD || ''
const managedGroups = new WeakSet()
const releaseCopyExcludedRoots = new Set([
  '.git', '.github', '.idea', '.vscode', '.lovecards-instance',
  'node_modules', 'runtime', 'tests',
])
const contractProbeTable = 'e2e_upgrade_contract_probe'
const contractProbeMigration = '20991231235959_e2e_contract_probe.sql'

if (process.env.LOVECARDS_UPGRADE_E2E_ISOLATED !== '1') {
  throw new Error('Refusing upgrade E2E: set LOVECARDS_UPGRADE_E2E_ISOLATED=1')
}
if (!/^lovecards_e2e_[a-z0-9_]+$/.test(database)) {
  throw new Error('LOVECARDS_UPGRADE_E2E_DB_NAME must start with lovecards_e2e_')
}
if (!['127.0.0.1', 'localhost', '::1'].includes(databaseHost)) {
  throw new Error('Refusing upgrade E2E: database host must be local')
}
if (!Number.isInteger(backendPort) || backendPort < 1024 || backendPort > 65535) {
  throw new Error('LOVECARDS_UPGRADE_E2E_PORT must be a valid unprivileged port')
}
if (!adminAccount || !adminPassword) {
  throw new Error('Missing LOVECARDS_E2E_ADMIN_ACCOUNT and LOVECARDS_E2E_ADMIN_PASSWORD')
}

function start(command, args, env, stdio = 'inherit', managed = false) {
  const child = spawn(command, args, {
    cwd: root,
    env: { ...process.env, ...env },
    stdio,
    windowsHide: true,
    detached: managed && process.platform !== 'win32',
  })
  if (managed && process.platform !== 'win32') managedGroups.add(child)
  child.once('error', (error) => console.error(`[upgrade-e2e] failed to start ${command}: ${error.message}`))
  return child
}

async function run(command, args, env = {}) {
  const child = start(command, args, env, ['ignore', 'pipe', 'pipe'])
  let stdout = ''
  let stderr = ''
  child.stdout.setEncoding('utf8')
  child.stderr.setEncoding('utf8')
  child.stdout.on('data', (chunk) => { stdout += chunk })
  child.stderr.on('data', (chunk) => { stderr += chunk })
  const code = await new Promise((resolve) => {
    child.once('error', () => resolve(1))
    child.once('exit', (value) => resolve(value ?? 1))
  })
  if (code !== 0) throw new Error(`${path.basename(command)} failed (${code}): ${(stderr || stdout).trim()}`)
  return stdout.trim()
}

function listening(port) {
  return new Promise((resolve) => {
    const socket = net.createConnection({ host: '127.0.0.1', port })
    socket.once('connect', () => { socket.destroy(); resolve(true) })
    socket.once('error', () => resolve(false))
  })
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
    const killer = spawn('taskkill.exe', ['/pid', String(child.pid), '/t', '/f'], { windowsHide: true, stdio: 'ignore' })
    await new Promise((resolve) => { killer.once('error', resolve); killer.once('exit', resolve) })
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

async function api(pathname, { token, method = 'GET', json, body, expected = [200] } = {}) {
  const headers = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (json !== undefined) headers['Content-Type'] = 'application/json'
  const response = await fetch(`http://127.0.0.1:${backendPort}/api${pathname}`, {
    method,
    headers,
    body: json === undefined ? body : JSON.stringify(json),
  })
  const text = await response.text()
  let payload = null
  if (text !== '') {
    try { payload = JSON.parse(text) } catch { payload = text }
  }
  if (!expected.includes(response.status)) {
    throw new Error(`${method} ${pathname} returned ${response.status}: ${text.slice(0, 1000)}`)
  }
  return { response, data: payload && typeof payload === 'object' && 'data' in payload ? payload.data : payload, payload }
}

async function expectRejected(pathname, options) {
  const result = await api(pathname, { ...options, expected: [400, 409, 422] })
  if (!result.payload || typeof result.payload !== 'object' || result.payload.success !== false) {
    throw new Error(`${options.method || 'GET'} ${pathname} did not return a structured rejection`)
  }
}

async function safeRemove(directory) {
  const temp = await realpath(os.tmpdir())
  let resolved
  try { resolved = await realpath(directory) } catch { return }
  if (resolved === temp || !resolved.startsWith(`${temp}${path.sep}`)) {
    throw new Error(`Refusing to remove non-temporary E2E directory: ${resolved}`)
  }
  try {
    await rm(resolved, { recursive: true, force: true })
  } catch (error) {
    if (error.code !== 'EACCES' && error.code !== 'EPERM') throw error
    // 激活后的 Release 目录按不可变发布设计为只读；非 root 运行器（CI）删除时
    // 会 EACCES。仅对已确认在临时根内的目录自底向上恢复写权限后重试。
    // 首次 rm 已删掉部分目录，恢复遍历必须容忍 ENOENT。
    const restoreWritable = async (current) => {
      let entries
      try { entries = await readdir(current, { withFileTypes: true }) }
      catch (error) { if (error.code === 'ENOENT') return; throw error }
      for (const entry of entries) {
        const child = path.join(current, entry.name)
        if (entry.isDirectory() && !entry.isSymbolicLink()) await restoreWritable(child)
      }
      try { await chmod(current, 0o755) }
      catch (error) { if (error.code !== 'ENOENT') throw error }
    }
    await restoreWritable(resolved)
    await rm(resolved, { recursive: true, force: true })
  }
}

async function prepareReleaseSource(destination) {
  const sourceRoot = await realpath(backendRoot)
  const sourcePrefix = `${sourceRoot}${path.sep}`
  await cp(sourceRoot, destination, {
    recursive: true,
    dereference: true,
    filter: async (source) => {
      const relative = path.relative(sourceRoot, source).split(path.sep).join('/')
      if (relative === '') return true
      if (relative === '..' || relative.startsWith('../')) throw new Error('Release source copy escaped the backend root')
      const segments = relative.split('/')
      if (releaseCopyExcludedRoots.has(segments[0])) return false
      if (relative === 'release.json' || relative === 'handler-catalog.json' || relative === 'install.lock') return false
      if (relative === 'config/rsa.php' || relative.startsWith('config/rsa/')) return false
      if (relative.startsWith('public/storage/') || relative.startsWith('themes/.releases/')) return false
      if ((segments[0] === '.env' || segments[0].startsWith('.env.')) && segments[0] !== '.env.example') return false
      const info = await lstat(source)
      if (info.isSymbolicLink()) {
        const target = await realpath(source)
        if (target !== sourceRoot && !target.startsWith(sourcePrefix)) {
          throw new Error(`Release source symlink escapes backend root: ${relative}`)
        }
      }
      return true
    },
  })

  const migration = [
    '-- E2E-only migration injected into the disposable Release source.',
    '-- @lc-phase preflight',
    'SELECT 1;',
    '-- @lc-phase expand',
    `CREATE TABLE IF NOT EXISTS \`${contractProbeTable}\` (`,
    '  `id` TINYINT UNSIGNED NOT NULL,',
    '  `expand_marker` VARCHAR(32) NOT NULL,',
    '  `contract_marker` VARCHAR(32) NULL,',
    '  PRIMARY KEY (`id`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
    `INSERT INTO \`${contractProbeTable}\` (\`id\`, \`expand_marker\`, \`contract_marker\`)`,
    "VALUES (1, 'expand-complete', NULL)",
    "ON DUPLICATE KEY UPDATE `expand_marker` = 'expand-complete', `contract_marker` = NULL;",
    '-- @lc-phase backfill',
    `UPDATE \`${contractProbeTable}\` SET \`expand_marker\` = 'backfill-complete' WHERE \`id\` = 1;`,
    '-- @lc-phase verify',
    `SELECT \`id\` FROM \`${contractProbeTable}\` WHERE \`id\` = 1 AND \`expand_marker\` = 'backfill-complete';`,
    '-- @lc-phase contract',
    `UPDATE \`${contractProbeTable}\` SET \`contract_marker\` = 'contract-complete' WHERE \`id\` = 1;`,
    '',
  ].join('\n')
  await writeFile(path.join(destination, 'database', 'migrations', contractProbeMigration), migration, { flag: 'wx' })
}

if (await listening(backendPort)) throw new Error(`Refusing upgrade E2E: port ${backendPort} is already in use`)

const { binary: php } = resolveSupportedPhp()
const temporaryRoot = await mkdtemp(path.join(os.tmpdir(), 'lovecards-upgrade-e2e-'))
const instanceRoot = path.join(temporaryRoot, 'instance')
const releaseSource = path.join(temporaryRoot, 'release-source')
const privateKey = path.join(temporaryRoot, 'release-private.pem')
const publicKey = path.join(temporaryRoot, 'release-public.pem')
const releaseZip = path.join(temporaryRoot, 'release.zip')
const releaseId = `upgrade-e2e-${Date.now()}`
const bundledOpenSsl = path.join(path.dirname(php), 'extras', 'ssl', 'openssl.cnf')
const openSslConfig = process.env.OPENSSL_CONF || (existsSync(bundledOpenSsl) ? bundledOpenSsl : '')
const randFile = path.join(temporaryRoot, 'openssl.rand')
await writeFile(randFile, '')
const databaseCollation = await detectUtf8mb4Collation(php, {
  DATABASE_HOSTNAME: databaseHost,
  DATABASE_HOSTPORT: databasePort,
  DATABASE_USERNAME: databaseUser,
  DATABASE_PASSWORD: databasePassword,
})
const dbEnv = {
  LOVECARDS_E2E_ISOLATED: '1',
  LOVECARDS_E2E_DB_HOST: databaseHost,
  LOVECARDS_E2E_DB_PORT: databasePort,
  LOVECARDS_E2E_DB_USER: databaseUser,
  LOVECARDS_E2E_DB_PASSWORD: databasePassword,
  DATABASE_DRIVER: 'mysql',
  DATABASE_TYPE: 'mysql',
  DATABASE_HOSTNAME: databaseHost,
  DATABASE_HOSTPORT: databasePort,
  DATABASE_DATABASE: database,
  DATABASE_USERNAME: databaseUser,
  DATABASE_PASSWORD: databasePassword,
  PHP_DATABASE_DRIVER: 'mysql',
  PHP_DATABASE_TYPE: 'mysql',
  PHP_DATABASE_HOSTNAME: databaseHost,
  PHP_DATABASE_HOSTPORT: databasePort,
  PHP_DATABASE_DATABASE: database,
  PHP_DATABASE_USERNAME: databaseUser,
  PHP_DATABASE_PASSWORD: databasePassword,
  PHP_DATABASE_COLLATION: databaseCollation,
  // 与 run-admin-e2e 相同：入口 loadEnv 只读 .env.e2e（不存在），进程注入的
  // 隔离库连接严格生效（带 .env 的开发仓库本地跑不再被劫持；CI 行为不变）。
  LC_ENV_NAME: 'e2e',
  PHP_CACHE_DRIVER: 'file',
  LC_INSTANCE_ROOT: instanceRoot,
  APP_DEBUG: 'true',
  PHP_APP_DEBUG: 'true',
  ...(openSslConfig ? { OPENSSL_CONF: openSslConfig } : {}),
  RANDFILE: randFile,
}

let backend
let databaseCreated = false
let exitCode = 1
try {
  const keyScript = [
    '$key=openssl_pkey_new(["private_key_type"=>OPENSSL_KEYTYPE_RSA,"private_key_bits"=>2048]);',
    'if($key===false||!openssl_pkey_export($key,$private)){fwrite(STDERR,"key generation failed\\n");exit(1);}',
    '$details=openssl_pkey_get_details($key);$public=is_array($details)?($details["key"]??""):"";',
    'if($public===""||file_put_contents($argv[1],$private)===false||file_put_contents($argv[2],$public)===false){exit(1);}',
  ].join('')
  await run(php, ['-r', keyScript, privateKey, publicKey], {
    ...(openSslConfig ? { OPENSSL_CONF: openSslConfig } : {}),
    RANDFILE: randFile,
  })
  const trustedKeys = JSON.stringify({ 'upgrade-e2e-key': publicKey })

  await run(php, ['scripts/bootstrap-e2e.php', 'create', database], dbEnv)
  databaseCreated = true
  console.log(`[upgrade-e2e] isolated database ready: ${database}`)

  // 新安装故意不预设外部 cron；升级验收须显式声明并观察一次真实 CLI 消费。
  const configureScheduledCron = [
    '$dsn="mysql:host=".getenv("DATABASE_HOSTNAME").";port=".getenv("DATABASE_HOSTPORT").";dbname=".getenv("DATABASE_DATABASE").";charset=utf8mb4";',
    '$pdo=new PDO($dsn,(string)getenv("DATABASE_USERNAME"),(string)getenv("DATABASE_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);',
    '$pdo->exec("UPDATE eventing_consumer_state SET scheduled_trigger=\'cli\',revision=revision+1 WHERE id=1 AND mode=\'scheduled\' AND scheduled_trigger=\'none\'");',
    'if($pdo->query("SELECT scheduled_trigger FROM eventing_consumer_state WHERE id=1")->fetchColumn()!=="cli")exit(1);',
  ].join('')
  await run(php, ['-r', configureScheduledCron], dbEnv)
  await run(php, [path.join('apps', 'backend', 'think'), 'lc:scheduler:run-once', '--max-jobs=1', '--max-ms=100'], dbEnv)
  console.log('[upgrade-e2e] scheduled CLI cron declared and observed')

  await prepareReleaseSource(releaseSource)
  console.log('[upgrade-e2e] isolated Release source includes an E2E-only contract migration')

  const buildOutput = await run(php, [
    path.join('apps', 'backend', 'think'), 'lc:release:build', releaseZip,
    '--source', releaseSource,
    '--release-id', releaseId,
    '--release-version', '99.0.0-e2e',
    '--build', releaseId,
    '--sequence', '1',
    '--private-key', privateKey,
    '--key-id', 'upgrade-e2e-key',
    '--optional-facilities', 'redis,worker',
  // ThinkPHP env() 只读 PHP_ 前缀，config/lifecycle.php 的受信公钥必须这样注入。
  ], { ...dbEnv, LC_RELEASE_TRUSTED_KEYS_JSON: trustedKeys, PHP_LC_RELEASE_TRUSTED_KEYS_JSON: trustedKeys })
  const buildResult = buildOutput
    .split(/\r?\n/)
    .map((line) => line.trim())
    .reverse()
    .map((line) => {
      try { return JSON.parse(line) } catch { return null }
    })
    .find((value) => value && typeof value === 'object' && value.release_id)
  if (!buildResult) throw new Error(`Release builder did not emit a JSON result: ${buildOutput.slice(-1000)}`)
  if (buildResult.release_id !== releaseId || (await stat(releaseZip)).size < 1) throw new Error('Release builder returned an invalid artifact')
  console.log(`[upgrade-e2e] signed Release built: ${releaseId}`)

  backend = start(process.execPath, ['scripts/dev-backend.mjs'], {
    ...dbEnv,
    LC_RELEASE_TRUSTED_KEYS_JSON: trustedKeys,
    PHP_LC_RELEASE_TRUSTED_KEYS_JSON: trustedKeys,
    LOVECARDS_DEV_BACKEND_PORT: String(backendPort),
    // PHP parses multipart bodies before ThinkPHP runs. Keep isolated E2E
    // uploads above the signed Release limit (512 MiB) unless the caller
    // deliberately supplies a stricter/alternate profile.
    LOVECARDS_DEV_PHP_POST_MAX_SIZE: process.env.LOVECARDS_UPGRADE_E2E_PHP_POST_MAX_SIZE
      || process.env.LOVECARDS_DEV_PHP_POST_MAX_SIZE
      || '768M',
    LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE: process.env.LOVECARDS_UPGRADE_E2E_PHP_UPLOAD_MAX_FILESIZE
      || process.env.LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE
      || '512M',
    LOVECARDS_DEV_PHP_INI: process.env.LOVECARDS_UPGRADE_E2E_PHP_INI || process.env.LOVECARDS_DEV_PHP_INI || '',
    LOVECARDS_E2E_ISOLATED: '1',
    LOVECARDS_ALLOW_UNSUPPORTED_PHP: process.env.LOVECARDS_ALLOW_UNSUPPORTED_PHP || '0',
  }, 'inherit', true)
  await waitFor(`http://127.0.0.1:${backendPort}/api/cards?list_rows=1`)

  const login = await api('/session/login', {
    method: 'POST',
    json: { account: adminAccount, password: adminPassword },
  })
  const token = login.data?.token
  if (typeof token !== 'string' || token === '') throw new Error('Admin login did not return a JWT')

  const form = new FormData()
  form.set('file', await openAsBlob(releaseZip, { type: 'application/zip' }), 'release.zip')
  let plan = (await api('/admin/operations/upgrades', {
    token,
    method: 'POST',
    body: form,
    expected: [201],
  })).data
  if (plan?.release_id !== releaseId || typeof plan?.plan_id !== 'string') throw new Error('Upgrade create response is invalid')
  const planId = plan.plan_id
  console.log(`[upgrade-e2e] durable plan created: ${planId}`)

  await expectRejected(`/admin/operations/upgrades/${planId}/approve-contract`, {
    token, method: 'POST',
  })
  await expectRejected(`/admin/operations/upgrades/${planId}/recover`, {
    token, method: 'POST',
  })
  let approved = false
  let sawContractWaiting = false
  let servedRelease = false
  let ranActivatedConsumer = false
  let previousRevision = Number(plan.revision || 0)
  const observations = []
  for (let attempt = 0; attempt < 128; attempt++) {
    if (plan.status === 'completed') break
    if (plan.status === 'failed' || plan.status === 'recovered') throw new Error(`Upgrade entered unexpected terminal state: ${plan.status}`)
    if (plan.current_step === 'health_check' && !ranActivatedConsumer) {
      const consumerOutput = await run(php, [path.join('apps', 'backend', 'think'), 'lc:scheduler:run-once', '--max-jobs=1', '--max-ms=100'], dbEnv)
      if (!consumerOutput.includes('claimed=')) throw new Error('Activated Release did not complete a scheduled consumer run')
      ranActivatedConsumer = true
    }
    if (plan.status === 'waiting') {
      if (plan.current_step !== 'contract_migrations') throw new Error(`Upgrade is waiting at unexpected step: ${plan.current_step}`)
      sawContractWaiting = true
      if (!approved) {
        plan = (await api(`/admin/operations/upgrades/${planId}/approve-contract`, {
          token, method: 'POST',
        })).data
        if (plan?.contract_approved !== true) throw new Error('Contract approval response did not persist approval')
        approved = true
        continue
      }
      if (plan?.contract_approved !== true) throw new Error('Approved waiting plan lost its contract approval')
    }

    const result = await api(`/admin/operations/upgrades/${planId}/advance`, {
      token, method: 'POST',
    })
    plan = result.data
    const headerRelease = result.response.headers.get('x-lovecards-release-id')
      || result.response.headers.get('x-lovecards-release')
    if (headerRelease === releaseId) servedRelease = true
    if (Number(plan.revision) <= previousRevision) throw new Error('Upgrade revision did not advance monotonically')
    previousRevision = Number(plan.revision)
    observations.push(`${plan.current_step || 'done'}:${plan.status}`)
  }
  if (plan.status !== 'completed') throw new Error(`Upgrade did not complete after bounded advances: ${observations.slice(-10).join(', ')}`)
  if (!ranActivatedConsumer) throw new Error('Upgrade completed without verifying the activated consumer')

  const detail = await api(`/admin/operations/upgrades/${planId}`, { token })
  if (detail.data?.status !== 'completed' || detail.data?.release_id !== releaseId) throw new Error('Persisted upgrade detail is inconsistent')
  const activation = detail.data.steps?.find((step) => step.name === 'activate_release')
  const health = detail.data.steps?.find((step) => step.name === 'health_check')
  const contract = detail.data.steps?.find((step) => step.name === 'contract_migrations')
  if (activation?.state !== 'succeeded' || health?.state !== 'succeeded') throw new Error('Activation and health steps were not durably completed')
  if (!sawContractWaiting || !approved || detail.data?.contract_approved !== true) throw new Error('Contract migration did not pass through explicit waiting and approval')
  if (contract?.state !== 'succeeded' || typeof detail.data?.milestones?.contract_executed_at !== 'string') {
    throw new Error('Contract migration did not persist its successful execution milestone')
  }
  const pointer = (await readFile(path.join(instanceRoot, 'current'), 'utf8')).trim()
  if (!pointer.includes(releaseId)) throw new Error('Active pointer does not reference the activated Release')
  const finalProbe = await api('/admin/operations/runtime', { token })
  const finalHeader = finalProbe.response.headers.get('x-lovecards-release-id')
    || finalProbe.response.headers.get('x-lovecards-release')
  servedRelease ||= finalHeader === releaseId
  if (!servedRelease) throw new Error('No HTTP response proved that the activated Release served subsequent requests')

  const probeScript = [
    '$dsn="mysql:host=".getenv("DATABASE_HOSTNAME").";port=".getenv("DATABASE_HOSTPORT").";dbname=".getenv("DATABASE_DATABASE").";charset=utf8mb4";',
    '$pdo=new PDO($dsn,(string)getenv("DATABASE_USERNAME"),(string)getenv("DATABASE_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);',
    `$value=$pdo->query("SELECT contract_marker FROM ${contractProbeTable} WHERE id=1")->fetchColumn();`,
    'if($value!=="contract-complete"){fwrite(STDERR,"contract probe mismatch\\n");exit(1);}echo $value;',
  ].join('')
  const contractProbe = await run(php, ['-r', probeScript], dbEnv)
  if (contractProbe !== 'contract-complete') throw new Error('Contract migration fixture result is invalid')

  console.log(`[upgrade-e2e] PASS upload, waiting, explicit contract approval, contract SQL, activation, runtime switch and health (${observations.length} advances)`)
  exitCode = 0
} finally {
  await stop(backend)
  if (databaseCreated) {
    try {
      await run(php, ['scripts/bootstrap-e2e.php', 'drop', database], dbEnv)
    } catch (error) {
      console.error(`[upgrade-e2e] database cleanup failed: ${error.message}`)
      exitCode = 1
    }
  }
  try { await safeRemove(temporaryRoot) } catch (error) {
    console.error(`[upgrade-e2e] temporary cleanup failed: ${error.message}`)
    exitCode = 1
  }
}

process.exit(exitCode)
