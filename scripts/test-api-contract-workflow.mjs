import assert from 'node:assert/strict'
import fs from 'node:fs/promises'
import { auditRequestContract, describeRequestContract, extractMethodBody, hasBlockingIssues, inspectControllerAction } from './lib/request-contract-audit.mjs'
import { generatedResourceMethod, operationResponseType, qualifyType } from './lib/sdk-codegen.mjs'
import { loadBackendContractMetadata } from './lib/backend-contract-metadata.mjs'

const validator = 'app\\modules\\Fixture\\Transport\\Api\\Input'
const metadata = { validators: { [validator]: {
  rules: { resource_type: 'require', resource_id: 'require', id: 'number' },
  allScenes: { resource: { normal: [], require: ['resource_type', 'resource_id'], nonNull: false, toNull: false } },
  requestSchemas: { resource: { type: 'object', properties: { resource_type: { type: 'string' }, resource_id: { type: 'string' } }, required: ['resource_type', 'resource_id'], additionalProperties: false } },
} } }
const route = { path: '/me/library', controller: 'Fixture', action: 'remove', contract: { request: { validate: validator, scene: 'resource' } } }

const oneLine = `public function remove(){ $p=$this->validated(); return $p['resource_id']; } public function next(){ return '}'; }`
assert.match(extractMethodBody(oneLine, 'remove'), /resource_id/)
assert.equal(inspectControllerAction(oneLine, 'remove').accessedFields[0], 'resource_id')

const enriched = `public function create(){ $p=$this->validated(); $p['owner_id']=request()->auth->uid(); return $p['owner_id']; }`
assert.deepEqual(inspectControllerAction(enriched, 'create').accessedFields, [])

assert.deepEqual(auditRequestContract({ ...route, contract: { request: null } }, inspectControllerAction(oneLine, 'remove'), metadata).map((item) => item.code), ['REQUEST_INPUT_WITHOUT_CONTRACT'])

const incomplete = structuredClone(metadata)
delete incomplete.validators[validator].allScenes.resource.require[1]
assert(auditRequestContract(route, inspectControllerAction(oneLine, 'remove'), incomplete).some((item) => item.code === 'CONTROLLER_FIELD_NOT_IN_CONTRACT'))

const direct = `public function remove(){ return Request::param('resource_id'); }`
assert(auditRequestContract(route, inspectControllerAction(direct, 'remove'), metadata).some((item) => item.code === 'DIRECT_REQUEST_INPUT'))
assert(auditRequestContract(route, inspectControllerAction(direct, 'remove'), metadata).some((item) => item.code === 'REQUEST_CONTRACT_NOT_EXECUTED'))

const schemaMismatch = structuredClone(metadata)
delete schemaMismatch.validators[validator].requestSchemas.resource.properties.resource_id
assert(describeRequestContract(route, schemaMismatch).issues.some((item) => item.code === 'RUNTIME_FIELD_MISSING_FROM_SCHEMA'))

const deleteMethod = generatedResourceMethod({ method: 'remove', params: 'params: { resource_type: string; resource_id: string }', httpMethod: 'DELETE', returnType: 'void', path: '/me/library' })
assert.match(deleteMethod, /_delete<void>\("\/me\/library", params\)/)
const pathQueryMethod = generatedResourceMethod({ method: 'unlike', params: 'id: number, params?: { type?: string }', httpMethod: 'DELETE', returnType: 'void', path: '/me/likes/:id' })
assert.match(pathQueryMethod, /`\/me\/likes\/\$\{id\}`, params/)
const handlePathMethod = generatedResourceMethod({ method: 'profile', params: 'handle: string', httpMethod: 'GET', returnType: 'unknown', path: '/users/by-handle/:handle' })
assert.match(handlePathMethod, /encodeURIComponent\(String\(handle\)\)/)
const bodyMethod = generatedResourceMethod({ method: 'send', params: 'data: { body: string }', httpMethod: 'POST', returnType: 'void', path: '/me/messages' })
assert.match(bodyMethod, /_post<void>\("\/me\/messages", data\)/)
assert.equal(qualifyType('ApiTypes.NotificationListCategory'), 'ApiTypes.NotificationListCategory')

