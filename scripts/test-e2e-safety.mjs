import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { mkdtemp, readFile, rm } from 'node:fs/promises'
import os from 'node:os'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'
import { resolveSupportedPhp } from './lib/php-runtime.mjs'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const runNode = (script, env = {}) => spawnSync(process.execPath, [script], {
  cwd: root,
  env: { ...process.env, ...env },
  encoding: 'utf8',
  windowsHide: true,
})

const adminWithoutFence = runNode('scripts/run-admin-e2e.mjs', {
  LOVECARDS_E2E_ISOLATED: '',
})
assert.notEqual(adminWithoutFence.status, 0)
assert.match(adminWithoutFence.stderr, /LOVECARDS_E2E_ISOLATED=1/)

const adminRemoteDatabase = runNode('scripts/run-admin-e2e.mjs', {
  LOVECARDS_E2E_ISOLATED: '1',
  LOVECARDS_E2E_DB_NAME: 'lovecards_e2e_safety',
  LOVECARDS_E2E_DB_HOST: 'database.example.com',
  LOVECARDS_E2E_ADMIN_ACCOUNT: 'guard@example.com',
  LOVECARDS_E2E_ADMIN_PASSWORD: 'guard-only',
})
assert.notEqual(adminRemoteDatabase.status, 0)
assert.match(adminRemoteDatabase.stderr, /must be local/)

const themeRemoteOrigin = runNode('scripts/run-theme-e2e.mjs', {
  LOVECARDS_THEME_ORIGIN: 'https://127.0.0.1.example.com',
})
assert.notEqual(themeRemoteOrigin.status, 0)
assert.match(themeRemoteOrigin.stderr, /must use localhost or 127\.0\.0\.1/)

const themeWithoutFence = runNode('scripts/run-theme-e2e.mjs', {
  LOVECARDS_THEME_ORIGIN: 'http://127.0.0.1:65534',
  LOVECARDS_THEME_TEST_ACCOUNT: 'guard@example.com',
  LOVECARDS_THEME_TEST_PASSWORD: 'guard-only',
  LOVECARDS_THEME_TEST_ISOLATED: '',
})
assert.notEqual(themeWithoutFence.status, 0)
assert.match(themeWithoutFence.stderr, /LOVECARDS_THEME_TEST_ISOLATED=1/)

const upgradeWithoutFence = runNode('scripts/run-upgrade-e2e.mjs', {
  LOVECARDS_UPGRADE_E2E_ISOLATED: '',
})
assert.notEqual(upgradeWithoutFence.status, 0)
assert.match(upgradeWithoutFence.stderr, /LOVECARDS_UPGRADE_E2E_ISOLATED=1/)

const upgradeRemoteDatabase = runNode('scripts/run-upgrade-e2e.mjs', {
  LOVECARDS_UPGRADE_E2E_ISOLATED: '1',
  LOVECARDS_UPGRADE_E2E_DB_NAME: 'lovecards_e2e_upgrade_safety',
  LOVECARDS_E2E_DB_HOST: 'database.example.com',
  LOVECARDS_E2E_ADMIN_ACCOUNT: 'guard@example.com',
  LOVECARDS_E2E_ADMIN_PASSWORD: 'guard-only',
})
assert.notEqual(upgradeRemoteDatabase.status, 0)
assert.match(upgradeRemoteDatabase.stderr, /database host must be local/)

const schemaWithoutFence = runNode('scripts/run-schema-consistency-e2e.mjs', {
  LOVECARDS_SCHEMA_E2E_ISOLATED: '',
})
assert.notEqual(schemaWithoutFence.status, 0)
assert.match(schemaWithoutFence.stderr, /LOVECARDS_SCHEMA_E2E_ISOLATED=1/)

const schemaRemoteDatabase = runNode('scripts/run-schema-consistency-e2e.mjs', {
  LOVECARDS_SCHEMA_E2E_ISOLATED: '1',
  LOVECARDS_E2E_DB_HOST: 'database.example.com',
})
assert.notEqual(schemaRemoteDatabase.status, 0)
assert.match(schemaRemoteDatabase.stderr, /localhost database address/)

const bootstrapSource = await readFile(path.join(root, 'scripts/bootstrap-e2e.php'), 'utf8')
assert.match(bootstrapSource, /getenv\('LOVECARDS_E2E_ISOLATED'\)\s*!==\s*'1'/)
assert.match(bootstrapSource, /\^lovecards_e2e_\[a-z0-9_\]\+\$/)
assert.match(bootstrapSource, /127\.0\.0\.1.*localhost.*::1/s)
assert.match(bootstrapSource, /Refusing to reuse an existing E2E database/)
assert.match(bootstrapSource, /if \(\$databaseCreated && \$pdo instanceof PDO\)/)
assert.match(bootstrapSource, /lc:system:migrate --contract/)

