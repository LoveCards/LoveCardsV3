import fs from 'node:fs/promises'
import path from 'node:path'
import { loadBackendContractMetadata } from './lib/backend-contract-metadata.mjs'

const root = process.cwd()
const inventoryPath = path.join(root, 'contracts/api-inventory.json')
const outputPath = path.join(root, 'contracts/openapi.json')
const checkOnly = process.argv.includes('--check')

const inventory = JSON.parse(await fs.readFile(inventoryPath, 'utf8'))
const blocking = Object.entries(inventory.summary || {}).filter(([severity, count]) => ['P0', 'P1'].includes(severity) && count > 0)
if (blocking.length) {
  throw new Error(`API audit has blocking findings: ${blocking.map(([severity, count]) => `${severity}=${count}`).join(', ')}`)
}

const backendMetadata = loadBackendContractMetadata(root)

function cloneSchema(schema) {
  return schema == null ? schema : structuredClone(schema)
}

function schemaForType(type) {
  const value = (type || '').trim()
  if (!value || value === 'void') return null
  if (value === 'string') return { type: 'string' }
  if (value === 'number') return { type: 'number' }
  if (value === 'boolean') return { type: 'boolean' }
  if (value === 'any' || value === 'unknown') return { type: 'object', additionalProperties: true }
  if (value.endsWith('[]')) return { type: 'array', items: schemaForType(value.slice(0, -2)) || {} }
  const list = value.match(/^ListResult<(.+)>$/)
  if (list) return {
    type: 'object',
    properties: {
      data: { type: 'array', items: schemaForType(list[1]) || {} },
      pagination: { $ref: '#/components/schemas/PaginationInfo' },
    },
    required: ['data'],
  }
  if (value.startsWith('{') && value.endsWith('}')) return { type: 'object', additionalProperties: true }
  return { type: 'object', additionalProperties: true }
}

function tsTypeForSchema(schema) {
  if (!schema) return 'unknown'
  if (schema.type === 'integer' || schema.type === 'number') return 'number'
  if (schema.type === 'boolean') return 'boolean'
  if (schema.type === 'string') return schema.format === 'binary' ? 'File' : 'string'
  if (schema.type === 'array') return `${tsTypeForSchema(schema.items)}[]`
  if (schema.type === 'object') {
    const fields = Object.entries(schema.properties || {}).map(([name, value]) => `${name}${(schema.required || []).includes(name) ? '' : '?'}: ${tsTypeForSchema(value)}`)
    return fields.length ? `{ ${fields.join('; ')} }` : 'Record<string, unknown>'
  }
  return 'unknown'
}