assert.equal(hasBlockingIssues({ P1: 1 }), true)
assert.equal(hasBlockingIssues({ P2: 1 }), false)
assert.throws(() => loadBackendContractMetadata(process.cwd(), ['definitely-missing-php-binary']), /Unable to export backend contract metadata/)

const openapi = JSON.parse(await fs.readFile(new URL('../contracts/openapi.json', import.meta.url), 'utf8'))
const routeManifest = JSON.parse(await fs.readFile(new URL('../contracts/route-manifest.json', import.meta.url), 'utf8'))
const backendMetadata = loadBackendContractMetadata(process.cwd())

// Requiredness is executed from `all_scene` by BaseController. A shared
// ThinkPHP `require` rule on a field that is optional in another scene would
// silently make that optional route require the field again (for example a
// parameter-free GET list route). Keep this invariant global, not per module.
for (const [validator, details] of Object.entries(backendMetadata.validators || {})) {
  for (const [field, rule] of Object.entries(details.rules || {})) {
    const rules = Array.isArray(rule) ? rule.map(String) : String(rule || '').split('|')
    if (!rules.includes('require')) continue
    for (const [sceneName, scene] of Object.entries(details.allScenes || {})) {
      const required = Array.isArray(scene?.require) ? scene.require : []
      const optional = ['normal', 'nonNull', 'toNull'].flatMap((bucket) => Array.isArray(scene?.[bucket]) ? scene[bucket] : [])
      assert(
        !optional.includes(field) || required.includes(field),
        `${validator}::${sceneName} marks ${field} optional but its shared rule still contains require`,
      )
    }
  }
}

for (const operations of Object.values(openapi.paths)) {
  for (const [method, operation] of Object.entries(operations)) {
    const query = operation.parameters?.find((item) => item.in === 'query')
    const schema = operation.requestBody
      ? Object.values(operation.requestBody.content || {})[0]?.schema
      : query?.schema
    if (!schema) continue
    const signature = schema?.['x-typescript-params'] || ''
    if (['get', 'delete'].includes(method)) assert.match(signature, /^params(?:\?|):/, `${operation.operationId} must use query params`)
    else assert.match(signature, /^(?:data(?:\?|):|formData:)/, `${operation.operationId} must use a request body`)
    if ((schema?.required || []).length && !signature.startsWith('formData:')) {
      assert(!/^(?:params|data)\?:/.test(signature), `${operation.operationId} must require its request object`)
      assert.equal(operation.requestBody?.required ?? query?.required, true, `${operation.operationId} must require its OpenAPI request container`)
    }
  }
}

function operation(operationId) {
  return Object.values(openapi.paths).flatMap((methods) => Object.values(methods)).find((item) => item.operationId === operationId)
}

const configDelete = operation('config.delete')
assert.equal(configDelete.parameters.find((item) => item.in === 'query').required, true)
assert.match(configDelete.parameters.find((item) => item.in === 'query').schema['x-typescript-params'], /^params:/)

const libraryRemove = operation('library.remove')
assert.match(libraryRemove.parameters.find((item) => item.in === 'query').schema['x-typescript-params'], /resource_type: string; resource_id: string/)

const notificationUnread = operation('notifications.unread')
assert.equal(notificationUnread.requestBody, undefined)
assert.equal(
  notificationUnread.parameters.find((item) => item.in === 'query').schema['x-typescript-params'],
  'params?: { category?: NotificationListCategory }',
)
assert.equal(operationResponseType(notificationUnread), 'NotificationUnread')

const notificationReadAll = operation('notifications.readAll')
assert.equal(
  notificationReadAll.requestBody.content['application/json'].schema['x-typescript-params'],
  'data?: { category?: NotificationListCategory }',
)

