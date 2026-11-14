type SortItem = { key: string; order?: boolean | 'asc' | 'desc' }

/** 表头与搜索弹窗共用排序；兼容旧视图中嵌在 filters 内的排序字段。 */
export function useAdminTableSearch(sortBy: Ref<SortItem[]>, filters: Ref<Record<string, unknown>>) {
  const withoutSort = (value: Record<string, unknown>) => {
    const { order_key, order_desc, ...rest } = value
    return rest
  }
  if (!sortBy.value.length && typeof filters.value.order_key === 'string') {
    sortBy.value = [{ key: filters.value.order_key, order: filters.value.order_desc === false ? 'asc' : 'desc' }]
  }
  filters.value = withoutSort(filters.value)
  const searchDialogFilter = computed(() => ({
    ...filters.value,
    order_key: sortBy.value[0]?.key,
    order_desc: sortBy.value[0] ? ['desc', true].includes(sortBy.value[0].order ?? 'asc') : true,
  }))
  const applySearchDialog = (value: Record<string, unknown>) => {
    filters.value = withoutSort(value)
    sortBy.value = typeof value.order_key === 'string' && value.order_key
      ? [{ key: value.order_key, order: value.order_desc === false ? 'asc' : 'desc' }]
      : []
  }
  return { searchDialogFilter, applySearchDialog }
}

type SearchKey = { title: string; value: string }
type HighlightSnapshot = { term: string; fields: string[] }

export function tableSearchCell(item: object, field: string): string | number | null {
  const value = (item as Record<string, unknown>)[field]
  return typeof value === 'string' || typeof value === 'number' ? value : null
}

/** 搜索提示跟随已选字段，高亮只使用最近一次成功加载的查询条件。 */
export function useAdminTableSearchFeedback(
  searchValue: Ref<string | undefined>,
  filters: Ref<Record<string, unknown>>,
  keys: SearchKey[],
  defaultField: string,
  defaultLabel: string,
) {
  const selectedFields = () => Array.isArray(filters.value.search_keys)
    ? filters.value.search_keys.filter((key): key is string => typeof key === 'string' && keys.some((option) => option.value === key))
    : []
  const searchInputLabel = computed(() => {
    const fields = selectedFields()
    return fields.length ? `搜索${fields.map((field) => keys.find((key) => key.value === field)?.title).join('、')}` : defaultLabel
  })
  const applied = shallowRef<HighlightSnapshot>({ term: '', fields: [defaultField] })
  const searchSnapshot = (): HighlightSnapshot => {
    const fields = selectedFields()
    return { term: searchValue.value?.trim() ?? '', fields: fields.length ? fields : [defaultField] }
  }
  const applySearchSnapshot = (snapshot: HighlightSnapshot) => { applied.value = snapshot }
  const highlightQuery = (field: string) => applied.value.fields.includes(field) ? applied.value.term : ''
  return { searchInputLabel, searchSnapshot, applySearchSnapshot, highlightQuery }
}