function resourceType(route) {
  if (route.response?.shape === 'raw') return route.response?.type || 'string'
  const operation = route.name || ''
  const operationTypes = {
    'files.upload': 'UploadResult', 'files.cleanup': '{ cleaned: number }',
    'captcha.types': 'CaptchaDriver[]', 'captcha.drivers': 'CaptchaDriver[]', 'captcha.meta': 'CaptchaDriver', 'captcha.config': 'CaptchaConfig', 'captcha.challenge': 'CaptchaChallenge',
    'sender.types': 'SenderType[]', 'sender.meta': 'SenderMeta', 'sender.channels': 'SenderChannel[]', 'sender.templates': 'SenderTemplate[]', 'sender.testChannel': '{ success: boolean; message?: string }',
    'storage.types': 'StorageDriver[]', 'storage.meta': 'StorageMeta', 'storage.channels': 'StorageChannel[]', 'storage.channelStats': 'ChannelStats', 'storage.testChannel': '{ success: boolean; message?: string }',
    'theme.publicConfig': 'ThemeConfigData', 'theme.updateConfig': 'void', 'roles.getGrants': 'Grant[]', 'permissions.all': 'CapabilityItem[]',
    'session.policy': 'AuthPolicy', 'session.check': 'SessionState',
  }
  if (operationTypes[operation]) return operationTypes[operation]
  const short = route.response?.resource || ''
  const base = short.replace(/Resource$/, '')
  const map = { Card: 'Card', CardCreated: 'CreateResult', Comment: 'Comment', CommentCreated: 'CreateResult', Tag: 'Tag', User: 'User', Profile: 'User', Role: 'Role', Grant: 'Grant', Permission: 'CapabilityItem', Like: 'Like', LikeCount: '{ likes: number }', File: 'LCFile', DirectUpload: 'DirectUploadResult', Auth: 'LoginResult', AuthPolicy: 'AuthPolicy', Session: 'SessionState', Theme: 'ThemeItem', Config: 'ConfigData', Dashboard: 'DashboardData', System: 'SystemUpdateInfo', Sender: 'SenderMeta', Storage: 'StorageMeta', Captcha: 'CaptchaMeta', CaptchaChallenge: 'CaptchaChallenge', CursorPage: 'CursorPage', AdminOverview: 'OperationsOverview', AdminMigration: 'OperationsMigration', AdminRuntime: 'OperationsRuntimeStatus', AdminDelivery: 'OperationsDelivery', AdminAuditLog: 'OperationsAuditLog', AdminNotificationStats: 'OperationsNotificationStats', AdminCommunityInsights: 'OperationsCommunityInsights', AdminUpgradePlan: 'OperationsUpgradePlan', AdminReport: 'AdminReport', AdminSecurity: 'UserSecurityOverview', AdminSession: 'UserSession', AdminRecoveryChallenge: 'RecoveryChallenge', AdminFileUsage: 'FileUsage' }
  const item = map[base] || 'Record<string, unknown>'
  if (route.response?.shape === 'collection') return `${item}[]`
  if (route.response?.shape === 'paginated') return `ListResult<${item}>`
  if (route.response?.shape === 'void') return 'void'
  return item
}

function fieldSchema(rule) {
  const rules = Array.isArray(rule) ? rule : String(rule || '').split('|')
  const text = rules.join('|')
  const schema = { type: 'string' }
  if (/arrayJson|arrayOrIntJson|tagsLength|picturesLength/.test(text)) schema.type = 'array'
  else if (/integer|number|float|int/.test(text)) schema.type = 'number'
  else if (/boolean|stringBool/.test(text)) schema.type = 'boolean'
  if (/url/.test(text)) schema.format = 'uri'
  if (/email/.test(text)) schema.format = 'email'
  if (/file/.test(text)) schema.format = 'binary'
  const max = text.match(/max:(\d+)/)
  const min = text.match(/min:(\d+)/)
  if (max && schema.type === 'string') schema.maxLength = Number(max[1])
  if (min && schema.type === 'string') schema.minLength = Number(min[1])
  const enumRule = text.match(/in:([^|]+)/)
  if (enumRule) schema.enum = enumRule[1].split(',').map((value) => /^-?\d+$/.test(value) ? Number(value) : value)
  return schema
}

function validationSchema(route) {
  const properties = {}
  const required = []
  for (const validator of route.validators || []) {
    const details = backendMetadata.validators?.[validator]
    if (!details) continue
    const explicitSchema = route.validatorScene ? details.requestSchemas?.[route.validatorScene] : null
    if (explicitSchema) return cloneSchema(explicitSchema)
    const rules = details.rules || {}
    const scene = route.validatorScene && details.allScenes?.[route.validatorScene]
    const normal = Array.isArray(scene?.normal) ? scene.normal : []
    const requireFields = Array.isArray(scene?.require) ? scene.require : []
    const toNull = Array.isArray(scene?.toNull) ? scene.toNull : []
    const fields = scene
      ? [...new Set([...normal, ...requireFields, ...toNull])]
      : Object.keys(rules)
    for (const field of fields) {
      properties[field] = route.contract?.request?.contentType === 'multipart/form-data' && field === 'file'
        ? { type: 'string', format: 'binary' }
        : fieldSchema(rules[field])
      if (requireFields.includes(field)) required.push(field)
    }
  }
  if (!Object.keys(properties).length) return null
  return { type: 'object', properties, ...(required.length ? { required: [...new Set(required)] } : {}), additionalProperties: true }
}

