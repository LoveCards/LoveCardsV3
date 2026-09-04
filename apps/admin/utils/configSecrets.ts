export type SecretConfigEnvelope =
  | { '$secret': 'keep'; configured: boolean }
  | { '$secret': 'replace'; value: string; configured: boolean }
  | { '$secret': 'clear'; configured: boolean }

const isRecord = (value: unknown): value is Record<string, unknown> => {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value)
}

export const isSecretConfigEnvelope = (value: unknown): boolean => {
  return isRecord(value) && typeof value.$secret === 'string'
}

/** 把服务端 envelope 或旧值归一为不包含密钥原文的安全状态。 */
export const normalizeSecretConfig = (value: unknown): SecretConfigEnvelope => {
  if (isRecord(value) && value.$secret === 'keep') {
    return { '$secret': 'keep', configured: value.configured === true }
  }
  return { '$secret': 'keep', configured: value !== null && value !== undefined && value !== '' }
}

/** 保存成功后只保留“是否已配置”，不在页面状态中继续留存新密钥。 */
export const settleSecretConfig = (value: SecretConfigEnvelope): SecretConfigEnvelope => {
  if (value.$secret === 'replace') return { '$secret': 'keep', configured: true }
  if (value.$secret === 'clear') return { '$secret': 'keep', configured: false }
  return { '$secret': 'keep', configured: value.configured }
}