const notificationList = operation('notifications.list')
const notificationQuery = notificationList.parameters.find((item) => item.in === 'query').schema
assert.equal(
  notificationQuery['x-typescript-params'],
  'params?: { cursor?: string; limit?: number; unread_only?: boolean; type?: "social.followed" | "message" | "content.card_liked" | "content.comment_liked" | "library.saved" | "content.commented" | "content.replied" | "content.mentioned" | "system.notice" | "system.security" | "system.moderation"; category?: NotificationListCategory }',
)
const notificationResponse = notificationList.responses['200'].content['application/json'].schema.properties.data
assert.equal(notificationResponse['x-typescript-type'], 'NotificationCursorPage')
assert.deepEqual(
  notificationResponse.properties.data.items.required,
  ['id', 'type', 'category', 'actor_id', 'actor', 'actor_state', 'resource_type', 'resource_id', 'resource_state', 'action_url', 'payload', 'read_at', 'created_at'],
)
assert.equal(operationResponseType(notificationList), 'NotificationCursorPage')
const notificationListMethod = generatedResourceMethod({ method: 'list', params: notificationQuery['x-typescript-params'], httpMethod: 'GET', returnType: 'NotificationCursorPage', path: '/me/notifications' })
assert.match(notificationListMethod, /Promise<ApiTypes\.NotificationCursorPage>/)
assert.match(notificationListMethod, /category\?: ApiTypes\.NotificationListCategory/)
assert.doesNotMatch(notificationListMethod, /ApiTypes\.ApiTypes/)

const stream = operation('notifications.stream')
assert.equal(stream['x-response-shape'], 'raw')
assert.equal(stream.responses['200'].content['text/event-stream'].schema['x-typescript-type'], 'string')

const migrationRun = operation('operations.runMigrations')
assert.equal(migrationRun.requestBody.required, false)
assert.deepEqual(Object.keys(migrationRun.requestBody.content['application/json'].schema.properties ?? {}), [])
const migrationRunRoute = routeManifest.routes.find((item) => item.name === 'operations.runMigrations')
assert.equal(
  migrationRunRoute.contract.request.rejectTruthy.allow_contract,
  'Contract 迁移必须通过已激活且完成备份 Gate 的升级计划审批',
)

const adminOperations = [
  ['operations.overview', 'operations.read', null, 'item', 200],
  ['operations.migrations', 'operations.read', null, 'collection', 200],
  ['operations.runMigrations', 'operations.migrate', 'migrationRun', 'collection', 200],
  ['operations.runtime', 'operations.read', null, 'item', 200],
  ['operations.deliveries', 'communications.read', 'pagination', 'paginated', 200],
  ['operations.retryDelivery', 'operations.retry', null, 'void', 204],
  ['operations.notificationStats', 'communications.read', null, 'item', 200],
  ['operations.auditLogs', 'audit.read', 'pagination', 'paginated', 200],
  ['reports.listManaged', 'reports.read', 'pagination', 'paginated', 200],
  ['reports.getManaged', 'reports.read', null, 'item', 200],
  ['reports.transition', 'reports.manage', 'transition', 'item', 200],
  ['users.security', 'users.security.read', null, 'item', 200],
  ['users.sessions', 'users.security.read', null, 'collection', 200],
  ['users.revokeSession', 'users.sessions.revoke', null, 'void', 204],
  ['users.revokeSessions', 'users.sessions.revoke', null, 'void', 204],
  ['users.recovery', 'users.recovery.read', 'pagination', 'paginated', 200],
  ['community.insights', 'community.insights.read', null, 'item', 200],
  ['files.usage', 'files.usage.read', null, 'item', 200],
]

for (const [operationId, capability, scene, shape, status] of adminOperations) {
  const route = routeManifest.routes.find((item) => item.name === operationId)
  assert(route, `${operationId} must be exported in the route manifest`)
  assert.equal(route.meta.access.auth, 'required', `${operationId} must require authentication`)
  assert.deepEqual(route.meta.access.allOf, [
    { capability: 'admin.access', scope: 'global' },
    { capability, scope: 'global' },
  ], `${operationId} must fail closed on both admin access and its feature capability`)
  assert(route.middleware.some((item) => item.endsWith('JwtAuthCheck')), `${operationId} must use JwtAuthCheck`)
  assert(route.middleware.some((item) => item.endsWith('PermissionCheck')), `${operationId} must use PermissionCheck`)
  assert.equal(route.contract.response.shape, shape, `${operationId} response shape`)
  assert.equal(route.contract.response.status, status, `${operationId} response status`)
  if (shape === 'void') assert.equal(route.contract.response.resource, null, `${operationId} void response resource`)
  if (scene === null) assert.equal(route.contract.request, null, `${operationId} must not accept request input`)
  else assert.equal(route.contract.request.scene, scene, `${operationId} request validation scene`)
}

console.log('API contract workflow fixture tests passed.')
