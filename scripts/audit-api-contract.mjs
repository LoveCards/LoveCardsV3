import fs from 'node:fs/promises'
import path from 'node:path'
import { loadBackendContractMetadata } from './lib/backend-contract-metadata.mjs'
import { auditRequestContract, hasBlockingIssues, inspectControllerAction } from './lib/request-contract-audit.mjs'

const root = process.cwd()
const controllerRoot = path.join(root, 'apps/backend/app/api/controller')
const reportDir = path.join(root, 'contracts')
const routeManifestPath = path.join(root, 'contracts', 'route-manifest.json')
const checkOnly = process.argv.includes('--check')
const backendMetadata = loadBackendContractMetadata(root)
const batchTraitSource = await fs.readFile(path.join(controllerRoot, 'BatchOperateTrait.php'), 'utf8')

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

function routeAccess(route) {
  const access = route.meta?.access
  const auth = access?.auth
  const requirements = [...(access?.allOf || []), ...(access?.anyOf || [])]
  return { access, auth, requirements }
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
  const requestInspection = inspectControllerAction(source, route.action, /BatchOperateTrait/.test(source) ? [batchTraitSource] : [])
  const body = requestInspection.body
  const responses = [...body.matchAll(/ApiResponse::(create[A-Za-z]+)/g)].map((match) => match[1])
  const resources = [...body.matchAll(/([A-Za-z]+Resource)::(make|collection|paginate)/g)].map((match) => `${match[1]}::${match[2]}`)
  const imports = new Map([...source.matchAll(/use\s+(app\\[^;]+Transport\\Api\\(\w+))(?:\s+as\s+(\w+))?;/g)].map((match) => [match[3] || match[2], match[1]]))
  const validators = []
  for (const [alias, className] of imports) {
    if (new RegExp(`(?:${alias}::class|new\\s+${alias}\\s*\\()` ).test(body)) validators.push(className)
  }
  if (/BatchOperationInput::class/.test(body)) validators.push('app\\shared\\Transport\\Validation\\BatchOperationInput')
  const scene = (body.match(/\$all_scene\[['"]([^'"]+)['"]\]/) || body.match(/->scene\(['"]([^'"]+)['"]\)/) || [])[1] || null
  return {
    controllerFile: path.relative(root, file).replaceAll(path.sep, '/'),
    missingController: false,
    validates: requestInspection.usesValidated || requestInspection.usesLegacyValidation,
    responses: [...new Set(responses)],
    resources: [...new Set(resources)],
    validators: [...new Set(validators)],
    validatorScene: scene,
    requestInspection,
    hasApiResponse: responses.length > 0 || requestInspection.usesContractResponse || requestInspection.usesRawResponse,
    usesRawResponse: requestInspection.usesRawResponse,
    multipleSuccessShapes: new Set(responses.filter((value) => value === 'createOk' || value === 'createCreated' || value === 'createNoContent')).size > 1,
  }
}

function issue(severity, code, message) { return { severity, code, message } }

function auditResponseContract(route) {
  const response = route.contract?.response
  if (!response || typeof response !== 'object' || Array.isArray(response)) {
    return [issue('P0', 'INVALID_RESPONSE_CONTRACT', 'response contract must be an object')]
  }

  const issues = []
  const shape = response.shape
  const status = Number(response.status)
  const allowedShapes = new Set(['item', 'collection', 'paginated', 'void', 'raw'])
  if (!allowedShapes.has(shape)) issues.push(issue('P0', 'INVALID_RESPONSE_SHAPE', `unsupported response shape: ${shape || 'missing'}`))
  if (!Number.isInteger(status) || status < 200 || status >= 300) issues.push(issue('P0', 'INVALID_RESPONSE_STATUS', `invalid success status: ${response.status}`))

  if (shape === 'void') {
    if (status !== 204 || response.resource != null) issues.push(issue('P1', 'VOID_RESPONSE_CONTRACT_MISMATCH', 'void responses must use status 204 and resource=null'))
  } else if (shape === 'raw') {
    if (response.resource != null || typeof response.type !== 'string' || !response.type || typeof response.contentType !== 'string' || !response.contentType) {
      issues.push(issue('P1', 'RAW_RESPONSE_CONTRACT_INCOMPLETE', 'raw responses require resource=null, type and contentType'))
    }
  } else if (typeof response.resource !== 'string' || !backendMetadata.resources?.[response.resource]) {
    issues.push(issue('P0', 'UNKNOWN_RESPONSE_RESOURCE', `response resource metadata is unavailable: ${response.resource || 'null'}`))
  }

  if (status === 204 && shape !== 'void') issues.push(issue('P1', 'NO_CONTENT_SHAPE_MISMATCH', 'status 204 requires shape=void'))
  return issues
}

