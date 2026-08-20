function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

export function extractMethodBody(source, action) {
  const match = new RegExp(`\\bfunction\\s+${escapeRegExp(action)}\\s*\\(`).exec(source)
  if (!match) return null
  const open = source.indexOf('{', match.index + match[0].length)
  if (open < 0) return null

  let depth = 0
  let quote = null
  let lineComment = false
  let blockComment = false
  for (let index = open; index < source.length; index++) {
    const char = source[index]
    const next = source[index + 1]
    if (lineComment) {
      if (char === '\n') lineComment = false
      continue
    }
    if (blockComment) {
      if (char === '*' && next === '/') { blockComment = false; index++ }
      continue
    }
    if (quote) {
      if (char === '\\') { index++; continue }
      if (char === quote) quote = null
      continue
    }
    if (char === '/' && next === '/') { lineComment = true; index++; continue }
    if (char === '/' && next === '*') { blockComment = true; index++; continue }
    if (char === '#') { lineComment = true; continue }
    if (char === "'" || char === '"' || char === '`') { quote = char; continue }
    if (char === '{') depth++
    if (char === '}' && --depth === 0) return source.slice(match.index, index + 1)
  }
  return null
}

export function inspectControllerAction(source, action, fallbacks = []) {
  let body = extractMethodBody(source, action)
  if (!body) {
    for (const fallback of fallbacks) {
      body = extractMethodBody(fallback, action)
      if (body) break
    }
  }
  if (!body) return { missingAction: true, body: '' }

  const validatedVariables = [...body.matchAll(/\$(\w+)\s*=\s*\$this->validated\s*\(/g)].map((match) => match[1])
  const accessedFields = []
  for (const variable of validatedVariables) {
    const pattern = new RegExp(`\\$${escapeRegExp(variable)}\\s*\\[\\s*['\"]([^'\"]+)['\"]\\s*\\]`, 'g')
    const matches = [...body.matchAll(pattern)]
    const localFields = new Set()
    const seenFields = new Set()
    for (const match of matches) {
      const field = match[1]
      if (seenFields.has(field)) continue
      seenFields.add(field)
      const suffix = body.slice((match.index || 0) + match[0].length)
      if (/^\s*(?:\?\?=|[+\-*\/.%&|^]?=)(?!=)/.test(suffix)) localFields.add(field)
    }
    accessedFields.push(...matches.map((match) => match[1]).filter((field) => !localFields.has(field)))
  }
  const directInputPattern = /(?:\bRequest::(?:param|input|post|get|put|delete)|\brequest\(\)->(?:param|input|post|get|put|delete)|\binput)\s*\(/g
  const directInputKeys = [...body.matchAll(/(?:Request::(?:param|input|post|get|put|delete)|request\(\)->(?:param|input|post|get|put|delete))\(\s*['"]([^'"]+)['"]/g)].map((match) => match[1])
  const fileKeys = [...body.matchAll(/request\(\)->file\(\s*['"]([^'"]+)['"]/g)].map((match) => match[1])

  return {
    missingAction: false,
    body,
    usesValidated: /\$this->validated\s*\(/.test(body),
    usesLegacyValidation: /\$this->(?:param|paramIndex)\s*\(/.test(body),
    usesDirectInput: directInputPattern.test(body),
    directInputKeys: [...new Set(directInputKeys)],
    fileKeys: [...new Set(fileKeys)],
    accessedFields: [...new Set(accessedFields)],
    usesContractResponse: /\$this->respond\s*\(/.test(body),
    usesRawResponse: /\breturn\s+response\s*\(/.test(body),
  }
}

function arrayValue(value) {
  return Array.isArray(value) ? value : []
}

function issue(severity, code, message) {
  return { severity, code, message }
}

export function describeRequestContract(route, metadata) {
  const pathFields = new Set([...String(route.path || '').matchAll(/:([^/]+)/g)].map((match) => match[1]))
  const request = route.contract?.request
  if (request == null) return { present: false, pathFields, runtimeFields: new Set(), wireFields: new Set(), required: new Set(), dynamic: false, issues: [] }

  const issues = []
  const validator = request.validate
  const sceneName = request.scene
  if (typeof validator !== 'string' || !validator || typeof sceneName !== 'string' || !sceneName) {
    issues.push(issue('P0', 'INVALID_REQUEST_CONTRACT', 'request contract must declare validate and scene'))
    return { present: true, pathFields, runtimeFields: new Set(), wireFields: new Set(), required: new Set(), dynamic: false, issues }
  }
  const details = metadata.validators?.[validator]
  if (!details) {
    issues.push(issue('P0', 'UNKNOWN_REQUEST_VALIDATOR', `validator metadata is unavailable: ${validator}`))
    return { present: true, pathFields, runtimeFields: new Set(), wireFields: new Set(), required: new Set(), dynamic: false, issues }
  }
  const scene = details.allScenes?.[sceneName]
  if (!scene || typeof scene !== 'object' || Array.isArray(scene)) {
    issues.push(issue('P0', 'UNKNOWN_VALIDATOR_SCENE', `validator scene is unavailable: ${validator}::${sceneName}`))
    return { present: true, pathFields, runtimeFields: new Set(), wireFields: new Set(), required: new Set(), dynamic: false, issues }
  }

  const runtimeFields = new Set(['normal', 'require', 'nonNull', 'toNull'].flatMap((key) => arrayValue(scene[key])))
  const runtimeRequired = new Set(arrayValue(scene.require))
  const rules = details.rules || {}
  for (const field of runtimeFields) {
    if (!(field in rules)) issues.push(issue('P1', 'SCENE_FIELD_WITHOUT_RULE', `${validator}::${sceneName} exposes ${field} without a validation rule`))
  }

  const schema = details.requestSchemas?.[sceneName] || null
  const schemaFields = new Set(Object.keys(schema?.properties || {}))
  const dynamic = Boolean(schema && schema.additionalProperties && schemaFields.size === 0)
  const runtimeWireFields = new Set([...runtimeFields].filter((field) => !pathFields.has(field)))
  const runtimeWireRequired = new Set([...runtimeRequired].filter((field) => !pathFields.has(field)))
  if (schema && !dynamic) {
    for (const field of runtimeWireFields) {
      if (!schemaFields.has(field)) issues.push(issue('P1', 'RUNTIME_FIELD_MISSING_FROM_SCHEMA', `${validator}::${sceneName} runtime field is absent from requestSchemas: ${field}`))
    }
    for (const field of schemaFields) {
      if (!runtimeWireFields.has(field)) issues.push(issue('P1', 'SCHEMA_FIELD_MISSING_FROM_SCENE', `${validator}::${sceneName} schema field is absent from runtime scene: ${field}`))
    }
    const schemaRequired = new Set(arrayValue(schema.required))
    for (const field of runtimeWireRequired) {
      if (!schemaRequired.has(field)) issues.push(issue('P1', 'REQUIRED_FIELD_SCHEMA_MISMATCH', `${validator}::${sceneName} required runtime field is optional in schema: ${field}`))
    }
    for (const field of schemaRequired) {
      if (!runtimeWireRequired.has(field)) issues.push(issue('P1', 'REQUIRED_FIELD_SCENE_MISMATCH', `${validator}::${sceneName} schema requires a field that runtime does not require: ${field}`))
    }
  }

  return {
    present: true,
    validator,
    sceneName,
    pathFields,
    runtimeFields,
    wireFields: schema ? schemaFields : runtimeWireFields,
    required: schema ? new Set(arrayValue(schema.required)) : runtimeWireRequired,
    dynamic,
    issues,
  }
}

export function auditRequestContract(route, inspection, metadata) {
  const descriptor = describeRequestContract(route, metadata)
  const issues = [...descriptor.issues]
  if (inspection.missingAction) {
    issues.push(issue('P0', 'MISSING_CONTROLLER_ACTION', `controller action cannot be resolved: ${route.controller}/${route.action}`))
    return issues
  }

  const readsInput = inspection.usesValidated || inspection.usesLegacyValidation || inspection.usesDirectInput || inspection.fileKeys.length > 0
  if (!descriptor.present && readsInput) {
    issues.push(issue('P1', 'REQUEST_INPUT_WITHOUT_CONTRACT', 'controller reads request input but route contract declares request => null'))
  }
  if (descriptor.present && !inspection.usesValidated) {
    issues.push(issue('P1', 'REQUEST_CONTRACT_NOT_EXECUTED', 'route declares a request contract but controller does not execute validated()'))
  }
  if (inspection.usesLegacyValidation) {
    issues.push(issue('P1', 'LEGACY_CONTROLLER_VALIDATION', 'controller bypasses the route-owned validated() contract'))
  }
  if (inspection.usesDirectInput) {
    issues.push(issue('P1', 'DIRECT_REQUEST_INPUT', `controller reads request parameters directly${inspection.directInputKeys.length ? `: ${inspection.directInputKeys.join(', ')}` : ''}`))
  }
  if (descriptor.present && !descriptor.dynamic) {
    for (const field of inspection.accessedFields) {
      if (!descriptor.runtimeFields.has(field) && !descriptor.pathFields.has(field)) {
        issues.push(issue('P1', 'CONTROLLER_FIELD_NOT_IN_CONTRACT', `controller reads a field absent from the validator scene: ${field}`))
      }
    }
    for (const field of inspection.fileKeys) {
      if (!descriptor.runtimeFields.has(field)) {
        issues.push(issue('P1', 'FILE_FIELD_NOT_IN_CONTRACT', `controller reads an upload field absent from the validator scene: ${field}`))
      }
    }
  }
  if (inspection.fileKeys.length > 0 && route.contract?.request?.contentType !== 'multipart/form-data') {
    issues.push(issue('P1', 'FILE_UPLOAD_WITHOUT_MULTIPART_CONTRACT', 'controller reads an upload file but the request contract is not multipart/form-data'))
  }
  return issues
}

export function hasBlockingIssues(summary) {
  return (summary.P0 || 0) > 0 || (summary.P1 || 0) > 0
}
