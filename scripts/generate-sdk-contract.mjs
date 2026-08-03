import fs from 'node:fs/promises'
import path from 'node:path'

const root = process.cwd()
const contract = JSON.parse(await fs.readFile(path.join(root, 'contracts/openapi.json'), 'utf8'))
const outputDir = path.join(root, 'packages/sdk/src/generated')
const outputPath = path.join(outputDir, 'api-contract.ts')
const resourceOutputDir = path.join(outputDir, 'resources')
const checkOnly = process.argv.includes('--check')

function quote(value) { return JSON.stringify(value) }

function tsType(schema, fallback = 'unknown') {
  if (!schema) return fallback
  if (schema['x-typescript-type']) return schema['x-typescript-type']
  if (schema.const === true) return 'true'
  if (schema.type === 'string') return 'string'
  if (schema.type === 'integer' || schema.type === 'number') return 'number'
  if (schema.type === 'boolean') return 'boolean'
  if (schema.type === 'array') return `${tsType(schema.items)}[]`
  if (schema.type === 'object') return 'Record<string, unknown>'
  return fallback
}

const qualifiedTypeNames = 'ListResult CreateResult Card Comment Tag User Role Like LCFile ThemeItem ThemeConfigData ThemeActivateParams ThemeDeleteParams ConfigData ConfigRegisterResult CaptchaDriver CaptchaMeta CaptchaConfig StorageDriver StorageMeta StorageChannel ChannelStats SenderType SenderMeta SenderChannel SenderTemplate SystemUpdateInfo DashboardData DirectUploadResult UploadResult CapabilityItem CheckResult LoginResult EmailCaptchaResult CardsListParams CreateCardParams UpdateCardParams CreateCommentParams BatchOperateParams PaginationParams ConfigRegisterParams ConfigUpdateParams FilesBatchOperateParams AssignCapabilitiesParams CreateRoleParams ReseedResult UpdateRoleParams CaptchaSendParams LoginParams RegisterParams EmailParams EmailCaptchaParams PasswordParams ProfileUpdateParams AdminUserUpdateParams ListParams'.split(' ')

function qualifyType(type) {
  return type.replace(new RegExp(`\\b(${qualifiedTypeNames.join('|')})\\b`, 'g'), 'ApiTypes.$1')
}

function splitParams(value) {
  if (!value.trim()) return []
  const result = []
  let current = ''
  let depth = 0
  for (const char of value) {
    if ('<{[('.includes(char)) depth++
    if ('>}])'.includes(char)) depth--
    if (char === ',' && depth === 0) {
      result.push(current.trim())
      current = ''
    } else current += char
  }
  if (current.trim()) result.push(current.trim())
  return result.map((param) => {
    const match = param.match(/^([A-Za-z_$][\w$]*\??)\s*:\s*(.+)$/)
    return match ? { name: match[1].replace('?', ''), declaration: `${match[1]}: ${qualifyType(match[2])}` } : { name: param, declaration: param }
  }).filter((param) => !/^\w+\??:\s*\{\s*\}$/.test(param.declaration))
}

function className(resource) { return resource.charAt(0).toUpperCase() + resource.slice(1) }

function generatedResourceMethod(method) {
  const params = splitParams(method.params)
  const pathParams = [...method.path.matchAll(/:([A-Za-z_]\w*)/g)].map((match, index) => params[index]?.name || match[1] || `param${index + 1}`)
  let pathIndex = 0
  const url = method.path.replaceAll(/:[A-Za-z_]\w*/g, () => `\${${pathParams[pathIndex++]}}`)
  const urlLiteral = pathParams.length ? `\`${url}\`` : JSON.stringify(method.path)
  const remaining = params.slice(pathParams.length)
  const returnType = qualifyType(method.returnType)
  const signature = params.map((param) => param.declaration).join(', ')
  const queryOrBody = remaining.at(-1)?.name
  let call
  if (method.httpMethod === 'GET') call = `this._get<${returnType}>(${urlLiteral}${queryOrBody ? `, ${queryOrBody}` : ''})`
  else if (method.httpMethod === 'DELETE') call = `this._delete<${returnType}>(${urlLiteral}${queryOrBody ? `, ${queryOrBody}` : ''})`
  else if (queryOrBody && /FormData/.test(remaining.at(-1)?.declaration || '')) call = `this._${method.httpMethod.toLowerCase()}<${returnType}>(${urlLiteral}, ${queryOrBody}, { headers: { 'Content-Type': 'multipart/form-data' } })`
  else if (queryOrBody) call = `this._${method.httpMethod.toLowerCase()}<${returnType}>(${urlLiteral}, ${queryOrBody})`
  else call = `this._${method.httpMethod.toLowerCase()}<${returnType}>(${urlLiteral})`
  return `  ${method.method}(${signature}): Promise<${returnType}> {\n    return ${call}\n  }`
}