function operationSchema(route) {
  const sdkType = resourceType(route)
  if (route.response?.shape === 'raw') {
    const schema = schemaForType(sdkType) || { type: 'string' }
    schema['x-typescript-type'] = sdkType
    return schema
  }
  const resourceSchema = cloneSchema(Object.values(backendMetadata.resources || {}).find((item) => item.shortName === route.response?.resource)?.schema)
  const explicit = new Set(['files.upload', 'files.cleanup', 'captcha.types', 'captcha.drivers', 'captcha.meta', 'captcha.config', 'sender.types', 'sender.meta', 'sender.channels', 'sender.templates', 'sender.testChannel', 'storage.types', 'storage.meta', 'storage.channels', 'storage.channelStats', 'storage.testChannel', 'theme.updateConfig', 'roles.getGrants', 'permissions.all'])
  let schema = explicit.has(route.name) ? schemaForType(sdkType) : (resourceSchema && resourceSchema['x-runtime-schema'] !== true ? resourceSchema : schemaForType(sdkType))
  if (route.response?.shape === 'collection') schema = { type: 'array', items: schema || {} }
  if (route.response?.shape === 'paginated') {
    const itemType = sdkType.match(/^ListResult<(.+)>$/)?.[1]
    schema = { type: 'array', items: resourceSchema && resourceSchema['x-runtime-schema'] !== true ? resourceSchema : schemaForType(itemType) || {} }
  }
  if (schema) schema['x-typescript-type'] = sdkType
  return schema
}

function requestSchema(route) {
  const pathParams = [...route.path.matchAll(/:([^/]+)/g)].map((match) => match[1])
  const formData = route.contract?.request?.contentType === 'multipart/form-data'
  const schema = cloneSchema(validationSchema(route) || { type: 'object', additionalProperties: true })
  for (const name of pathParams) {
    delete schema.properties?.[name]
    if (Array.isArray(schema.required)) schema.required = schema.required.filter((value) => value !== name)
  }
  const fields = Object.entries(schema.properties || {}).map(([name, value]) => `${name}${(schema.required || []).includes(name) ? '' : '?'}: ${tsTypeForSchema(value)}`)
  const requestRequired = (schema.required || []).length > 0
  const params = formData
    ? 'formData: FormData'
    : ['GET', 'DELETE'].includes(route.method)
      ? `params${requestRequired ? '' : '?'}: { ${fields.join('; ')} }`
      : fields.length
        ? `data${requestRequired ? '' : '?'}: { ${fields.join('; ')} }`
        : 'data?: Record<string, unknown>'
  schema['x-typescript-params'] = params
  if (formData) schema['x-content-type'] = 'multipart/form-data'
  if (pathParams.length) schema['x-path-params'] = pathParams
  if (route.validators?.length) {
    schema['x-validators'] = route.validators
    schema['x-validator-scene'] = route.validatorScene
  }
  return schema
}

