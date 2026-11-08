type SearchOption = { title: string; value: string | number }

/** 仅在明确搜索单个枚举字段时，把展示名称转换为接口存储值。 */
export function tableSearchValue(value: string, keys: unknown, options: Record<string, readonly SearchOption[]>): string {
  if (!Array.isArray(keys) || keys.length !== 1 || typeof keys[0] !== 'string') return value
  const fieldOptions = options[keys[0]]
  if (!fieldOptions) return value
  const term = value.trim()
  const match = fieldOptions.find((option) => option.title.replace(/^#\d+\s*/, '').trim() === term)
  return match ? String(match.value) : value
}