const entries = []
for (const [route, methods] of Object.entries(contract.paths)) {
  for (const [method, operation] of Object.entries(methods)) {
    if (method === 'parameters') continue
    const success = Object.entries(operation.responses).find(([status]) => status !== 'default')?.[1]
    const status = Object.entries(operation.responses).find(([code]) => code !== 'default')?.[0] || '200'
    const content = success?.content?.['application/json']?.schema
    const responseSchema = content?.properties?.data
    const requestBody = operation.requestBody?.content
    const requestSchema = requestBody ? Object.values(requestBody)[0]?.schema : operation.parameters?.find((item) => item.in === 'query')?.schema
    entries.push({
      operationId: operation.operationId,
      method: method.toUpperCase(),
      path: route,
      status: Number(status),
      request: requestSchema?.['x-typescript-params'] || null,
      response: tsType(responseSchema, status === '204' ? 'void' : 'unknown'),
      shape: operation['x-response-shape'] || 'dynamic',
      capabilities: operation['x-capabilities'] || [],
      public: Boolean(operation['x-public']),
    })
  }
}
entries.sort((a, b) => a.operationId.localeCompare(b.operationId))

const lines = [
  "import type * as ApiTypes from '../types'",
  '',
  '/** Generated from contracts/openapi.json. Do not edit manually. */',
  'export interface GeneratedApiContract {',
]
for (const entry of entries) {
  const requestType = entry.request ? qualifyType(entry.request.match(/(?:data|params)\??:\s*(.+)$/)?.[1] || 'Record<string, unknown>') : 'void'
  const responseType = qualifyType(entry.response)
  lines.push(`  ${JSON.stringify(entry.operationId)}: { method: ${JSON.stringify(entry.method)}; path: ${JSON.stringify(entry.path)}; request: ${requestType}; response: ${responseType}; status: ${entry.status}; shape: ${JSON.stringify(entry.shape)}; public: ${entry.public}; capabilities: readonly string[] }`)
}
lines.push('}', '')
lines.push('export type GeneratedOperationId = keyof GeneratedApiContract')
lines.push('export type GeneratedRequest<K extends GeneratedOperationId> = GeneratedApiContract[K][\'request\']')
lines.push('export type GeneratedResponse<K extends GeneratedOperationId> = GeneratedApiContract[K][\'response\']')
lines.push('')
lines.push('export const generatedApiContract = {')
for (const entry of entries) {
  lines.push(`  ${JSON.stringify(entry.operationId)}: { method: ${JSON.stringify(entry.method)}, path: ${JSON.stringify(entry.path)}, status: ${entry.status}, shape: ${JSON.stringify(entry.shape)}, public: ${entry.public}, capabilities: ${JSON.stringify(entry.capabilities)} },`)
}
lines.push('} as const satisfies Record<GeneratedOperationId, { method: string; path: string; status: number; shape: string; public: boolean; capabilities: readonly string[] }>')
lines.push('')

await fs.mkdir(outputDir, { recursive: true })
const outputs = new Map([[outputPath, `${lines.join('\n')}\n`]])

