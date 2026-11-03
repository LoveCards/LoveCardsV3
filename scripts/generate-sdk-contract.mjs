import fs from 'node:fs/promises'
import path from 'node:path'
import { className, generatedResourceMethod, operationResponseType, qualifyType, resourceFileName, splitParams } from './lib/sdk-codegen.mjs'

const root = process.cwd()
const contract = JSON.parse(await fs.readFile(path.join(root, 'contracts/openapi.json'), 'utf8'))
const outputDir = path.join(root, 'packages/sdk/src/generated')
const outputPath = path.join(outputDir, 'api-contract.ts')
const resourceOutputDir = path.join(outputDir, 'resources')
const checkOnly = process.argv.includes('--check')

function quote(value) { return JSON.stringify(value) }

const entries = []
for (const [route, methods] of Object.entries(contract.paths)) {
  for (const [method, operation] of Object.entries(methods)) {
    if (method === 'parameters') continue
    const status = Object.entries(operation.responses).find(([code]) => code !== 'default')?.[0] || '200'
    const requestBody = operation.requestBody?.content
    const requestSchema = requestBody ? Object.values(requestBody)[0]?.schema : operation.parameters?.find((item) => item.in === 'query')?.schema
    entries.push({
      operationId: operation.operationId,
      method: method.toUpperCase(),
      path: route,
      status: Number(status),
      request: requestSchema?.['x-typescript-params'] || null,
      response: operationResponseType(operation, status === '204' ? 'void' : 'unknown'),
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
  const requestType = entry.request ? qualifyType(entry.request.match(/(?:data|params|formData)\??:\s*(.+)$/)?.[1] || 'Record<string, unknown>') : 'void'
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
    // Keep the public-card SDK verb aligned with the established list* API
    // naming, while retaining the concise route operation id in contracts.
    if (operation.operationId === 'cards.publicByUser') methodName = 'listPublicByUser'
    if (parts.length > 2 && parts[1] === 'me') {
      methodName = ({ get: 'me', update: 'updateMe', password: 'updatePassword', email: 'updateEmail', emailCaptcha: 'emailCaptcha' })[parts[2]] || parts[2]
    } else if (parts.length > 2) {
      methodName = parts.slice(1).join('_')
    }
    const pathValue = route.replaceAll(/\{[^}]+\}/g, (value) => `:${value.slice(1, -1)}`)
    let returnType = operationResponseType(operation, operation.responses?.['204'] ? 'void' : 'unknown')
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
  outputs.set(path.join(resourceOutputDir, resourceFileName(resource)), resourceLines.join('\n'))
}

async function normalizeResourceFileCasing(expectedNames) {
  for (const actual of await fs.readdir(resourceOutputDir)) {
    const expected = [...expectedNames].find((name) => name.toLowerCase() === actual.toLowerCase())
    if (!expected || actual === expected) continue
    const source = path.join(resourceOutputDir, actual)
    const temporary = path.join(resourceOutputDir, `.__lc_case_${Date.now()}_${actual}`)
    await fs.rename(source, temporary)
    await fs.rename(temporary, path.join(resourceOutputDir, expected))
  }
}

if (checkOnly) {
  const failures = []
  for (const [file, expected] of outputs) {
    let current = ''
    try { current = await fs.readFile(file, 'utf8') } catch { current = '' }
    if (current !== expected) failures.push(path.relative(root, file))
  }
  const expectedResources = new Set([...outputs.keys()].filter((file) => path.dirname(file) === resourceOutputDir).map((file) => path.basename(file)))
  const extraResources = (await fs.readdir(resourceOutputDir)).filter((file) => file.endsWith('.ts') && !expectedResources.has(file))
  failures.push(...extraResources.map((file) => path.relative(root, path.join(resourceOutputDir, file))))
  if (failures.length) {
    console.error(`Generated SDK files are stale: ${failures.join(', ')}`)
    process.exitCode = 1
  } else {
    console.log(`Generated SDK files are up to date (${outputs.size} files).`)
  }
} else {
  await fs.mkdir(resourceOutputDir, { recursive: true })
  const expectedResources = new Set([...outputs.keys()].filter((file) => path.dirname(file) === resourceOutputDir).map((file) => path.basename(file)))
  await normalizeResourceFileCasing(expectedResources)
  for (const [file, content] of outputs) await fs.writeFile(file, content)
  for (const file of await fs.readdir(resourceOutputDir)) {
    if (file.endsWith('.ts') && !expectedResources.has(file)) await fs.unlink(path.join(resourceOutputDir, file))
  }
  console.log(`Generated SDK contract for ${entries.length} endpoints and ${methodsByResource.size} resources`)
}
