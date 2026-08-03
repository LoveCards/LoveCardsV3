import { readFile, readdir, stat } from 'node:fs/promises'
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
    if (entry.name === '.git' || entry.name === 'node_modules' || entry.name === 'vendor' || entry.name === '.nuxt' || entry.name === '.output' || entry.name === '.nitro' || entry.name === '.data' || entry.name === 'dist' || entry.name === 'coverage') continue
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
if (adminPackage.dependencies?.axios) fail('Admin must not depend on Axios directly; use @lovecards/sdk')

const adminSourceFiles = await walk(path.join(root, 'apps/admin'), (file) => /\.(?:ts|vue|js)$/.test(file))
for (const file of adminSourceFiles) {
  const source = await readFile(file, 'utf8')
  const rel = relative(file)
  if (/from\s+['"](?:axios|ofetch)['"]/.test(source)) {
    fail(`Admin source must not import a transport client: ${rel}`)
  }
  if (/(?:\$fetch|useFetch|\bfetch)\s*\(/.test(source)) {
    fail(`Admin source must call APIs through @lovecards/sdk: ${rel}`)
  }
  if (/['"](?:~|@)\/?api\/(?:app|axios|interceptors)(?:\/|['"])/.test(source)) {
    fail(`Admin source references the removed API layer: ${rel}`)
  }
}

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

if (await exists(path.join(root, 'apps/backend/app/api/service'))) {
  fail('Legacy API service directory must not exist; use app/modules')
}

async function fileExists(file) {
  try {
    const info = await stat(file)
    return info.isFile()
  } catch {
    return false
  }
}
if (await exists(path.join(root, 'apps/backend/app/api/infrastructure'))) {
  fail('Legacy API infrastructure directory must not exist; use app/modules')
}
if (await exists(path.join(root, 'apps/backend/app/api/model'))) {
  fail('Legacy API model directory must not exist; use module infrastructure persistence')
}
if (await exists(path.join(root, 'apps/backend/app/api/validate'))) {
  fail('Legacy API validate directory must not exist; use module Transport')
}
if (await exists(path.join(root, 'apps/backend/app/api/resource'))) {
  fail('Legacy API resource directory must not exist; use module Transport or shared HTTP')
}
if (await exists(path.join(root, 'apps/backend/app/system/utils'))) {
  fail('Legacy system utility directory must not exist; use modules/Operations/Installer')
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

if (await exists(path.join(root, 'apps/backend/app/common'))) {
  fail('Legacy common directory must not exist; use shared or an owning module')
}
if (!(await fileExists(path.join(root, 'contracts/openapi.json')))) {
  fail('Generated API contract is missing: run npm run export:api')
}
if (!(await fileExists(path.join(root, 'packages/sdk/src/generated/api-contract.ts')))) {
  fail('Generated SDK contract is missing: run npm run generate:sdk')
}
const sdkResourceDirectory = path.join(root, 'packages/sdk/src/resources')
for (const file of await readdir(sdkResourceDirectory)) {
  if (file.endsWith('.ts') && file !== 'base.ts') {
    fail(`Handwritten SDK resource is forbidden; use generated/resources/${file}`)
  }
}
await checkPhpBoundary('apps/backend/app/shared', /^app\\common\\/i)
await checkPhpBoundary('apps/backend/app/modules', /^app\\api\\(controller|middleware|route)\\/i)
await checkPhpBoundary('apps/backend/app/modules', /^app\\frontend\\/i)
await checkPhpBoundary('apps/backend/app/modules', /^app\\system\\/i)

const moduleRoot = path.join(root, 'apps/backend/app/modules')
const moduleFiles = await walk(moduleRoot, (file) => file.endsWith('.php'))
for (const file of moduleFiles) {
  const source = await readFile(file, 'utf8')
  const rel = relative(file)
  const layer = rel.split('/').find((part) => ['Application', 'Domain', 'Contract', 'Infrastructure'].includes(part))
  for (const match of source.matchAll(/^\s*use\s+(app\\[^;]+|think\\[^;]+);/gmi)) {
    const dependency = match[1].replace(/\s+as\s+.+$/i, '')
    const key = `${rel}:${dependency}`
    if (layer === 'Application' && /^app\\modules\\.+\\Infrastructure\\/i.test(dependency)) {
      fail(`Application must not depend on module Infrastructure: ${key}`)
    }
    if (layer === 'Application' && /^think\\facade\\Db$/i.test(dependency)) {
      fail(`Application must not depend on Db facade directly: ${key}`)
    }
    if (layer === 'Domain' && /^(app\\(?:api|frontend|system)|app\\modules\\.+\\Infrastructure|think\\)/i.test(dependency)) {
      fail(`Domain must remain framework and infrastructure free: ${key}`)
    }
    if (layer === 'Contract' && /^(app\\(?:api|frontend|system)|app\\modules\\.+\\Infrastructure|think\\)/i.test(dependency)) {
      fail(`Contract must not depend on transport, ORM model, or infrastructure: ${key}`)
    }
  }
}

const backendPhpFiles = await walk(path.join(root, 'apps/backend/app'), (file) => file.endsWith('.php'))
const legacyAuthField = /(?:request\(\)|\$request)->(?:uid|user|rolesId|caps|newToken)\b/
for (const file of backendPhpFiles) {
  const source = await readFile(file, 'utf8')
  if (legacyAuthField.test(source)) {
    fail(`${relative(file)} uses a legacy Request auth field; use request()->auth`)
  }
  if (/\b(?:StorageManager|DirectUploadManager)\b/.test(source)) {
    fail(`${relative(file)} references a removed legacy Files/Storage manager`)
  }
}

const applicationFiles = await walk(moduleRoot, (file) => file.endsWith('.php'))
for (const file of applicationFiles) {
  const rel = relative(file)
  if (!rel.split('/').includes('Application')) continue
  const isStrictApplication = /apps\/backend\/app\/modules\/Platform\/(Auth|Files)\/Application\//i.test(rel)
  if (!isStrictApplication) continue
  const source = await readFile(file, 'utf8')
  if (/\brequest\s*\(/i.test(source)) {
    fail(`${rel} reads HTTP Request state inside Application`)
  }
}

for (const file of applicationFiles) {
  const rel = relative(file)
  if (!rel.split('/').includes('Application')) continue
  const isStrictApplication = /apps\/backend\/app\/modules\/Platform\/(Auth|Files)\/Application\//i.test(rel)
  if (!isStrictApplication) continue
  const source = await readFile(file, 'utf8')
  if (/think\\facade\\Request|think\\Request/i.test(source)) {
    fail(`${rel} reads HTTP Request state inside Application`)
  }
}

if (failures.length > 0) {
  console.error('Architecture checks failed:')
  failures.forEach((message) => console.error(`- ${message}`))
  process.exit(1)
}

console.log('Architecture checks passed.')