const methodsByResource = new Map()
for (const [route, operations] of Object.entries(contract.paths)) {
  for (const [httpMethod, operation] of Object.entries(operations)) {
    if (httpMethod === 'parameters') continue
    const parts = String(operation.operationId || '').split('.')
    const resource = className(parts[0] || 'Api')
    let methodName = parts[1] || operation.operationId
    if (parts.length > 2 && parts[1] === 'me') {
      methodName = ({ get: 'me', update: 'updateMe', password: 'updatePassword', email: 'updateEmail', emailCaptcha: 'emailCaptcha' })[parts[2]] || parts[2]
    } else if (parts.length > 2) {
      methodName = parts.slice(1).join('_')
    }
    const pathValue = route.replaceAll(/\{[^}]+\}/g, (value) => `:${value.slice(1, -1)}`)
    const response = Object.entries(operation.responses || {}).find(([status]) => status !== 'default')?.[1]
    const responseSchema = response?.content?.['application/json']?.schema?.properties?.data
    let returnType = tsType(responseSchema, String(operation.responses?.['204'] ? 'void' : 'unknown'))
    if (operation.operationId === 'theme.publicConfig') returnType = 'ThemeConfigData'
    const bodySchema = operation.requestBody ? Object.values(operation.requestBody.content || {})[0]?.schema : operation.parameters?.find((item) => item.in === 'query')?.schema
    const params = bodySchema?.['x-typescript-params'] || null
    const pathParams = [...route.matchAll(/(?:\{([^}]+)\}|:([A-Za-z_]\w*))/g)].map((match) => {
      const name = match[1] || match[2]
      return `${name}: ${name === 'id' ? 'number' : 'string'}`
    })
    const signature = [...pathParams, ...(params ? splitParams(params).map((item) => item.declaration) : [])].join(', ')
    const method = { method: methodName, params: signature, httpMethod: httpMethod.toUpperCase(), returnType, path: pathValue }
    if (!methodsByResource.has(resource)) methodsByResource.set(resource, [])
    methodsByResource.get(resource).push(method)
  }
}
await fs.mkdir(resourceOutputDir, { recursive: true })
for (const [resource, methods] of methodsByResource) {
  const generatedMethods = [...methods]
  if (resource === 'Theme') {
    const publicConfig = methods.find((method) => method.method === 'publicConfig')
    if (publicConfig) generatedMethods.push({ ...publicConfig, method: 'config' })
    generatedMethods.push({ method: 'tags', params: '', httpMethod: 'GET', returnType: 'Tag[]', path: '/tags' })
  }
  if (resource === 'Roles') {
    const capabilities = methods.find((method) => method.method === 'getRoleCapabilities')
    if (capabilities) generatedMethods.push({ ...capabilities, method: 'getCapabilities' })
  }
  const listOwn = methods.find((method) => method.method === 'listOwn')
  if (listOwn && !methods.some((method) => method.method === 'listMe')) generatedMethods.push({ ...listOwn, method: 'listMe', aliasOf: 'listOwn' })
  const resourceLines = [
    "import { BaseResource } from '../../resources/base'",
    "import type * as ApiTypes from '../../types'",
    '',
    '/** Generated from contracts/openapi.json. Do not edit manually. */',
    `export class ${className(resource)} extends BaseResource {`,
    ...generatedMethods.map((method) => method.aliasOf
      ? `  ${method.method}(${splitParams(method.params).map((param) => param.declaration).join(', ')}): Promise<${qualifyType(method.returnType)}> {\n    return this.${method.aliasOf}(${splitParams(method.params).map((param) => param.name).join(', ')})\n  }`
      : generatedResourceMethod(method)),
    '}',
    '',
  ]
  outputs.set(path.join(resourceOutputDir, `${resource}.ts`), resourceLines.join('\n'))
}

if (checkOnly) {
  const failures = []
  for (const [file, expected] of outputs) {
    let current = ''
    try { current = await fs.readFile(file, 'utf8') } catch { current = '' }
    if (current !== expected) failures.push(path.relative(root, file))
  }
  if (failures.length) {
    console.error(`Generated SDK files are stale: ${failures.join(', ')}`)
    process.exitCode = 1
  } else {
    console.log(`Generated SDK files are up to date (${outputs.size} files).`)
  }
} else {
  await fs.mkdir(resourceOutputDir, { recursive: true })
  for (const [file, content] of outputs) await fs.writeFile(file, content)
  console.log(`Generated SDK contract for ${entries.length} endpoints and ${methodsByResource.size} resources`)
}
