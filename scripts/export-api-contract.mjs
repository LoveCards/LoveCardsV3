import fs from 'node:fs/promises'
import path from 'node:path'
import { spawnSync } from 'node:child_process'

const root = process.cwd()
const inventoryPath = path.join(root, 'contracts/api-inventory.json')
const outputPath = path.join(root, 'contracts/openapi.json')
const checkOnly = process.argv.includes('--check')

const inventory = JSON.parse(await fs.readFile(inventoryPath, 'utf8'))
const blocking = Object.entries(inventory.summary || {}).filter(([severity, count]) => ['P0', 'P1'].includes(severity) && count > 0)
if (blocking.length) {
  throw new Error(`API audit has blocking findings: ${blocking.map(([severity, count]) => `${severity}=${count}`).join(', ')}`)
}

function loadBackendMetadata() {
  const candidates = [process.env.PHP_BINARY, 'php', 'C:\\software\\phpstudy_pro\\Extensions\\php\\php8.0.2nts\\php.exe'].filter(Boolean)
  for (const binary of candidates) {
    const result = spawnSync(binary, [path.join(root, 'scripts/export-contract-metadata.php')], { cwd: root, encoding: 'utf8' })
    if (result.status === 0 && result.stdout.trim()) {
      try { return JSON.parse(result.stdout) } catch {}
    }
  }
  return { resources: {}, validators: {} }
}

const backendMetadata = loadBackendMetadata()

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
    const rules = details.rules || {}
    const scene = route.validatorScene && details.allScenes?.[route.validatorScene]
    const normal = Array.isArray(scene?.normal) ? scene.normal : []
    const requireFields = Array.isArray(scene?.require) ? scene.require : []
    const toNull = Array.isArray(scene?.toNull) ? scene.toNull : []
    const fields = scene
      ? [...new Set([...normal, ...requireFields, ...toNull])]
      : Object.keys(rules)
    for (const field of fields) {
      properties[field] = fieldSchema(rules[field])
      if (requireFields.includes(field)) required.push(field)
    }
  }
  if (route.action === 'upload') properties.file = { type: 'string', format: 'binary' }
  if (!Object.keys(properties).length) return null
  return { type: 'object', properties, ...(required.length ? { required: [...new Set(required)] } : {}), additionalProperties: true }
}

function operationSchema(route) {
  const sdkType = route.response?.sdkReturnType || route.sdk?.returnType || 'unknown'
  const resourceSchema = Object.values(backendMetadata.resources || {}).find((item) => item.shortName === route.response?.resource)?.schema
  let schema = resourceSchema && resourceSchema['x-runtime-schema'] !== true ? resourceSchema : schemaForType(sdkType)
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
  const params = route.sdk?.params || ''
  const formData = /FormData/.test(params) || route.action === 'upload'
  const schema = validationSchema(route) || { type: 'object', additionalProperties: true }
  schema['x-typescript-params'] = params || null
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
    'x-capabilities': route.meta.caps || [],
    'x-public': Boolean(route.meta.public),
    'x-middleware': route.middleware,
    'x-resource': route.response?.resource || null,
    'x-response-shape': route.response?.shape || 'dynamic',
    'x-validators': route.validators || [],
    'x-validator-scene': route.validatorScene || null,
    'x-validation-rules': Object.fromEntries((route.validators || []).map((validator) => [validator, backendMetadata.validators?.[validator]?.rules || null])),
    responses: {},
  }

  if (route.middleware.includes('JwtAuthCheck')) operation.security = [{ bearerAuth: [] }]
  else if (route.meta.public) operation.security = []

  const responseStatus = String(route.response?.status || 200)
  if (responseStatus === '204') {
    operation.responses[responseStatus] = { description: 'No content' }
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
  if (['GET', 'DELETE'].includes(route.method)) {
    operation.parameters.push({ name: 'query', in: 'query', required: false, schema: requestSchema(route) })
  } else {
    operation.requestBody = {
      required: route.action === 'upload',
      content: {
        [route.action === 'upload' ? 'multipart/form-data' : 'application/json']: { schema: requestSchema(route) },
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
