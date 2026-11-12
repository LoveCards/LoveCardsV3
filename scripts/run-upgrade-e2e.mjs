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
const cleanDatabase = `${database}_clean`
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
// The reviewed compatibility Release is deliberately built from its committed source.
// The current Release deletes legacy likes columns and requires this predecessor.
const compatibilityCommit = '034ce0a0'

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
}

async function prepareCompatibilitySource(destination, php, temporaryRoot) {
  const archive = path.join(temporaryRoot, 'compatibility-source.zip')
  const extractRoot = path.join(temporaryRoot, 'compatibility-source')
  await run('git', ['archive', '--format=zip', `--output=${archive}`, compatibilityCommit, 'apps/backend'])
  const extract = '$zip=new ZipArchive();if($zip->open($argv[1])!==true||!$zip->extractTo($argv[2]))exit(1);$zip->close();'
  await run(php, ['-r', extract, archive, extractRoot])
  await cp(path.join(extractRoot, 'apps', 'backend'), destination, { recursive: true })
  // Composer dependencies are installed by CI and intentionally absent from Git.
  await cp(path.join(backendRoot, 'vendor'), path.join(destination, 'vendor'), { recursive: true })
}

async function addContractProbe(destination) {
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
const currentReleaseSource = path.join(temporaryRoot, 'current-release-source')
const privateKey = path.join(temporaryRoot, 'release-private.pem')
const publicKey = path.join(temporaryRoot, 'release-public.pem')
const releaseZip = path.join(temporaryRoot, 'release.zip')
const currentReleaseZip = path.join(temporaryRoot, 'current-release.zip')
const releaseId = `upgrade-e2e-${Date.now()}`
const currentReleaseId = `${releaseId}-current`
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
  LC_DEPLOYMENT_ID: `upgrade-e2e-${database}`,
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
let cleanDatabaseCreated = false
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
  dbEnv.PHP_LC_RUNTIME_DRAIN_COMMAND_JSON = JSON.stringify([php, path.join(root, 'scripts/assert-e2e-consumers-stopped.php'), 'contract'])

  await run(php, ['scripts/bootstrap-e2e.php', 'create-legacy-prior', database], dbEnv)
  databaseCreated = true
  console.log(`[upgrade-e2e] isolated prior-version database ready before S11: ${database}`)

  // 签名升级前注入同名自定义过程和未映射旧数据：第一次 preflight
  // 不得覆盖自定义过程；显式移除后第二次仍须因旧数据失败；
  // 修复数据后由操作者显式重试并完成升级。
  const connectScript = '$dsn="mysql:host=".getenv("DATABASE_HOSTNAME").";port=".getenv("DATABASE_HOSTPORT").";dbname=".getenv("DATABASE_DATABASE").";charset=utf8mb4";$pdo=new PDO($dsn,(string)getenv("DATABASE_USERNAME"),(string)getenv("DATABASE_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);'
  await run(php, ['-r', connectScript
    + '$pdo->exec("CREATE PROCEDURE lc_s11_assert_legacy_seed() COMMENT \'e2e-custom-procedure\' SELECT 1");'
    + '$pdo->exec("UPDATE `system` SET value=\'e2e-custom-value\' WHERE id=1");'], dbEnv)

  // 旧版夹具的账本尚无 S11，当前代码身份不能领取任务。这里写入
  // 旧部署最近一次 cron 运行的历史状态夹具；激活后再真实执行
  // 新 Release 的 CLI 消费，不能将该夹具当作旧进程运行验收。
  const configureScheduledCron = [
    '$dsn="mysql:host=".getenv("DATABASE_HOSTNAME").";port=".getenv("DATABASE_HOSTPORT").";dbname=".getenv("DATABASE_DATABASE").";charset=utf8mb4";',
    '$pdo=new PDO($dsn,(string)getenv("DATABASE_USERNAME"),(string)getenv("DATABASE_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);',
    '$pdo->exec("UPDATE eventing_consumer_state SET scheduled_trigger=\'cli\',revision=revision+1 WHERE id=1 AND mode=\'scheduled\' AND scheduled_trigger=\'none\'");',
    'if($pdo->query("SELECT scheduled_trigger FROM eventing_consumer_state WHERE id=1")->fetchColumn()!=="cli")exit(1);',
    '$now=gmdate("Y-m-d H:i:s");',
    '$insert=$pdo->prepare("INSERT INTO scheduler_runs (worker_id,trigger_source,started_at,finished_at,outcome,jobs_processed,release_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)");',
    '$insert->execute(["previous-release-cron","cli",$now,$now,"ok",0,"legacy",$now,$now]);',
  ].join('')
  await run(php, ['-r', configureScheduledCron], dbEnv)
  console.log('[upgrade-e2e] previous Release cron evidence prepared')

  await prepareCompatibilitySource(releaseSource, php, temporaryRoot)
  console.log('[upgrade-e2e] isolated compatibility Release source prepared from reviewed commit')

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
    // PHP 8.4 加载第三方库时会产生废弃提示；避免污染 JSON 响应，
    // 错误仍写入服务端日志。
    LOVECARDS_DEV_PHP_INI: process.env.LOVECARDS_UPGRADE_E2E_PHP_INI || process.env.LOVECARDS_DEV_PHP_INI || '{"display_errors":"0"}',
    LOVECARDS_E2E_ISOLATED: '1',
    LOVECARDS_ALLOW_UNSUPPORTED_PHP: process.env.LOVECARDS_ALLOW_UNSUPPORTED_PHP || '0',
  }, 'inherit', true)
  await waitFor(`http://127.0.0.1:${backendPort}/api/cards?list_rows=1`)

  const login = await api('/session/login', {
    method: 'POST',
    json: { account: adminAccount, password: adminPassword },
  })
  const token = login.data?.token
  if (typeof token !== 'string' || token === '') {
    throw new Error(`Admin login did not return a JWT (${login.response.status}, ${login.response.headers.get('content-type')}, ${String(login.payload?.error?.message ?? 'no API error')})`)
  }

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
  let ranFinalConsumer = false
  let previousRevision = Number(plan.revision || 0)
  let negativeStage = 'collision'
  let contractGuardStage = 'inject'
  let cleanupStage = 'pending'
  const observations = []
  for (let attempt = 0; attempt < 128; attempt++) {
    if (plan.status === 'completed') break
    if (plan.status === 'failed' && ((plan.current_step === 'preflight_migrations' && negativeStage !== 'finished')
      || (plan.current_step === 'contract_migrations' && (contractGuardStage === 'retry' || cleanupStage === 'retry')))) {
      // 清除失败原因后，由下一次 advance 显式重试。
    } else if (plan.status === 'failed' || plan.status === 'recovered') {
      throw new Error(`Upgrade entered unexpected terminal state: ${plan.status}`)
    }
    if (plan.current_step === 'preflight_migrations' && ['collision', 'unknown'].includes(negativeStage)) {
      await api(`/admin/operations/upgrades/${planId}/advance`, { token, method: 'POST', expected: [500] })
      plan = (await api(`/admin/operations/upgrades/${planId}`, { token })).data
      if (plan.status !== 'failed' || plan.current_step !== 'preflight_migrations') {
        throw new Error(`S11 ${negativeStage} did not fail the preflight plan step`)
      }
      const expectedComment = negativeStage === 'collision'
        ? 'e2e-custom-procedure' : 'LoveCards:20261112000001:legacy-seed-v1'
      const inspect = connectScript
        + '$comment=$pdo->query("SELECT ROUTINE_COMMENT FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME=\'lc_s11_assert_legacy_seed\'")->fetchColumn();'
        + `if($comment!==${JSON.stringify(expectedComment)}){fwrite(STDERR,"unexpected routine owner");exit(1);}`
        + '$status=$pdo->query("SELECT status FROM system_upgrade_steps WHERE migration_id=\'20261112000001\'")->fetchColumn();'
        + 'if($status!=="failed"){fwrite(STDERR,"S11 failure was not durable");exit(1);}'
        + 'foreach(["system","permissions","role_permissions","role_capabilities"] as $table){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);if((int)$q->fetchColumn()!==1)exit(1);}'
      await run(php, ['-r', inspect], dbEnv)
      if (negativeStage === 'collision') {
        await run(php, ['-r', connectScript + '$pdo->exec("DROP PROCEDURE lc_s11_assert_legacy_seed");'], dbEnv)
        negativeStage = 'unknown'
        console.log('[upgrade-e2e] custom procedure collision rejected without overwrite')
      } else {
        await run(php, ['-r', connectScript + '$pdo->exec("UPDATE `system` SET value=\'lovecards.cn\' WHERE id=1");'], dbEnv)
        negativeStage = 'done'
        console.log('[upgrade-e2e] unknown legacy row rejected and repaired for explicit retry')
      }
      previousRevision = Number(plan.revision)
      continue
    }
    if (plan.current_step === 'health_check' && !ranActivatedConsumer) {
      const consumerOutput = await run(php, [path.join('apps', 'backend', 'think'), 'lc:scheduler:run-once', '--max-jobs=1', '--max-ms=100'], dbEnv)
      if (!consumerOutput.includes('claimed=')) throw new Error('Activated Release did not complete a scheduled consumer run')
      ranActivatedConsumer = true
    }
    if (plan.current_step === 'final_health' && !ranFinalConsumer) {
      const consumerOutput = await run(php, [path.join('apps', 'backend', 'think'), 'lc:scheduler:run-once', '--max-jobs=1', '--max-ms=100'], dbEnv)
      if (!consumerOutput.includes('claimed=')) throw new Error('Final health did not observe a post-Contract consumer run')
      ranFinalConsumer = true
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
    if (plan.current_step === 'contract_migrations' && approved && contractGuardStage === 'inject') {
      await run(php, ['-r', connectScript + '$pdo->exec("UPDATE `system` SET value=\'changed-after-approval\' WHERE id=1");'], dbEnv)
      await api(`/admin/operations/upgrades/${planId}/advance`, { token, method: 'POST', expected: [500] })
      plan = (await api(`/admin/operations/upgrades/${planId}`, { token })).data
      if (plan.status !== 'failed' || plan.current_step !== 'contract_migrations') {
        throw new Error('S11 Contract did not reject legacy data changed after approval')
      }
      await run(php, ['-r', connectScript
        + '$status=$pdo->query("SELECT status FROM system_upgrade_steps WHERE migration_id=\'20261112000001\'")->fetchColumn();'
        + 'if($status!=="failed")exit(1);'
        + 'foreach(["system","permissions","role_permissions","role_capabilities"] as $table){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);if((int)$q->fetchColumn()!==1)exit(1);}'
        + '$pdo->exec("UPDATE `system` SET value=\'lovecards.cn\' WHERE id=1");'], dbEnv)
      contractGuardStage = 'retry'
      previousRevision = Number(plan.revision)
      console.log('[upgrade-e2e] post-approval data change rejected before DROP; repaired for explicit retry')
      continue
    }
    if (plan.current_step === 'contract_migrations' && approved && cleanupStage === 'pending') {
      const s11Complete = await run(php, ['-r', connectScript
        + '$state=$pdo->query("SELECT status FROM system_upgrade_steps WHERE migration_id=\'20261112000001\'")->fetchColumn();'
        + 'echo $state==="complete"?"yes":"no";'], dbEnv)
      if (s11Complete === 'yes') {
        // S11 已提交四表删除；用同名外部过程验证清理单元的所有权门禁。
        await run(php, ['-r', connectScript
          + '$pdo->exec("DROP PROCEDURE lc_s11_assert_legacy_seed");'
          + '$pdo->exec("CREATE PROCEDURE lc_s11_assert_legacy_seed() COMMENT \'e2e-custom-cleanup\' SELECT 1");'], dbEnv)
        await api(`/admin/operations/upgrades/${planId}/advance`, { token, method: 'POST', expected: [500] })
        plan = (await api(`/admin/operations/upgrades/${planId}`, { token })).data
        if (plan.status !== 'failed' || plan.current_step !== 'contract_migrations') {
          throw new Error('S11 cleanup did not reject a foreign procedure')
        }
        await run(php, ['-r', connectScript
          + '$state=$pdo->query("SELECT status FROM system_upgrade_steps WHERE migration_id=\'20261112000002\'")->fetchColumn();'
          + '$comment=$pdo->query("SELECT ROUTINE_COMMENT FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME=\'lc_s11_assert_legacy_seed\'")->fetchColumn();'
          + 'if($state!=="failed"||$comment!=="e2e-custom-cleanup")exit(1);'
          + '$pdo->exec("DROP PROCEDURE lc_s11_assert_legacy_seed");'], dbEnv)
        cleanupStage = 'retry'
        previousRevision = Number(plan.revision)
        console.log('[upgrade-e2e] cleanup rejected foreign procedure; absent procedure ready for explicit retry')
        continue
      }
    }

    const result = await api(`/admin/operations/upgrades/${planId}/advance`, {
      token, method: 'POST',
    })
    plan = result.data
    if (negativeStage === 'done') negativeStage = 'finished'
    if (contractGuardStage === 'retry') contractGuardStage = 'done'
    if (cleanupStage === 'retry') cleanupStage = 'done'
    const headerRelease = result.response.headers.get('x-lovecards-release-id')
      || result.response.headers.get('x-lovecards-release')
    if (headerRelease === releaseId) servedRelease = true
    if (Number(plan.revision) <= previousRevision) throw new Error('Upgrade revision did not advance monotonically')
    previousRevision = Number(plan.revision)
    observations.push(`${plan.current_step || 'done'}:${plan.status}`)
  }
  if (plan.status !== 'completed') throw new Error(`Upgrade did not complete after bounded advances: ${observations.slice(-10).join(', ')}`)
  if (!ranActivatedConsumer || !ranFinalConsumer) throw new Error('Upgrade completed without both activated and final consumer runs')

  const detail = await api(`/admin/operations/upgrades/${planId}`, { token })
  if (detail.data?.status !== 'completed' || detail.data?.release_id !== releaseId) throw new Error('Persisted upgrade detail is inconsistent')
  const activation = detail.data.steps?.find((step) => step.name === 'activate_release')
  const health = detail.data.steps?.find((step) => step.name === 'health_check')
  const contract = detail.data.steps?.find((step) => step.name === 'contract_migrations')
  const finalHealth = detail.data.steps?.find((step) => step.name === 'final_health')
  if (activation?.state !== 'succeeded' || health?.state !== 'succeeded' || finalHealth?.state !== 'succeeded') throw new Error('Activation and health steps were not durably completed')
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

  const s11Script = [
    '$dsn="mysql:host=".getenv("DATABASE_HOSTNAME").";port=".getenv("DATABASE_HOSTPORT").";dbname=".getenv("DATABASE_DATABASE").";charset=utf8mb4";',
    '$pdo=new PDO($dsn,(string)getenv("DATABASE_USERNAME"),(string)getenv("DATABASE_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);',
    'foreach(["system","permissions","role_permissions","role_capabilities"] as $table){',
    '$statement=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");',
    '$statement->execute([$table]);if((int)$statement->fetchColumn()!==0){fwrite(STDERR,"S11 old table remains: ".$table);exit(1);}}',
    '$state=$pdo->query("SELECT phase,status FROM system_upgrade_steps WHERE migration_id=\'20261112000001\'")->fetch(PDO::FETCH_ASSOC);',
    'if(!is_array($state)||$state["status"]!=="complete"){fwrite(STDERR,"S11 ledger not complete");exit(1);}',
    '$state=$pdo->query("SELECT phase,status FROM system_upgrade_steps WHERE migration_id=\'20261112000002\'")->fetch(PDO::FETCH_ASSOC);',
    'if(!is_array($state)||$state["status"]!=="complete"){fwrite(STDERR,"S11 cleanup ledger not complete");exit(1);}',
    '$routine=$pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME=\'lc_s11_assert_legacy_seed\'")->fetchColumn();',
    'if((int)$routine!==0){fwrite(STDERR,"S11 procedure remains");exit(1);}',
    'echo "S11 contracts complete";',
  ].join('')
  console.log(`[upgrade-e2e] ${await run(php, ['-r', s11Script], dbEnv)}`)

  // R2 is a separate signed Release. Its source range starts at the reviewed
  // compatibility migration; it must never be advertised as a direct upgrade
  // from the pre-S11 fixture.
  await prepareReleaseSource(currentReleaseSource)
  await addContractProbe(currentReleaseSource)
  const currentBuildOutput = await run(php, [
    path.join('apps', 'backend', 'think'), 'lc:release:build', currentReleaseZip,
    '--source', currentReleaseSource,
    '--release-id', currentReleaseId,
    '--release-version', '99.0.1-e2e',
    '--build', currentReleaseId,
    '--sequence', '2',
    '--source-schema-min', '20261112000003',
    '--private-key', privateKey,
    '--key-id', 'upgrade-e2e-key',
    '--optional-facilities', 'redis,worker',
  ], { ...dbEnv, LC_RELEASE_TRUSTED_KEYS_JSON: trustedKeys, PHP_LC_RELEASE_TRUSTED_KEYS_JSON: trustedKeys })
  if (!currentBuildOutput.includes(currentReleaseId) || (await stat(currentReleaseZip)).size < 1) {
    throw new Error('Current signed Release builder returned an invalid artifact')
  }
  console.log(`[upgrade-e2e] signed current Release built: ${currentReleaseId}`)

  await stop(backend)
  dbEnv.PHP_LC_RUNTIME_DRAIN_COMMAND_JSON = JSON.stringify([php, path.join(root, 'scripts/assert-e2e-consumers-stopped.php'), '{phase}'])
  backend = start(process.execPath, ['scripts/dev-backend.mjs'], {
    ...dbEnv,
    LC_RELEASE_TRUSTED_KEYS_JSON: trustedKeys,
    PHP_LC_RELEASE_TRUSTED_KEYS_JSON: trustedKeys,
    LOVECARDS_DEV_BACKEND_PORT: String(backendPort),
    LOVECARDS_DEV_PHP_POST_MAX_SIZE: process.env.LOVECARDS_UPGRADE_E2E_PHP_POST_MAX_SIZE || '768M',
    LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE: process.env.LOVECARDS_UPGRADE_E2E_PHP_UPLOAD_MAX_FILESIZE || '512M',
    LOVECARDS_DEV_PHP_INI: process.env.LOVECARDS_UPGRADE_E2E_PHP_INI || '{"display_errors":"0"}',
    LOVECARDS_E2E_ISOLATED: '1',
    LOVECARDS_ALLOW_UNSUPPORTED_PHP: process.env.LOVECARDS_ALLOW_UNSUPPORTED_PHP || '0',
  }, 'inherit', true)
  await waitFor(`http://127.0.0.1:${backendPort}/api/cards?list_rows=1`)
  const currentLogin = await api('/session/login', { method: 'POST', json: { account: adminAccount, password: adminPassword } })
  const currentToken = currentLogin.data?.token
  if (typeof currentToken !== 'string' || currentToken === '') throw new Error('Current Release admin login failed')

  const currentForm = new FormData()
  currentForm.set('file', await openAsBlob(currentReleaseZip, { type: 'application/zip' }), 'release.zip')
  let currentPlan = (await api('/admin/operations/upgrades', {
    token: currentToken, method: 'POST', body: currentForm, expected: [201],
  })).data
  if (currentPlan?.release_id !== currentReleaseId || typeof currentPlan?.plan_id !== 'string') {
    throw new Error('Current Release plan creation failed')
  }
  const currentPlanId = currentPlan.plan_id
  let currentApproved = false
  const negativeContracts = [
    {
      id: '20261113000008', label: 'nonempty images',
      inject: '$pdo->exec("INSERT INTO images (aid,pid,user_id,url,created_at) VALUES (0,0,0,\'e2e-legacy-image\',UTC_TIMESTAMP())");',
      repair: '$pdo->exec("DELETE FROM images WHERE url=\'e2e-legacy-image\'");',
    },
    {
      id: '20261113000010', label: 'nonzero tags_map.status',
      inject: '$pdo->exec("INSERT INTO tags_map (aid,pid,tag_id,status) VALUES (0,-999999,0,1)");',
      repair: '$pdo->exec("DELETE FROM tags_map WHERE pid=-999999 AND status=1");',
    },
    {
      id: '20261113000011', label: 'custom recipient_rule',
      inject: '$pdo->exec("UPDATE notification_policies SET recipient_rule=\'e2e-custom\' ORDER BY id LIMIT 1");',
      repair: '$pdo->exec("UPDATE notification_policies SET recipient_rule=\'handler\' WHERE recipient_rule=\'e2e-custom\'");',
    },
  ]
  let negativeIndex = 0
  for (let attempt = 0; attempt < 128 && currentPlan.status !== 'completed'; attempt++) {
    if (currentPlan.status === 'failed' || currentPlan.status === 'recovered') {
      throw new Error(`Current Release entered terminal state: ${currentPlan.status}`)
    }
    // The coordinator advances one migration at a time. Read the durable
    // ledger to inject only when the target migration is next in verify.
    if (currentPlan.current_step === 'verify_migrations' && negativeIndex < negativeContracts.length) {
      const scenario = negativeContracts[negativeIndex]
      const nextId = await run(php, ['-r', connectScript
        + 'echo $pdo->query("SELECT migration_id FROM system_upgrade_steps WHERE phase=\'verify\' AND status<>\'complete\' ORDER BY migration_id LIMIT 1")->fetchColumn() ?: "";'], dbEnv)
      if (nextId !== '' && nextId > scenario.id) throw new Error(`Missed negative verify fixture for ${scenario.id}`)
      if (nextId === scenario.id) {
        await run(php, ['-r', connectScript + scenario.inject], dbEnv)
        await api(`/admin/operations/upgrades/${currentPlanId}/advance`, {
          token: currentToken, method: 'POST', expected: [500],
        })
        currentPlan = (await api(`/admin/operations/upgrades/${currentPlanId}`, { token: currentToken })).data
        if (currentPlan.status !== 'failed' || currentPlan.current_step !== 'verify_migrations') {
          throw new Error(`${scenario.label} did not block current Release verify`)
        }
        const inspect = connectScript
          + `$state=$pdo->query("SELECT status FROM system_upgrade_steps WHERE migration_id='${scenario.id}'")->fetchColumn();`
          + 'if($state!=="failed")exit(1);'
        await run(php, ['-r', inspect + scenario.repair], dbEnv)
        currentPlan = (await api(`/admin/operations/upgrades/${currentPlanId}/advance`, {
          token: currentToken, method: 'POST',
        })).data
        if (currentPlan.status === 'failed' || currentPlan.current_step !== 'verify_migrations') {
          throw new Error(`${scenario.label} failed to resume after explicit retry`)
        }
        console.log(`[upgrade-e2e] ${scenario.label} rejected, repaired and explicitly retried`)
        negativeIndex++
        continue
      }
    }
    if (currentPlan.status === 'waiting') {
      if (currentPlan.current_step !== 'contract_migrations' || currentApproved) throw new Error('Unexpected current Release wait')
      currentPlan = (await api(`/admin/operations/upgrades/${currentPlanId}/approve-contract`, {
        token: currentToken, method: 'POST',
      })).data
      currentApproved = true
      continue
    }
    if (currentPlan.current_step === 'health_check' || currentPlan.current_step === 'final_health') {
      const consumerOutput = await run(php, [path.join('apps', 'backend', 'think'), 'lc:scheduler:run-once', '--max-jobs=1', '--max-ms=100'], dbEnv)
      if (!consumerOutput.includes('claimed=')) throw new Error('Current Release consumer check failed')
    }
    currentPlan = (await api(`/admin/operations/upgrades/${currentPlanId}/advance`, {
      token: currentToken, method: 'POST',
    })).data
  }
  if (currentPlan.status !== 'completed' || !currentApproved || negativeIndex !== negativeContracts.length) {
    throw new Error('Current Release upgrade did not complete all guarded scenarios')
  }
  const currentDetail = (await api(`/admin/operations/upgrades/${currentPlanId}`, { token: currentToken })).data
  if (currentDetail?.status !== 'completed' || currentDetail?.release_id !== currentReleaseId
    || currentDetail?.steps?.find((step) => step.name === 'contract_migrations')?.state !== 'succeeded') {
    throw new Error('Current Release durable plan is inconsistent')
  }
  const currentPointer = (await readFile(path.join(instanceRoot, 'current'), 'utf8')).trim()
  if (!currentPointer.includes(currentReleaseId)) throw new Error('Current Release pointer was not activated')
  const probeScript = [
    '$dsn="mysql:host=".getenv("DATABASE_HOSTNAME").";port=".getenv("DATABASE_HOSTPORT").";dbname=".getenv("DATABASE_DATABASE").";charset=utf8mb4";',
    '$pdo=new PDO($dsn,(string)getenv("DATABASE_USERNAME"),(string)getenv("DATABASE_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);',
    `$value=$pdo->query("SELECT contract_marker FROM ${contractProbeTable} WHERE id=1")->fetchColumn();`,
    'if($value!=="contract-complete"){fwrite(STDERR,"contract probe mismatch\\n");exit(1);}',
    'foreach(["images"] as $table){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);if((int)$q->fetchColumn()!==0)exit(1);}',
    'foreach([["tags_map","status"],["notification_policies","recipient_rule"],["likes","aid"],["likes","pid"]] as [$table,$column]){$q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");$q->execute([$table,$column]);if((int)$q->fetchColumn()!==0)exit(1);}',
    'foreach(["20261113000001","20261113000002","20261113000003","20261113000004","20261113000005","20261113000008","20261113000009","20261113000010","20261113000011"] as $id){$q=$pdo->prepare("SELECT status FROM system_upgrade_steps WHERE migration_id=?");$q->execute([$id]);if($q->fetchColumn()!=="complete")exit(1);}',
    `$pdo->exec("DROP TABLE ${contractProbeTable}");echo "current contracts complete";`,
  ].join('')
  console.log(`[upgrade-e2e] ${await run(php, ['-r', probeScript], dbEnv)}`)

  await run(php, ['scripts/bootstrap-e2e.php', 'create', cleanDatabase], {
    ...dbEnv,
    LC_INSTANCE_ROOT: path.join(temporaryRoot, 'clean-instance'),
  })
  cleanDatabaseCreated = true
  const dsn = (name) => `mysql:host=${databaseHost};port=${databasePort};dbname=${name};charset=utf8mb4`
  console.log(await run(php, ['scripts/verify-schema-consistency.php', dsn(cleanDatabase), dsn(database)], {
    ...dbEnv,
    LC_SCHEMA_COMPARE_USER: databaseUser,
    LC_SCHEMA_COMPARE_PASSWORD: databasePassword,
  }))

  console.log(`[upgrade-e2e] PASS compatibility and current signed Releases, guarded retries, approval, schema consistency, activation and health (${observations.length} R1 advances)`)
  exitCode = 0
} finally {
  await stop(backend)
  if (cleanDatabaseCreated) {
    try {
      await run(php, ['scripts/bootstrap-e2e.php', 'drop', cleanDatabase], dbEnv)
    } catch (error) {
      console.error(`[upgrade-e2e] clean database cleanup failed: ${error.message}`)
      exitCode = 1
    }
  }
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
