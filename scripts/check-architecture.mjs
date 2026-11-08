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

for (const directory of ['apps/backend', 'apps/admin', 'apps/default-theme', 'apps/realtime-gateway', 'packages/sdk']) {
  if (!(await exists(path.join(root, directory)))) fail(`Missing workspace directory: ${directory}`)
}

const rootPackage = await readJson(path.join(root, 'package.json'))
const adminPackage = await readJson(path.join(root, 'apps/admin/package.json'))
const defaultThemePackage = await readJson(path.join(root, 'apps/default-theme/package.json'))
const sdkPackage = await readJson(path.join(root, 'packages/sdk/package.json'))

// 通信运行时（契约 §7）：独立 Realtime Gateway 是第四个 Node workspace。
const expectedWorkspaces = ['apps/admin', 'apps/default-theme', 'apps/realtime-gateway', 'packages/sdk']
if (JSON.stringify(rootPackage.workspaces) !== JSON.stringify(expectedWorkspaces)) {
  fail(`Root workspaces must be exactly: ${expectedWorkspaces.join(', ')}`)
}
if (adminPackage.name !== '@lovecards/admin') fail('Admin package name must be @lovecards/admin')
if (defaultThemePackage.name !== '@lovecards/default-theme') fail('Default theme package name must be @lovecards/default-theme')
if (sdkPackage.name !== '@lovecards/sdk') fail('SDK package name must be @lovecards/sdk')
const gatewayPackage = await readJson(path.join(root, 'apps/realtime-gateway/package.json'))
if (gatewayPackage.name !== '@lovecards/realtime-gateway') fail('Gateway package name must be @lovecards/realtime-gateway')
if (gatewayPackage.private !== true) fail('Realtime Gateway must be private (进程产物，不发布 npm)')
if (adminPackage.dependencies?.['@lovecards/sdk'] !== sdkPackage.version) {
  fail('Admin must depend on the local SDK version, not a filesystem path')
}
if (defaultThemePackage.dependencies?.['@lovecards/sdk'] !== sdkPackage.version) {
  fail('Default theme must depend on the local SDK version, not a filesystem path')
}
if (sdkPackage.scripts?.postbuild) fail('SDK postbuild must not write into an application')
if (adminPackage.dependencies?.axios) fail('Admin must not depend on Axios directly; use @lovecards/sdk')

const adminSourceFiles = await walk(path.join(root, 'apps/admin'), (file) => {
  if (!/\.(?:ts|vue|js)$/.test(file)) return false
  // Browser fixtures intentionally exercise the HTTP boundary; production admin
  // code remains required to use the generated SDK.
  return !/[\\/]e2e[\\/]/.test(file) && !/\.test\.[^.]+$/i.test(file)
})
const adminPagesRoot = path.join(root, 'apps/admin/pages')
for (const file of adminSourceFiles) {
  if (!file.startsWith(`${adminPagesRoot}${path.sep}`) || !file.endsWith('.vue') || path.basename(file) === 'index.vue') continue
  const siblingDirectory = file.slice(0, -'.vue'.length)
  try {
    const info = await stat(siblingDirectory)
    if (!info.isDirectory()) continue
    const source = await readFile(file, 'utf8')
    if (!/<NuxtPage\b/i.test(source)) {
      fail(`Admin page file shadows nested routes; move it to index.vue or render NuxtPage: ${relative(file)}`)
    }
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
  }
}
if (await exists(path.join(root, 'apps/ui'))) fail('Legacy apps/ui directory must not exist; use apps/default-theme/ui')

const defaultThemeRoot = path.join(root, 'apps/default-theme')
const defaultThemeDesignRoot = path.join(defaultThemeRoot, 'ui')
const defaultThemeSourceFiles = await walk(path.join(defaultThemeRoot, 'src'), (file) => /\.(?:js|jsx|css)$/.test(file))
for (const file of defaultThemeSourceFiles) {
  // Tests may read design specifications as executable acceptance criteria;
  // only production source must remain independent from the design workspace.
  if (/[\\/]test[\\/]/.test(file) || /\.test\.[^.]+$/i.test(file)) continue
  const source = await readFile(file, 'utf8')
  const references = [
    ...source.matchAll(/['"](\.{1,2}\/[^'"]+)['"]/g),
    ...source.matchAll(/url\(\s*['"]?(\.{1,2}\/[^'"\)]+)['"]?\s*\)/g),
  ]
  for (const match of references) {
    const dependency = path.resolve(path.dirname(file), match[1])
    if (dependency === defaultThemeDesignRoot || dependency.startsWith(`${defaultThemeDesignRoot}${path.sep}`)) {
      fail(`Default theme runtime source must not depend on design references: ${relative(file)} -> ${match[1]}`)
    }
  }
}

