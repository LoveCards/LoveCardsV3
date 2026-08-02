import fs from 'node:fs/promises'
import path from 'node:path'

const root = process.cwd()
const routeRoot = path.join(root, 'apps/backend/app/api/route')
const controllerRoot = path.join(root, 'apps/backend/app/api/controller')
const reportDir = path.join(root, 'contracts')
const sdkResourceRoot = path.join(root, 'packages/sdk/src/resources')

const routeFiles = (await fs.readdir(routeRoot)).filter((name) => name.endsWith('.php')).sort()

function normalizePath(prefix, route) {
  const value = [prefix, route].filter(Boolean).join('/').replaceAll(/\/+/g, '/').replace(/^\//, '').replace(/\/$/, '')
  return `/${value}`.replace(/\/\//g, '/') || '/'
}

function parseMeta(statement) {
  const meta = {}
  const name = statement.match(/'name'\s*=>\s*'([^']*)'/)
  const group = statement.match(/'group'\s*=>\s*'([^']*)'/)
  const caps = statement.match(/'caps'\s*=>\s*\[([^\]]*)\]/)
  if (name) meta.name = name[1]
  if (group) meta.group = group[1]
  if (caps) meta.caps = [...caps[1].matchAll(/'([^']+)'/g)].map((match) => match[1])
  meta.public = /'public'\s*=>\s*true/.test(statement)
  return meta
}

function middleware(statement) {
  return [...statement.matchAll(/middleware\((\w+)::class/g)].map((match) => match[1])
}

function parseRoutes(source, file) {
  const routes = []
  const groups = []
  const groupRe = /Route::group\(\s*'([^']*)'\s*,\s*function\s*\(\)\s*\{([\s\S]*?)\}\)([\s\S]*?);/g
  let match
  while ((match = groupRe.exec(source))) {
    groups.push([match.index, groupRe.lastIndex])
    const prefix = match[1]
    const body = match[2]
    const tail = match[3]
    for (const route of parseRouteStatements(body, file, prefix, middleware(tail))) routes.push(route)
  }

  let topLevel = source
  for (const [start, end] of groups) topLevel = `${topLevel.slice(0, start)}${' '.repeat(end - start)}${topLevel.slice(end)}`
  for (const route of parseRouteStatements(topLevel, file, '', [])) routes.push(route)
  return routes
}