function responseDescriptor(route) {
  if (route.contract?.response && typeof route.contract.response === 'object') {
    const response = route.contract.response
    const resource = typeof response.resource === 'string' ? response.resource.split('\\').at(-1) : null
    const shape = response.shape || 'item'
    const operation = shape === 'paginated' ? 'paginate' : shape === 'collection' ? 'collection' : shape === 'item' ? 'make' : null
    return {
      status: Number(response.status || 200),
      shape,
      resource,
      operation,
      ...(typeof response.type === 'string' ? { type: response.type } : {}),
      ...(typeof response.contentType === 'string' ? { contentType: response.contentType } : {}),
    }
  }
  const responseFns = route.responses || []
  const status = responseFns.includes('createNoContent') ? 204 : responseFns.includes('createCreated') ? 201 : 200
  const resource = route.resources?.[0]?.split('::')[0] || null
  const operation = route.resources?.[0]?.split('::')[1] || null
  const shape = status === 204 ? 'void' : operation === 'paginate' ? 'paginated' : operation === 'collection' ? 'collection' : resource === 'ApiResource' ? 'dynamic' : 'item'
  return { status, shape, resource, operation }
}

const routeManifest = JSON.parse(await fs.readFile(routeManifestPath, 'utf8'))
const routes = (routeManifest.routes || []).map((route) => ({
  ...route,
  file: route.source || 'apps/backend/app/api/route',
  middleware: (route.middleware || []).map((value) => String(value).split('\\').at(-1)),
  hasContract: Boolean(route.contract),
  contractSource: route.contract ? 'backend' : 'legacy',
}))
routes.sort((a, b) => `${a.path}:${a.method}:${a.name}`.localeCompare(`${b.path}:${b.method}:${b.name}`))