for (const directory of ['apps/admin/api', 'apps/admin/components/apps', 'apps/admin/stores/api']) {
  if (await exists(path.join(root, directory))) fail(`Admin legacy directory must not exist: ${directory}`)
}
for (const file of adminSourceFiles) {
  const source = await readFile(file, 'utf8')
  const rel = relative(file)
  if (file.startsWith(`${adminPagesRoot}${path.sep}`)) {
    const legacyRoute = source.match(/\/apps\/(?:cards|comments|tags|governance|system|storage|sender|captcha|roles|permissions|view|updata)(?=[/?#'"`]|$)/)
    if (legacyRoute) {
      fail(`Admin production pages must link to canonical routes, not ${legacyRoute[0]}: ${rel}`)
    }
  }
  if (/from\s+['"](?:axios|ofetch)['"]/.test(source)) {
    fail(`Admin source must not import a transport client: ${rel}`)
  }
  if (/(?:\$fetch|useFetch|\bfetch)\s*\(/.test(source)) {
    fail(`Admin source must call APIs through @lovecards/sdk: ${rel}`)
  }
  if (/['"](?:~|@)\/?api\/(?:app|axios|interceptors)(?:\/|['"])/.test(source)) {
    fail(`Admin source references the removed API layer: ${rel}`)
  }
  if (/NuxtLayout\s+name=['"]root['"]/.test(source)) {
    fail(`Admin pages must use the application layout: ${rel}`)
  }
  if (/\buseNotifier\s*\(/.test(source) && !rel.endsWith('components/shared/NotificationNotifier.vue')) {
    fail(`Admin business code must use useAdminNotifier: ${rel}`)
  }
  if (/\bany\b/.test(source)) {
    fail(`Admin source must use explicit types or unknown, not any: ${rel}`)
  }

  if (file.endsWith('.vue')) {
    const templateStart = source.match(/<template(?:\s[^>]*)?>/i)
    const templateEnd = source.lastIndexOf('</template>')
    const template = templateStart && templateEnd > (templateStart.index ?? 0)
      ? source.slice((templateStart.index ?? 0) + templateStart[0].length, templateEnd)
      : ''
    const templateWithoutComments = template.replace(/<!--[\s\S]*?-->/g, '')
    const rawControl = templateWithoutComments.match(/<(?:button|input|select|textarea|form|table|dialog)\b/i)
    if (rawControl) {
      fail(`Admin interactive controls must use Vuetify primitives: ${rel}`)
    }
    if (/(?:^|\s):?style\s*=/im.test(templateWithoutComments)) {
      fail(`Admin templates must use Vuetify props, theme tokens, and utility classes instead of inline styles: ${rel}`)
    }
    if (/#[0-9a-f]{3,8}\b/i.test(source) || /rgba?\(\s*\d/i.test(source)) {
      fail(`Admin Vue files must use Vuetify theme tokens instead of hard-coded colors: ${rel}`)
    }

    for (const match of templateWithoutComments.matchAll(/<v-btn\b[\s\S]*?>/gi)) {
      const tag = match[0]
      const iconOnly = /(?:\s|:)icon(?:\s|=)/i.test(tag)
      const accessibleName = /(?:aria-label|\btitle)\s*=/i.test(tag)
      if (iconOnly && !accessibleName) {
        fail(`Admin icon-only buttons need an aria-label or title: ${rel}`)
      }
    }

    for (const match of templateWithoutComments.matchAll(/<v-dialog\b[\s\S]*?>/gi)) {
      if (!/(?:aria-label|aria-labelledby)\s*=/i.test(match[0])) {
        fail(`Admin dialogs need an accessible name via aria-label or aria-labelledby: ${rel}`)
      }
    }

    let cardDepth = 0
    let maximumCardDepth = 0
    for (const match of templateWithoutComments.matchAll(/<\/?v-card(?:\s|>)[^>]*>/gi)) {
      const tag = match[0]
      if (tag.startsWith('</')) cardDepth = Math.max(0, cardDepth - 1)
      else if (!tag.endsWith('/>')) {
        cardDepth += 1
        maximumCardDepth = Math.max(maximumCardDepth, cardDepth)
      }
    }
    if (maximumCardDepth > 1) {
      fail(`Admin pages must not nest v-card surfaces: ${rel}`)
    }
  }
}

const adminNuxtConfig = await readFile(path.join(root, 'apps/admin/nuxt.config.ts'), 'utf8')
if (/['"]vuetify-notifier['"]/.test(adminNuxtConfig)) {
  fail('Admin must register vuetify-notifier only through vuetify-notifier/nuxt')
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

for (const lock of ['apps/admin/package-lock.json', 'apps/default-theme/package-lock.json', 'packages/sdk/package-lock.json']) {
  try {
    await readFile(path.join(root, lock))
    fail(`${lock} must be replaced by the root package-lock.json`)
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
  }
}

for (const directory of ['apps/backend/.git', 'apps/admin/.git', 'apps/default-theme/.git', 'packages/sdk/.git']) {
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
const systemHostRoot = path.join(root, 'apps/backend/app/system')
for (const file of await walk(systemHostRoot, (candidate) => candidate.endsWith('.php'))) {
  if (!path.relative(systemHostRoot, file).split(path.sep).join('/').startsWith('controller/')) {
    fail(`System Host may contain controller adapters only: ${relative(file)}`)
  }
}
const installerHost = await readFile(path.join(systemHostRoot, 'controller/Install.php'), 'utf8')
if (!installerHost.includes("'schema.sql'") || !installerHost.includes("'seed.sql'") || installerHost.includes("'data.sql'")) {
  fail('Installer Host must load database/schema.sql and database/seed.sql, not the legacy combined data.sql')
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
  const sourceModule = rel.split('/').slice(4, 6).join('/')
  const layer = rel.split('/').find((part) => ['Application', 'Domain', 'Contract', 'Infrastructure'].includes(part))
  for (const match of source.matchAll(/^\s*use\s+(app\\[^;]+|think\\[^;]+);/gmi)) {
    const dependency = match[1].replace(/\s+as\s+.+$/i, '')
    const key = `${rel}:${dependency}`
    if (sourceModule === 'Operations/SystemLifecycle' && /^app\\modules\\(?!Operations\\SystemLifecycle\\)[^\\]+\\[^\\]+\\(?!Public\\|Contract\\)/i.test(dependency)) {
      fail(`SystemLifecycle must not depend on another module's internals: ${key}`)
    }
    if (sourceModule !== 'Content/Cards' && /^app\\modules\\Content\\Cards\\(?!Public\\|Contract\\)/i.test(dependency)) {
      fail(`Cross-module Cards dependencies must use Public or Contract: ${key}`)
    }
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

// 审计 P3-10 守卫：meta 声明了授权组合（allOf/anyOf）的路由必须挂 PermissionCheck。
// 历史上 community 族存在"业务层授权、中间件缺席"的自洽模式，但没有任何自动检查
// 阻止新路由漏挂中间件——授权四元组漂移只能靠人肉评审发现。此处按清单锁定该模式。
const routeManifestFile = path.join(root, 'contracts', 'route-manifest.json')
if (await exists(routeManifestFile)) {
  const routeManifest = await readJson(routeManifestFile)
  const declaredRoutes = Array.isArray(routeManifest) ? routeManifest : (routeManifest.routes ?? [])
  for (const route of declaredRoutes) {
    const metaText = JSON.stringify(route?.meta ?? {})
    const middlewareText = JSON.stringify(route?.middleware ?? [])
    if ((metaText.includes('"allOf"') || metaText.includes('"anyOf"')) && !middlewareText.includes('PermissionCheck')) {
      fail(`route ${route?.name ?? route?.path ?? 'unknown'} declares grants in meta but does not attach PermissionCheck middleware`)
    }
  }
}

if (failures.length > 0) {
  console.error('Architecture checks failed:')
  failures.forEach((message) => console.error(`- ${message}`))
  process.exit(1)
}

console.log('Architecture checks passed.')
