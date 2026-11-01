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