const paths = {}
for (const route of inventory.routes) {
  const operation = {
    operationId: route.name,
    tags: route.meta.group ? [route.meta.group] : [],
    summary: route.meta.name || route.name,
    description: route.meta.name || route.name,
    'x-controller': `${route.controller}/${route.action}`,
    'x-access': route.meta.access || null,
    'x-capabilities': [...(route.meta.access?.allOf || []), ...(route.meta.access?.anyOf || [])].map((grant) => grant.capability),
    'x-public': route.meta.access?.auth === 'public',
    'x-middleware': route.middleware,
    'x-resource': route.response?.resource || null,
    'x-response-shape': route.response?.shape || 'dynamic',
    'x-validators': route.validators || [],
    'x-validator-scene': route.validatorScene || null,
    'x-contract-source': route.contractSource || 'legacy',
    'x-validation-rules': Object.fromEntries((route.validators || []).map((validator) => [validator, backendMetadata.validators?.[validator]?.rules || null])),
    responses: {},
  }

  if (route.middleware.includes('JwtAuthCheck')) operation.security = [{ bearerAuth: [] }]
  else if (route.meta.access?.auth === 'public') operation.security = []

  const responseStatus = String(route.response?.status || 200)
  if (responseStatus === '204') {
    operation.responses[responseStatus] = { description: 'No content' }
  } else if (route.response?.shape === 'raw') {
    operation.responses[responseStatus] = {
      description: 'Success',
      content: {
        [route.response?.contentType || 'text/plain']: { schema: operationSchema(route) || { type: 'string' } },
      },
    }
  } else {
    const responseSchema = operationSchema(route)
    const envelopeSchema = {
      type: 'object',
      properties: {
        success: { const: true },
        message: { type: 'string' },
        timestamp: { type: 'string', format: 'date-time' },
        data: responseSchema || { type: 'object', additionalProperties: true },
      },
      required: ['success', 'data', 'timestamp'],
    }
    if (route.response?.shape === 'paginated') envelopeSchema.properties.pagination = { $ref: '#/components/schemas/PaginationInfo' }
    operation.responses[responseStatus] = {
      description: 'Success',
      content: { 'application/json': { schema: envelopeSchema } },
    }
  }
  operation.responses.default = {
    description: 'API error',
    content: { 'application/json': { schema: { $ref: '#/components/schemas/ErrorEnvelope' } } },
  }

  const pathParams = [...route.path.matchAll(/:([^/]+)/g)].map((match) => match[1])
  operation.parameters = pathParams.map((name) => ({
    name,
    in: 'path',
    required: true,
    schema: { type: name === 'id' ? 'integer' : 'string' },
  }))
  const hasRequestContract = route.contract?.request != null
  if (hasRequestContract && ['GET', 'DELETE'].includes(route.method)) {
    const schema = requestSchema(route)
    operation.parameters.push({ name: 'query', in: 'query', required: (schema.required || []).length > 0, schema })
  } else if (hasRequestContract) {
    const schema = requestSchema(route)
    operation.requestBody = {
      required: (schema.required || []).length > 0,
      content: {
        [route.contract?.request?.contentType || 'application/json']: { schema },
      },
    }
  }

  paths[route.path] ??= {}
  paths[route.path][route.method.toLowerCase()] = operation
}

const document = {
  openapi: '3.1.0',
  info: {
    title: 'LoveCards API',
    version: '1.0.0',
  },
  paths,
  components: {
    securitySchemes: { bearerAuth: { type: 'http', scheme: 'bearer' } },
    schemas: {
      PaginationInfo: {
        type: 'object',
        properties: {
          currentPage: { type: 'integer' },
          totalPages: { type: 'integer' },
          totalItems: { type: 'integer' },
          itemsPerPage: { type: 'integer' },
        },
        required: ['currentPage', 'totalPages', 'totalItems', 'itemsPerPage'],
      },
      ErrorEnvelope: {
        type: 'object',
        properties: {
          success: { const: false },
          error: {
            type: 'object',
            properties: {
              code: { type: 'integer' },
              message: { type: 'string' },
              details: {},
            },
            required: ['code', 'message'],
          },
          timestamp: { type: 'string', format: 'date-time' },
        },
        required: ['success', 'error', 'timestamp'],
      },
    },
  },
}

const output = `${JSON.stringify(document, null, 2)}\n`
if (checkOnly) {
  let current = ''
  try { current = await fs.readFile(outputPath, 'utf8') } catch {}
  if (current !== output) {
    console.error('API contract is stale. Run: npm run export:api')
    process.exitCode = 1
  } else {
    console.log('API contract is up to date.')
  }
} else {
  await fs.mkdir(path.dirname(outputPath), { recursive: true })
  await fs.writeFile(outputPath, output)
  console.log(`Exported ${inventory.routeCount} endpoints to contracts/openapi.json`)
}
