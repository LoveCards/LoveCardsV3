<template>
  <v-dialog v-model="thisDialogState" aria-label="搜索与排序" max-width="600">
    <v-card prepend-icon="mdi-filter-outline" title="搜索与排序">
      <v-card-text class="pt-4">
        <v-alert v-if="applyError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ applyError }}
        </v-alert>
        <v-row dense>
          <slot name="filters" :filters="extraFilters" />
          <v-col cols="12">
            <v-select
              v-model="filter.search_keys"
              chips
              clearable
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="KEYS"
              label="搜索字段"
              multiple
              :messages="props.KeysMessages"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="8">
            <v-select
              v-model="filter.order_key"
              clearable
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="KEYS"
              label="排序字段"
              messages="默认按资源 ID 排序"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-select
              v-model="filter.order_desc"
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="ORDER_DESC"
              label="排序方式"
              variant="outlined"
            />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-btn :disabled="applying" variant="text" @click="reset">重置</v-btn>
        <v-spacer />
        <v-btn :disabled="applying" variant="text" @click="thisDialogState = false">取消</v-btn>
        <v-btn color="primary" :loading="applying" variant="flat" @click="submit">应用筛选</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
const ORDER_DESC = [
  { title: '升序', value: false },
  { title: '降序', value: true },
]

const props = defineProps<{
  KEYS?: unknown[]
  getTableData?: () => unknown
  setFilter?: (data: Record<string, unknown>) => unknown
  KeysMessages?: string
  initialFilter?: Record<string, unknown>
}>()

const KEYS = computed(() => props.KEYS ?? [])
const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const applying = ref(false)
const applyError = ref('')
const filter = ref({
  search_keys: [] as unknown[],
  order_key: undefined as string | undefined,
  order_desc: true,
})
const extraFilters = ref<Record<string, unknown>>({})

const syncInitialFilter = () => {
  const current = props.initialFilter ?? {}
  filter.value = {
    search_keys: Array.isArray(current.search_keys) ? [...current.search_keys] : [],
    order_key: typeof current.order_key === 'string' ? current.order_key : undefined,
    order_desc: current.order_desc !== false,
  }
  const { search_keys, order_key, order_desc, ...rest } = current
  extraFilters.value = { ...rest }
}

watch(thisDialogState, (open) => {
  if (open) {
    applyError.value = ''
    syncInitialFilter()
  }
})

const reset = () => {
  filter.value = { search_keys: [], order_key: undefined, order_desc: true }
  extraFilters.value = {}
}

const submit = async () => {
  if (applying.value) return
  const nextFilter: Record<string, unknown> = { ...extraFilters.value, ...filter.value }
  for (const key of Object.keys(nextFilter)) {
    if (nextFilter[key] == null) delete nextFilter[key]
  }
  if (!nextFilter.order_key) delete nextFilter.order_key
  if (Array.isArray(nextFilter.search_keys) && nextFilter.search_keys.length === 0) {
    delete nextFilter.search_keys
  }

  applying.value = true
  applyError.value = ''
  try {
    await props.setFilter?.(nextFilter)
    await props.getTableData?.()
    thisDialogState.value = false
  } catch (cause) {
    applyError.value = cause instanceof Error ? cause.message : '应用筛选失败，请稍后重试。'
  } finally {
    applying.value = false
  }
}
</script>
