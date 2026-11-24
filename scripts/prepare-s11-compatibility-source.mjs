import { execFile } from 'node:child_process'
import { copyFile, cp, mkdir, mkdtemp, readFile, readdir, rm } from 'node:fs/promises'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { promisify } from 'node:util'
import { resolveSupportedPhp } from './lib/php-runtime.mjs'

const root = fileURLToPath(new URL('..', import.meta.url))
const backendRoot = path.join(root, 'apps', 'backend')
const compatibilityRef = 'review/r1-compat-source-20261115'
const compatibilityCommit = '9632a4946808244bbae67149fae8781bfa6b015d'
const compatibilityFiles = [
  'app/modules/Operations/SystemLifecycle/Infrastructure/S11RoutineGuard.php',
  'app/modules/Operations/SystemLifecycle/Infrastructure/SqlFileMigration.php',
  'app/shared/Infrastructure/Persistence/SqlStatementExecutor.php',
]
const run = promisify(execFile)

export async function prepareS11CompatibilitySource(destination, php) {
  const { stdout } = await run('git', ['rev-parse', `refs/tags/${compatibilityRef}^{commit}`], { cwd: root })
  if (stdout.trim() !== compatibilityCommit) throw new Error('Reviewed R1 compatibility source tag differs from expected commit')

  await mkdir(destination)
  const temporary = await mkdtemp(path.join(os.tmpdir(), 'lovecards-r1-source-'))
  try {
    const archive = path.join(temporary, 'backend.zip')
    await run('git', ['archive', '--format=zip', `--output=${archive}`, compatibilityCommit, 'apps/backend'], { cwd: root })
    const extract = '$zip=new ZipArchive();if($zip->open($argv[1])!==true||!$zip->extractTo($argv[2]))exit(1);$zip->close();'
    await run(php, ['-r', extract, archive, temporary], { cwd: root })
    const baseline = path.join(temporary, 'apps', 'backend')
    const migrations = path.join('database', 'migrations')
    for (const name of await readdir(path.join(baseline, migrations))) {
      if (!name.endsWith('.sql')) continue
      const published = await readFile(path.join(baseline, migrations, name))
      const current = await readFile(path.join(backendRoot, migrations, name))
      if (!published.equals(current)) throw new Error(`R1 migration checksum differs from current source: ${name}`)
    }
    await cp(baseline, destination, { recursive: true })
    for (const file of compatibilityFiles) {
      await copyFile(path.join(backendRoot, file), path.join(destination, file))
    }
  } finally {
    await rm(temporary, { recursive: true, force: true })
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  if (process.argv.length !== 3 || !path.isAbsolute(process.argv[2])) {
    throw new Error('Usage: node scripts/prepare-s11-compatibility-source.mjs <new-absolute-backend-directory>')
  }
  await prepareS11CompatibilitySource(process.argv[2], resolveSupportedPhp().binary)
}