function parseRouteStatements(source, file, prefix, inheritedMiddleware) {
  const routes = []
  const routeRe = /Route::(get|post|put|patch|delete)\(\s*'([^']*)'\s*,\s*'([^']+)'\)([\s\S]*?);/g
  let match
  while ((match = routeRe.exec(source))) {
    const statement = match[0]
    const [controller, method] = match[3].split('/')
    const meta = parseMeta(statement)
    const middlewares = [...new Set([...inheritedMiddleware, ...middleware(statement)])]
    routes.push({
      file,
      method: match[1].toUpperCase(),
      path: normalizePath(prefix, match[2]),
      controller,
      action: method,
      name: (statement.match(/->name\('([^']+)'\)/) || [])[1] || null,
      meta,
      middleware: middlewares,
      hasContract: /setOption\(\s*'contract'/.test(statement),
    })
  }
  return routes
}

function controllerPath(controller) {
  return path.join(controllerRoot, ...controller.split('.')) + '.php'
}

async function inspectController(route) {
  const file = controllerPath(route.controller)
  let source = ''
  try { source = await fs.readFile(file, 'utf8') } catch { return { controllerFile: file, missingController: true } }
  const start = source.indexOf(`function ${route.action}(`)
  const end = start < 0 ? -1 : source.indexOf('\n    public function ', start + 10)
  let body = start < 0 ? '' : source.slice(start, end < 0 ? source.length : end)
  if (route.action === 'batch' && /BatchOperateTrait/.test(source)) {
    body += await fs.readFile(path.join(controllerRoot, 'BatchOperateTrait.php'), 'utf8')
  }
  const responses = [...body.matchAll(/ApiResponse::(create[A-Za-z]+)/g)].map((match) => match[1])
  const resources = [...body.matchAll(/([A-Za-z]+Resource)::(make|collection|paginate)/g)].map((match) => `${match[1]}::${match[2]}`)
  return {
    controllerFile: path.relative(root, file),
    missingController: false,
    validates: /\b(?:param|validate)\s*\(|->check\s*\(/.test(body),
    responses: [...new Set(responses)],
    resources: [...new Set(resources)],
    hasApiResponse: responses.length > 0,
    multipleSuccessShapes: new Set(responses.filter((value) => value === 'createOk' || value === 'createCreated' || value === 'createNoContent')).size > 1,
  }
}

function issue(severity, code, message) { return { severity, code, message } }

function normalizeSdkPath(value) {
  return value
    .replaceAll(/`/g, '')
    .replaceAll(/\$\{[^}]+\}/g, ':param')
    .replaceAll(/\$\{[^}]+\}/g, ':param')
    .replace(/\/$/, '') || '/'
}

async function readSdkMethods() {
  const methods = []
  const files = (await fs.readdir(sdkResourceRoot)).filter((name) => name.endsWith('.ts')).sort()
  for (const file of files) {
    const source = await fs.readFile(path.join(sdkResourceRoot, file), 'utf8')
    const methodRe = /^\s{2}(\w+)\([^)]*\):\s*Promise<(.+?)>\s*\{\s*return\s+this\._(get|post|put|patch|delete)<(.+?)>\((['"`])([^'"`]+)\5/gm
    let match
    while ((match = methodRe.exec(source))) {
      methods.push({ file: `packages/sdk/src/resources/${file}`, method: match[1], httpMethod: match[3].toUpperCase(), returnType: match[2], path: normalizeSdkPath(match[6]) })
    }
  }
  return methods
}

const sdkMethods = await readSdkMethods()
const sdkByEndpoint = new Map(sdkMethods.map((method) => [`${method.httpMethod} ${method.path}`, method]))

const routes = []
for (const file of routeFiles) {
  const source = await fs.readFile(path.join(routeRoot, file), 'utf8')
  routes.push(...parseRoutes(source, `apps/backend/app/api/route/${file}`))
}
routes.sort((a, b) => `${a.path}:${a.method}:${a.name}`.localeCompare(`${b.path}:${b.method}:${b.name}`))

for (const route of routes) Object.assign(route, await inspectController(route))

const seen = new Map()
for (const route of routes) {
  const key = `${route.method} ${route.path}`
  const duplicate = seen.get(key)
  if (duplicate) {
    route.issues ??= []
    route.issues.push(issue('P1', 'DUPLICATE_ENDPOINT', `duplicates ${duplicate.name || 'unnamed route'}`))
  } else seen.set(key, route)

  route.issues ??= []
  if (!route.name) route.issues.push(issue('P1', 'MISSING_OPERATION_ID', 'route has no stable operation name'))
  if (!route.meta.name || !route.meta.group) route.issues.push(issue('P2', 'INCOMPLETE_ROUTE_META', 'route meta lacks name or group'))
  if (route.meta.caps?.length && !route.middleware.includes('PermissionCheck')) {
    route.issues.push(issue('P1', 'CAPABILITY_WITHOUT_PERMISSION_MIDDLEWARE', 'capabilities are declared but PermissionCheck is absent'))
  }
  if (route.meta.public && route.middleware.includes('JwtAuthCheck')) {
    route.issues.push(issue('P1', 'PUBLIC_META_WITH_JWT', 'route metadata says public but JWT middleware requires authentication'))
  }
  if (!route.meta.public && !route.meta.caps?.length && !route.middleware.includes('JwtAuthCheck')) {
    route.issues.push(issue('P1', 'UNAUTHENTICATED_NONPUBLIC_ROUTE', 'route is neither public nor protected by JWT/capability'))
  }
  if (route.missingController) route.issues.push(issue('P0', 'MISSING_CONTROLLER', 'controller file cannot be resolved'))
  if (!route.hasApiResponse) route.issues.push(issue('P1', 'MISSING_API_RESPONSE', 'controller action does not visibly return ApiResponse'))
  if (route.multipleSuccessShapes) route.issues.push(issue('P1', 'MULTIPLE_SUCCESS_SHAPES', 'action emits more than one success shape/status'))
  if (!route.validates && route.action === 'upload') {
    route.issues.push(issue('P1', 'UPLOAD_WITHOUT_VALIDATION', 'multipart upload endpoint has no formal request validation contract'))
  }

  const sdk = sdkByEndpoint.get(`${route.method} ${route.path.replace(/:[^/]+/g, ':param')}`)
  route.sdk = sdk || null
  if (!sdk) route.issues.push(issue('P2', 'SDK_ENDPOINT_MISSING', 'no SDK resource method maps to this HTTP endpoint'))
  if (sdk && sdk.returnType.trim() === 'void' && route.responses.some((value) => value === 'createOk' || value === 'createCreated')) {
    route.issues.push(issue('P1', 'SDK_RESPONSE_TYPE_MISMATCH', `SDK returns void but backend emits ${route.responses.filter((value) => value === 'createOk' || value === 'createCreated').join(', ')}`))
  }
}

const summary = routes.flatMap((route) => route.issues || []).reduce((acc, current) => {
  acc[current.severity] = (acc[current.severity] || 0) + 1
  return acc
}, {})

await fs.mkdir(reportDir, { recursive: true })
await fs.writeFile(path.join(reportDir, 'api-inventory.json'), `${JSON.stringify({ generatedAt: new Date().toISOString(), routeCount: routes.length, summary, routes }, null, 2)}\n`)

const lines = [
  '# API Contract Audit',
  '',
  `- Routes inspected: ${routes.length}`,
  `- SDK methods inspected: ${sdkMethods.length}`,
  `- P0: ${summary.P0 || 0}`,
  `- P1: ${summary.P1 || 0}`,
  `- P2: ${summary.P2 || 0}`,
  '',
  '> This is a static preflight audit. No API contract or SDK code is generated by this command.',
  '',
  '| Method | Path | Operation | Auth | Validate | Response | Issues |',
  '|---|---|---|---|---:|---|---|',
]
for (const route of routes) {
  const auth = route.meta.public ? 'public' : route.meta.caps?.length ? `caps:${route.meta.caps.join(',')}` : route.middleware.includes('JwtAuthCheck') ? 'jwt' : 'unknown'
  lines.push(`| ${route.method} | \`${route.path}\` | \`${route.name || ''}\` | ${auth} | ${route.validates ? 'yes' : 'no'} | ${route.resources.join(', ') || route.responses.join(', ') || 'unknown'} | ${(route.issues || []).map((item) => `${item.severity}:${item.code}`).join('<br>') || '—'} |`)
}
await fs.writeFile(path.join(reportDir, 'API-AUDIT.md'), `${lines.join('\n')}\n`)

console.log(`API audit complete: routes=${routes.length} P0=${summary.P0 || 0} P1=${summary.P1 || 0} P2=${summary.P2 || 0}`)
if ((summary.P0 || 0) > 0) process.exitCode = 2
