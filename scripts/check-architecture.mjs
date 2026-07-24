import { readFile, readdir } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const root = fileURLToPath(new URL('..', import.meta.url))
const failures = []

const relative = (file) => path.relative(root, file).split(path.sep).join('/')
const fail = (message) => failures.push(message)

async function readJson(file) {
  return JSON.parse(await readFile(file, 'utf8'))
}

async function exists(file) {
  try {
    await readdir(file)
    return true
  } catch {
    return false
  }
}

async function walk(directory, predicate, result = []) {
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    if (entry.name === '.git' || entry.name === 'node_modules' || entry.name === 'vendor') continue
    const file = path.join(directory, entry.name)
    if (entry.isDirectory()) await walk(file, predicate, result)
    else if (predicate(file)) result.push(file)
  }
  return result
}

for (const directory of ['apps/backend', 'apps/admin', 'packages/sdk']) {
  if (!(await exists(path.join(root, directory)))) fail(`Missing workspace directory: ${directory}`)
}

const rootPackage = await readJson(path.join(root, 'package.json'))
const adminPackage = await readJson(path.join(root, 'apps/admin/package.json'))
const sdkPackage = await readJson(path.join(root, 'packages/sdk/package.json'))

const expectedWorkspaces = ['apps/admin', 'packages/sdk']
if (JSON.stringify(rootPackage.workspaces) !== JSON.stringify(expectedWorkspaces)) {
  fail(`Root workspaces must be exactly: ${expectedWorkspaces.join(', ')}`)
}
if (adminPackage.name !== '@lovecards/admin') fail('Admin package name must be @lovecards/admin')
if (sdkPackage.name !== '@lovecards/sdk') fail('SDK package name must be @lovecards/sdk')
if (adminPackage.dependencies?.['@lovecards/sdk'] !== sdkPackage.version) {
  fail('Admin must depend on the local SDK version, not a filesystem path')
}
if (sdkPackage.scripts?.postbuild) fail('SDK postbuild must not write into an application')

const packageFiles = await walk(root, (file) => path.basename(file) === 'package.json')
for (const file of packageFiles) {
  const manifest = await readJson(file)
  for (const group of ['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies']) {
    for (const [name, version] of Object.entries(manifest[group] ?? {})) {
      if (String(version).startsWith('file:')) {
        fail(`${relative(file)}: ${group}.${name} uses a file: dependency`)
      }
    }
  }
}

for (const lock of ['apps/admin/package-lock.json', 'packages/sdk/package-lock.json']) {
  try {
    await readFile(path.join(root, lock))
    fail(`${lock} must be replaced by the root package-lock.json`)
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
  }
}

for (const directory of ['apps/backend/.git', 'apps/admin/.git', 'packages/sdk/.git']) {
  if (await exists(path.join(root, directory))) fail(`Nested Git repository is forbidden: ${directory}`)
}

async function checkPhpBoundary(directory, forbidden, allowlist = new Set()) {
  const base = path.join(root, directory)
  const files = await walk(base, (file) => file.endsWith('.php'))
  for (const file of files) {
    const source = await readFile(file, 'utf8')
    for (const match of source.matchAll(/^\s*use\s+(app\\[^;]+);/gmi)) {
      const dependency = match[1].replace(/\s+as\s+.+$/i, '')
      const key = `${relative(file)}:${dependency}`
      if (forbidden.test(dependency) && !allowlist.has(key)) fail(`Forbidden dependency: ${key}`)
    }
  }
}

await checkPhpBoundary(
  'apps/backend/app/common',
  /^app\\(api|frontend|system)\\/i,
  new Set(['apps/backend/app/common/support/OwnershipGuard.php:app\\api\\ApiException']),
)
await checkPhpBoundary('apps/backend/app/api/service', /^app\\api\\controller\\/i)
await checkPhpBoundary('apps/backend/app/api/controller', /^app\\api\\model\\/i)
await checkPhpBoundary('apps/backend/app/api', /^app\\common\\infra\\Jwt$/i)
await checkPhpBoundary(
  'apps/backend/app/api/application',
  /^app\\api\\(controller|middleware|model|infrastructure)\\/i,
)

const backendPhpFiles = await walk(path.join(root, 'apps/backend/app'), (file) => file.endsWith('.php'))
const legacyAuthField = /(?:request\(\)|\$request)->(?:uid|user|rolesId|caps|newToken)\b/
for (const file of backendPhpFiles) {
  const source = await readFile(file, 'utf8')
  if (legacyAuthField.test(source)) {
    fail(`${relative(file)} uses a legacy Request auth field; use request()->auth`)
  }
}

const authApplicationFiles = await walk(
  path.join(root, 'apps/backend/app/api/application/Auth'),
  (file) => file.endsWith('.php'),
)
for (const file of authApplicationFiles) {
  const source = await readFile(file, 'utf8')
  if (/\brequest\s*\(/i.test(source) || /think\\facade\\Request/i.test(source)) {
    fail(`${relative(file)} reads HTTP Request state inside the Auth application layer`)
  }
}

const sessionService = await readFile(
  path.join(root, 'apps/backend/app/api/service/User/Session.php'),
  'utf8',
)
if (/public\s+function\s+(?:login|register)\s*\(/i.test(sessionService)) {
  fail('Auth login/register entry points must remain Application use cases, not Session service methods')
}

if (failures.length > 0) {
  console.error('Architecture checks failed:')
  failures.forEach((message) => console.error(`- ${message}`))
  process.exit(1)
}

console.log('Architecture checks passed.')
