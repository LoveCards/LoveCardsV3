const qualifiedTypeNames = 'ListResult CursorPage CreateResult Card Comment Tag User CurrentUser PublicUserProfile SocialRelation CurrentUserSession PrivacyPreferences BlockedUserItem BlockedUserCursorPage MentionCandidate Role Grant AuthorizationScope Like LCFile ThemeItem ThemeConfigData ThemeActivateParams ThemeDeleteParams ConfigData ConfigRegisterResult CaptchaDriver CaptchaMeta CaptchaConfig CaptchaChallenge StorageDriver StorageMeta StorageChannel ChannelStats SenderType SenderMeta SenderChannel SenderTemplate SystemUpdateInfo DashboardData DirectUploadResult UploadResult CapabilityItem CheckResult LoginResult AuthPolicy SessionState CardsListParams CreateCardParams UpdateCardParams CreateCommentParams BatchOperateParams PaginationParams ConfigRegisterParams ConfigUpdateParams FilesBatchOperateParams AssignGrantsParams CreateRoleParams ReseedResult UpdateRoleParams CaptchaSendParams LoginParams RegisterParams EmailParams EmailCaptchaParams EmailCaptchaResult PasswordParams ProfileUpdateParams AdminUserUpdateParams ListParams NotificationType ConfigurableNotificationType SystemNotificationType NotificationCategory NotificationListCategory NotificationActorState NotificationResourceState NotificationActor NotificationItem NotificationCursorPage NotificationUnread NotificationPreferences NotificationPreferencesUpdate OperationsOverview OperationsMigration OperationsRuntimeStatus OperationsDelivery OperationsAuditLog OperationsNotificationStats OperationsCommunityInsights OperationsUpgradePlan AdminReport UserSecurityOverview UserSession RecoveryChallenge FileUsage'.split(' ')

qualifiedTypeNames.push('CardMediaAccess')
qualifiedTypeNames.push('RuntimeCommunicationSnapshot', 'RuntimeConfigValidation', 'RuntimeConfigUpdate')
qualifiedTypeNames.push('StreamTicket', 'CommunicationSettingsSnapshot')
qualifiedTypeNames.push('NotificationPolicyCatalog', 'NotificationPolicyApplyResult', 'NotificationPolicyUpdate', 'NotificationPolicyRule')
qualifiedTypeNames.push('RealtimeTicketVerification', 'RealtimePollSnapshot', 'RealtimePollQuery', 'RealtimeSignalMessage')

export function schemaType(schema, fallback = 'unknown') {
  if (!schema) return fallback
  if (schema['x-typescript-type']) return schema['x-typescript-type']
  if (schema.const === true) return 'true'
  if (schema.type === 'string') return 'string'
  if (schema.type === 'integer' || schema.type === 'number') return 'number'
  if (schema.type === 'boolean') return 'boolean'
  if (schema.type === 'array') return `${schemaType(schema.items)}[]`
  if (schema.type === 'object') return 'Record<string, unknown>'
  return fallback
}

export function operationResponseSchema(operation) {
  const response = Object.entries(operation.responses || {}).find(([status]) => status !== 'default')?.[1]
  const content = response?.content || {}
  if (operation['x-response-shape'] === 'raw') {
    return Object.values(content)[0]?.schema
  }
  return content['application/json']?.schema?.properties?.data
}

export function operationResponseType(operation, fallback = 'unknown') {
  return schemaType(operationResponseSchema(operation), fallback)
}

export function qualifyType(type) {
  return type.replace(new RegExp(`\\b(${qualifiedTypeNames.join('|')})\\b`, 'g'), (match, _name, offset, source) => {
    return source.slice(Math.max(0, offset - 9), offset) === 'ApiTypes.' ? match : `ApiTypes.${match}`
  })
}

export function splitParams(value) {
  if (!value.trim()) return []
  const result = []
  let current = ''
  let depth = 0
  for (const char of value) {
    if ('<{[('.includes(char)) depth++
    if ('>}])'.includes(char)) depth--
    if (char === ',' && depth === 0) { result.push(current.trim()); current = '' } else current += char
  }
  if (current.trim()) result.push(current.trim())
  return result.map((param) => {
    const match = param.match(/^([A-Za-z_$][\w$]*\??)\s*:\s*(.+)$/)
    return match ? { name: match[1].replace('?', ''), declaration: `${match[1]}: ${qualifyType(match[2])}` } : { name: param, declaration: param }
  }).filter((param) => !/^\w+\??:\s*\{\s*\}$/.test(param.declaration))
}

export function className(resource) {
  return resource.charAt(0).toUpperCase() + resource.slice(1)
}

export function resourceFileName(resource) {
  return `${resource.toLowerCase()}.ts`
}

export function generatedResourceMethod(method) {
  const params = splitParams(method.params)
  const pathParams = [...method.path.matchAll(/:([A-Za-z_]\w*)/g)].map((match, index) => params[index]?.name || match[1] || `param${index + 1}`)
  const pathParamExpression = (name) => {
    const declaration = params.find((param) => param.name === name)?.declaration || ''
    // Path values are user-controlled URL segments. Numeric identifiers can
    // remain numeric for readable generated code; string handles/slugs must
    // be encoded so Unicode, spaces and reserved characters cannot alter the
    // route shape. The generated SDK is the single place that owns this rule.
    const type = declaration.replace(/^\w+\??\s*:\s*/, '').trim()
    return type === 'number' || type === 'boolean' ? name : `encodeURIComponent(String(${name}))`
  }
  let pathIndex = 0
  const url = method.path.replaceAll(/:[A-Za-z_]\w*/g, () => `\${${pathParamExpression(pathParams[pathIndex++])}}`)
  const urlLiteral = pathParams.length ? `\`${url}\`` : JSON.stringify(method.path)
  const remaining = params.slice(pathParams.length)
  const returnType = qualifyType(method.returnType)
  const signature = params.map((param) => param.declaration).join(', ')
  const requestArgument = remaining.at(-1)?.name
  let call
  if (method.httpMethod === 'GET') call = `this._get<${returnType}>(${urlLiteral}${requestArgument ? `, ${requestArgument}` : ''})`
  else if (method.httpMethod === 'DELETE') call = `this._delete<${returnType}>(${urlLiteral}${requestArgument ? `, ${requestArgument}` : ''})`
  else if (requestArgument && /FormData/.test(remaining.at(-1)?.declaration || '')) call = `this._${method.httpMethod.toLowerCase()}<${returnType}>(${urlLiteral}, ${requestArgument}, { headers: { 'Content-Type': 'multipart/form-data' } })`
  else if (requestArgument) call = `this._${method.httpMethod.toLowerCase()}<${returnType}>(${urlLiteral}, ${requestArgument})`
  else call = `this._${method.httpMethod.toLowerCase()}<${returnType}>(${urlLiteral})`
  return `  ${method.method}(${signature}): Promise<${returnType}> {\n    return ${call}\n  }`
}
