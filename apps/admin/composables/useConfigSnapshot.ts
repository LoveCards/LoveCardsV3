import { isApiError, type JsonObject, type LCClient } from '@lovecards/sdk'

class ConfigSnapshotError extends Error {}

/** 页面独立保存所见配置版本；冲突不重新读取或替换用户编辑。 */
export function useConfigSnapshot(client: LCClient) {
  const revisions = new Map<string, number>()

  const remember = (snapshot: JsonObject, required: string[] = []) => {
    for (const group of required) {
      const value = snapshot[group]
      if (!value || typeof value !== 'object' || Array.isArray(value)
        || !Number.isInteger(value.__revision) || Number(value.__revision) < 1) {
        throw new ConfigSnapshotError('配置已返回，但版本信息缺失。请重新加载配置后再操作。')
      }
    }
    for (const [group, value] of Object.entries(snapshot)) {
      if (value && typeof value === 'object' && !Array.isArray(value)
        && Number.isInteger(value.__revision) && Number(value.__revision) > 0) {
        revisions.set(group, Number(value.__revision))
      }
    }
    return snapshot
  }

  const list = async () => {
    const snapshot = await client.config.list()
    revisions.clear()
    return remember(snapshot)
  }

  const update = async (changes: JsonObject) => {
    const payload: JsonObject = {}
    for (const [group, values] of Object.entries(changes)) {
      const revision = revisions.get(group)
      if (!revision || !values || typeof values !== 'object' || Array.isArray(values)) {
        throw new ConfigSnapshotError('尚未加载该组配置版本，请重新加载后再保存；当前编辑仍保留。')
      }
      payload[group] = { ...values, __revision: revision }
    }
    const committed = await client.config.update(payload)
    return remember(committed, Object.keys(changes))
  }

  const errorMessage = (cause: unknown, fallback: string) => {
    if (isApiError(cause) && cause.status === 409) return '配置已被其他管理员修改。当前编辑已保留，请重新加载并核对后再保存。'
    if (cause instanceof ConfigSnapshotError) return cause.message
    return isApiError(cause) ? cause.message : fallback
  }

  return { list, update, errorMessage }
}
