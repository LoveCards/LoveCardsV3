import fs from 'node:fs/promises'
import path from 'node:path'

const root = process.cwd()
const failures = []
const fail = (message) => failures.push(message)

async function read(file) {
  return fs.readFile(path.join(root, file), 'utf8')
}

function capabilityCatalog(source) {
  const body = source.match(/function\s+all\(\)\s*:\s*array\s*\{[\s\S]*?return\s*\[([\s\S]*?)\];\s*\}/)?.[1] || ''
  return new Set([...body.matchAll(/['"]([a-z][a-z0-9_.]+)['"]\s*=>/gi)].map((match) => match[1]))
}

function adminPolicyCapabilities(source) {
  const body = source.match(/\$roles\['admin'\]\s*=>\s*\[([\s\S]*?)\]\s*,\s*\$roles\['root'\]/)?.[1] || ''
  return new Set([...body.matchAll(/['"]([a-z][a-z0-9_.]+)['"]/gi)].map((match) => match[1]))
}

function normalizePolicyGrant(capability, catalog) {
  if (capability.endsWith('.all')) return `${capability.slice(0, -4)}:all`
  return `${capability}:${catalog.has(`${capability}.all`) ? 'own' : 'global'}`
}

function migrationAdminGrants(source, catalog) {
  const grants = new Set()
  for (const block of source.matchAll(/INSERT\s+INTO\s+`role_capabilities`[\s\S]*?SELECT\s+2\s*,\s*c\.capability\s+FROM\s*\(([\s\S]*?)\)\s*c[\s\S]*?rc\.role_id\s*=\s*2/gi)) {
    for (const match of block[1].matchAll(/['"]([a-z][a-z0-9_.]+)['"]/gi)) {
      grants.add(normalizePolicyGrant(match[1], catalog))
    }
  }

  for (const match of source.matchAll(/(?:SELECT|UNION\s+ALL\s+SELECT|UNION\s+SELECT)\s+2(?:\s+role_id)?\s*,\s*['"]([a-z][a-z0-9_.]+)['"](?:\s+capability)?(?:\s*,\s*['"](own|all|global)['"](?:\s+scope)?)?/gi)) {
    grants.add(`${match[1]}:${match[2] || 'global'}`)
  }
  return grants
}

function requiredGrantsFromManifest(manifest) {
  const result = []
  for (const route of manifest.routes || []) {
    const access = route.meta?.access
    if (!access || !['public', 'required'].includes(access.auth)) {
      fail(`${route.name || route.method + ' ' + route.path}: route access policy is incomplete`)
      continue
    }
    for (const grant of [...(access.allOf || []), ...(access.anyOf || [])]) {
      if (!grant || typeof grant.capability !== 'string' || !['own', 'all', 'global'].includes(grant.scope)) {
        fail(`${route.name || route.method + ' ' + route.path}: invalid capability grant metadata`)
        continue
      }
      result.push({ route, grant })
    }
  }
  return result
}

function navEntries(source) {
  return [...source.matchAll(/path:\s*['"]([^'"]+)['"][\s\S]*?grants:\s*\[\s*\{\s*capability:\s*['"]([^'"]+)['"]\s*,\s*scope:\s*['"]([^'"]+)['"]/g)]
    .map((match) => ({ path: match[1], capability: match[2], scope: match[3] }))
}

async function pageExists(routePath) {
  const relative = routePath
    .replace(/^\//, '')
    .split('/')
    .map((segment) => segment.startsWith(':') ? `[${segment.slice(1)}]` : segment)
    .join(path.sep)
  const candidates = [
    path.join(root, 'apps/admin/pages', `${relative}.vue`),
    path.join(root, 'apps/admin/pages', relative, 'index.vue'),
  ]
  for (const candidate of candidates) {
    try {
      const stat = await fs.stat(candidate)
      if (stat.isFile()) return true
    } catch {}
  }
  return false
}

const manifest = JSON.parse(await read('contracts/route-manifest.json'))
const catalog = capabilityCatalog(await read('apps/backend/app/modules/Platform/Rbac/Domain/CapabilityCatalog.php'))
const adminPolicy = adminPolicyCapabilities(await read('apps/backend/app/modules/Platform/Rbac/Domain/SystemRolePolicy.php'))
const routeGrants = requiredGrantsFromManifest(manifest)
const adminRouteGrants = new Set()
const migrationDirectory = path.join(root, 'apps/backend/database/migrations')
const migrationSql = (await Promise.all((await fs.readdir(migrationDirectory))
  .filter((file) => file.endsWith('.sql'))
  .map((file) => fs.readFile(path.join(migrationDirectory, file), 'utf8')))).join('\n')

if (!catalog.size) fail('CapabilityCatalog::all() could not be parsed')
if (!adminPolicy.size) fail("SystemRolePolicy admin capability matrix could not be parsed")
const seededAdminGrants = migrationAdminGrants(migrationSql, catalog)

for (const capability of catalog) {
  const canonical = capability.endsWith('.all') ? capability.slice(0, -4) : capability
  if (!migrationSql.includes(`'${canonical}'`)) {
    fail(`CapabilityCatalog entry has no migration seed or adoption rule: ${canonical}`)
  }
}

for (const capability of adminPolicy) {
  const grant = normalizePolicyGrant(capability, catalog)
  if (!seededAdminGrants.has(grant)) {
    fail(`admin SystemRolePolicy grant has no database migration seed: ${grant}`)
  }
}

for (const { route, grant } of routeGrants) {
  if (!catalog.has(grant.capability)) {
    fail(`${route.name || route.path}: capability is absent from CapabilityCatalog: ${grant.capability}`)
    continue
  }
  // 管理路由声明的 scope 必须能由默认 admin 角色表示；普通用户路由只检查能力目录归属。
  if (route.path === '/admin' || route.path.startsWith('/admin/')) {
    adminRouteGrants.add(`${grant.capability}:${grant.scope}`)
    const granted = grant.scope === 'all'
      ? adminPolicy.has(`${grant.capability}.all`)
      : adminPolicy.has(grant.capability) || adminPolicy.has(`${grant.capability}.all`)
    if (!granted) fail(`${route.name || route.path}: admin SystemRolePolicy does not grant ${grant.capability}:${grant.scope}`)
  }
}

const navigation = navEntries(await read('apps/admin/config/navigation.ts'))
const navigationPaths = new Set()
for (const entry of navigation) {
  if (navigationPaths.has(entry.path)) fail(`navigation path is duplicated: ${entry.path}`)
  navigationPaths.add(entry.path)
  if (!entry.path.startsWith('/apps/')) fail(`navigation path must be under /apps/: ${entry.path}`)
  if (!['all', 'global'].includes(entry.scope)) fail(`navigation scope must be all or global: ${entry.path}`)
  if (!catalog.has(entry.capability)) fail(`navigation ${entry.path} uses unknown capability: ${entry.capability}`)
  const policyGrant = entry.scope === 'all'
    ? adminPolicy.has(`${entry.capability}.all`)
    : adminPolicy.has(entry.capability) || adminPolicy.has(`${entry.capability}.all`)
  if (!policyGrant) fail(`navigation ${entry.path} is not granted by admin SystemRolePolicy: ${entry.capability}:${entry.scope}`)
  if (!adminRouteGrants.has(`${entry.capability}:${entry.scope}`)) {
    fail(`navigation ${entry.path} has no matching admin route grant: ${entry.capability}:${entry.scope}`)
  }
  if (!(await pageExists(entry.path))) fail(`navigation ${entry.path} has no matching Nuxt page`)
}

if (failures.length) {
  console.error('Admin contract consistency checks failed:')
  for (const failure of failures) console.error(`- ${failure}`)
  process.exit(1)
}

console.log(`Admin contract consistency checks passed (${routeGrants.length} route grants, ${navigation.length} navigation entries).`)