const adminRunnerSource = await readFile(path.join(root, 'scripts/run-admin-e2e.mjs'), 'utf8')
assert.match(adminRunnerSource, /let databaseCreated = false/)
assert.match(adminRunnerSource, /if \(databaseCreated\) \{\s*const cleanup/s)
assert.match(adminRunnerSource, /function waitForExit\(child\)/)
assert.match(adminRunnerSource, /admin = start\(process\.execPath, \[nuxtCli/)
assert.match(adminRunnerSource, /existsSync\(bundledOpenSsl\)/)
assert.match(adminRunnerSource, /signal\('SIGKILL'\)/)

const upgradeRunnerSource = await readFile(path.join(root, 'scripts/run-upgrade-e2e.mjs'), 'utf8')
assert.match(upgradeRunnerSource, /existsSync\(bundledOpenSsl\)/)
assert.match(upgradeRunnerSource, /signal\('SIGKILL'\)/)
assert.match(upgradeRunnerSource, /release-source/)
assert.match(upgradeRunnerSource, /e2e_upgrade_contract_probe/)
assert.match(upgradeRunnerSource, /Contract migration did not pass through explicit waiting and approval/)

if (process.env.LOVECARDS_E2E_DB_SAFETY_LIVE === '1') {
  const host = process.env.LOVECARDS_E2E_DB_HOST || '127.0.0.1'
  const port = process.env.LOVECARDS_E2E_DB_PORT || '3306'
  const user = process.env.LOVECARDS_E2E_DB_USER || 'root'
  const password = process.env.LOVECARDS_E2E_DB_PASSWORD || ''
  const database = process.env.LOVECARDS_E2E_DB_SAFETY_NAME
    || `lovecards_e2e_collision_${Date.now()}_${process.pid}`
  const marker = `sentinel-${Date.now()}-${process.pid}`
  assert.match(database, /^lovecards_e2e_[a-z0-9_]+$/)
  assert(['127.0.0.1', 'localhost', '::1'].includes(host), 'live E2E database safety test requires a local database')

  const { binary: php } = resolveSupportedPhp()
  const temporaryRoot = await mkdtemp(path.join(os.tmpdir(), 'lovecards-e2e-collision-'))
  const connectionArgs = [host, port, user, password, database]
  const phpEnv = {
    ...process.env,
    LOVECARDS_E2E_ISOLATED: '1',
    LOVECARDS_E2E_DB_HOST: host,
    LOVECARDS_E2E_DB_PORT: port,
    LOVECARDS_E2E_DB_USER: user,
    LOVECARDS_E2E_DB_PASSWORD: password,
    LC_INSTANCE_ROOT: path.join(temporaryRoot, 'instance'),
  }
  const runPhp = (args, env = process.env) => spawnSync(php, args, {
    cwd: root,
    env,
    encoding: 'utf8',
    windowsHide: true,
  })
  const serverPdo = '$dsn="mysql:host={$argv[1]};port={$argv[2]};charset=utf8mb4";$pdo=new PDO($dsn,$argv[3],$argv[4],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db=$argv[5];$quoted="`".str_replace("`","``",$db)."`";'
  let created = false
  try {
    const create = runPhp(['-r', `${serverPdo}$exists=$pdo->prepare("SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=? LIMIT 1");$exists->execute([$db]);if($exists->fetchColumn()!==false){fwrite(STDERR,"fixture already exists\\n");exit(2);}$pdo->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$fixture=new PDO($dsn.";dbname=".$db,$argv[3],$argv[4],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$fixture->exec("CREATE TABLE collision_sentinel (marker VARCHAR(191) NOT NULL)");$insert=$fixture->prepare("INSERT INTO collision_sentinel (marker) VALUES (?)");$insert->execute([$argv[6]]);`, ...connectionArgs, marker])
    assert.equal(create.status, 0, create.stderr || create.stdout)
    created = true

    const collision = runNode('scripts/run-admin-e2e.mjs', {
      ...phpEnv,
      PHP_BINARY: php,
      LOVECARDS_E2E_DB_NAME: database,
      LOVECARDS_E2E_ADMIN_ACCOUNT: 'collision-guard@example.test',
      LOVECARDS_E2E_ADMIN_PASSWORD: 'collision-guard-only',
      LOVECARDS_E2E_BACKEND_PORT: '65532',
      LOVECARDS_E2E_ADMIN_PORT: '65533',
    })
    assert.notEqual(collision.status, 0, 'bootstrap unexpectedly reused an existing E2E database')
    assert.match(`${collision.stderr}\n${collision.stdout}`, /Refusing to reuse an existing E2E database/)

    const probe = runPhp(['-r', `${serverPdo}$fixture=new PDO($dsn.";dbname=".$db,$argv[3],$argv[4],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);echo $fixture->query("SELECT marker FROM collision_sentinel LIMIT 1")->fetchColumn();`, ...connectionArgs])
    assert.equal(probe.status, 0, probe.stderr || probe.stdout)
    assert.equal(probe.stdout.trim(), marker, 'existing E2E database was modified or deleted after a rejected bootstrap')
    console.log('Live E2E database collision guard passed.')
  } finally {
    if (created) {
      const cleanup = runPhp(['-r', `${serverPdo}$pdo->exec("DROP DATABASE {$quoted}");`, ...connectionArgs])
      assert.equal(cleanup.status, 0, cleanup.stderr || cleanup.stdout)
    }
    await rm(temporaryRoot, { recursive: true, force: true })
  }
}

console.log('Admin, theme, upgrade, schema and database E2E safety guards passed.')