for (const route of routes) {
  const controller = await inspectController(route)
  if (!route.contract) {
    Object.assign(route, controller)
    continue
  }

  const request = route.contract.request
  const response = route.contract.response || {}
  const resource = typeof response.resource === 'string'
    ? response.resource.split('\\').at(-1)
    : null
  const operation = response.shape === 'paginated'
    ? 'paginate'
    : response.shape === 'collection' ? 'collection' : 'make'

  Object.assign(route, {
    controllerFile: controller.controllerFile,
    missingController: controller.missingController,
    validates: request !== null,
    responses: response.status === 204 ? ['createNoContent'] : response.status === 201 ? ['createCreated'] : ['createOk'],
    resources: resource ? [`${resource}::${operation}`] : [],
    validators: request?.validate ? [request.validate] : [],
    validatorScene: request?.scene || null,
    hasApiResponse: controller.hasApiResponse,
    multipleSuccessShapes: controller.multipleSuccessShapes,
    requestInspection: controller.requestInspection ? { ...controller.requestInspection, body: undefined } : null,
    usesRawResponse: controller.usesRawResponse,
  })
}

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
  const { access, auth, requirements } = routeAccess(route)
  if (!access) route.issues.push(issue('P1', 'MISSING_ACCESS_POLICY', 'route has no structured access policy'))
  else if (!['public', 'required'].includes(auth)) route.issues.push(issue('P1', 'INVALID_ACCESS_POLICY', 'route access policy must explicitly declare auth=public or auth=required'))
  if (requirements.length && !route.middleware.includes('PermissionCheck')) {
    route.issues.push(issue('P1', 'GRANT_WITHOUT_PERMISSION_MIDDLEWARE', 'grant requirements are declared but PermissionCheck is absent'))
  }
  if (auth === 'public' && route.middleware.includes('JwtAuthCheck')) {
    route.issues.push(issue('P1', 'PUBLIC_META_WITH_JWT', 'route metadata says public but JWT middleware requires authentication'))
  }
  if (auth === 'required' && !route.middleware.includes('JwtAuthCheck')) {
    route.issues.push(issue('P1', 'REQUIRED_ACCESS_WITHOUT_JWT', 'required access policy is missing JwtAuthCheck'))
  }
  if (route.missingController) route.issues.push(issue('P0', 'MISSING_CONTROLLER', 'controller file cannot be resolved'))
  if (!route.hasApiResponse) route.issues.push(issue('P1', 'MISSING_API_RESPONSE', 'controller action does not visibly return ApiResponse'))
  if (route.multipleSuccessShapes) route.issues.push(issue('P1', 'MULTIPLE_SUCCESS_SHAPES', 'action emits more than one success shape/status'))
  if (!route.validates && route.action === 'upload') {
    route.issues.push(issue('P1', 'UPLOAD_WITHOUT_VALIDATION', 'multipart upload endpoint has no formal request validation contract'))
  }

  if (route.contract) route.issues.push(...auditResponseContract(route))
  if (route.requestInspection) route.issues.push(...auditRequestContract(route, route.requestInspection, backendMetadata))
  if (route.usesRawResponse && route.contract?.response?.shape !== 'raw') {
    route.issues.push(issue('P1', 'RAW_RESPONSE_CONTRACT_MISMATCH', 'controller returns a raw response but route contract is not shape=raw'))
  }
  if (!route.usesRawResponse && route.contract?.response?.shape === 'raw') {
    route.issues.push(issue('P1', 'RAW_CONTRACT_WITHOUT_RAW_RESPONSE', 'route declares shape=raw but the controller does not return a raw response'))
  }
}
for (const route of routes) route.response = responseDescriptor(route)

const summary = routes.flatMap((route) => route.issues || []).reduce((acc, current) => {
  acc[current.severity] = (acc[current.severity] || 0) + 1
  return acc
}, {})

const inventoryOutput = `${JSON.stringify({ routeCount: routes.length, summary, routes }, null, 2)}\n`

const lines = [
  '# API Contract Audit',
  '',
  `- Routes inspected: ${routes.length}`,
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
  const access = routeAccess(route)
  const auth = access.auth === 'public' ? 'public' : access.auth === 'required'
    ? `grants:${access.requirements.map((item) => `${item.capability}:${item.scope}`).join(',') || 'authenticated'}`
    : 'unknown'
  lines.push(`| ${route.method} | \`${route.path}\` | \`${route.name || ''}\` | ${auth} | ${route.validates ? 'yes' : 'no'} | ${route.resources.join(', ') || route.responses.join(', ') || 'unknown'} | ${(route.issues || []).map((item) => `${item.severity}:${item.code}`).join('<br>') || '—'} |`)
}
const reportOutput = `${lines.join('\n')}\n`
await fs.mkdir(reportDir, { recursive: true })
if (checkOnly) {
  const stale = []
  for (const [file, expected] of [['api-inventory.json', inventoryOutput], ['API-AUDIT.md', reportOutput]]) {
    let current = ''
    try { current = await fs.readFile(path.join(reportDir, file), 'utf8') } catch {}
    if (current !== expected) stale.push(file)
  }
  if (stale.length) {
    console.error(`API audit artifacts are stale: ${stale.join(', ')}. Run: npm run audit:api`)
    process.exitCode = 1
  }
} else {
  await fs.writeFile(path.join(reportDir, 'api-inventory.json'), inventoryOutput)
  await fs.writeFile(path.join(reportDir, 'API-AUDIT.md'), reportOutput)
}

console.log(`API audit complete: routes=${routes.length} P0=${summary.P0 || 0} P1=${summary.P1 || 0} P2=${summary.P2 || 0}`)
if (hasBlockingIssues(summary)) process.exitCode = 2
