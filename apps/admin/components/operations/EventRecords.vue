<template>
  <section aria-label="事件明细">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <h2 class="text-h6">事件明细</h2>
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新事件明细</v-btn>
    </div>
    <p class="text-body-2 text-medium-emphasis mb-4">每行是一件已经发生的业务事件；展开后可查看它对应的处理任务。这里不展示事件正文或私密数据。</p>
    <div class="d-flex flex-wrap align-start ga-3 mb-4">
      <v-text-field v-model="draftEventId" label="事件 ID" inputmode="numeric" variant="outlined" density="compact" hide-details="auto" :error-messages="idValidationError" class="event-records__filter" @keyup.enter="applyFilters" />
      <v-select v-model="queue" :items="queueOptions" item-title="title" item-value="value" label="处理队列" variant="outlined" density="compact" hide-details class="event-records__filter" />
      <v-text-field v-model="draftType" label="事件类型" placeholder="例如 message.sent" variant="outlined" density="compact" hide-details class="event-records__filter" @keyup.enter="applyFilters" />
      <v-btn variant="outlined" @click="applyFilters">筛选</v-btn>
      <v-btn v-if="hasFilters" variant="text" @click="clearFilters">清除筛选</v-btn>
    </div>
    <AdminAsyncState :loading="loading && !hasCurrentResult" :error="error && !hasCurrentResult ? error : null" :empty="!loading && !error && !events.length" empty-title="没有匹配的事件" @retry="load">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
      <v-expansion-panels v-model="expandedEvent" variant="accordion">
        <v-expansion-panel v-for="event in events" :key="event.id" :value="event.id" elevation="0" border>
          <v-expansion-panel-title>
            <div class="d-flex flex-wrap align-center ga-3">
              <strong>{{ event.event_type }}@{{ event.event_version }}</strong>
              <span class="text-body-2">#{{ event.id }} · {{ event.aggregate_type }} · {{ event.occurred_at }}</span>
              <v-chip size="small" variant="tonal">{{ event.deliveries.length }} 项处理</v-chip>
            </div>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="text-body-2 mb-3">关联对象：{{ event.aggregate_key }}</p>
            <v-alert v-if="!event.deliveries.length" type="info" variant="tonal">这条事实没有注册处理器，因此没有投递任务。</v-alert>
            <v-list v-else lines="two" border rounded>
              <v-list-item v-for="delivery in event.deliveries" :key="delivery.id" :title="delivery.handler_key" :subtitle="`${delivery.queue_name} · 已尝试 ${delivery.attempt_count} 次`" :to="`/apps/operations/deliveries/${delivery.id}`">
                <template #append><AdminStatusChip :status="delivery.status" :label="delivery.policy_decision === 'skipped' ? '按策略跳过' : undefined" /></template>
              </v-list-item>
            </v-list>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <div class="d-flex flex-wrap align-center justify-space-between ga-3 mt-4">
        <span v-if="hasCurrentResult" class="text-body-2">{{ loading ? '更新中 · 上次结果共' : error ? '上次结果共' : '共' }} {{ total }} 条事件</span>
        <v-pagination v-if="pageCount > 1" v-model="page" :length="pageCount" :total-visible="5" :disabled="loading || !!error" aria-label="事件页码" />
      </div>
    </AdminAsyncState>
  </section>
</template>

<script setup lang="ts">
import type { OperationsEvent } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { isApiError, useApi } from '~/lib/api'

const client = useApi()
const route = useRoute()
const router = useRouter()
const queueOptions = [
  { title: '全部事件', value: '' },
  { title: '通知', value: 'notifications' },
  { title: '实时提示', value: 'realtime' },
  { title: '文件清理', value: 'storage' },
]
const queue = computed<string>({
  get: () => typeof route.query.queue === 'string' ? route.query.queue : '',
  set: value => { if (value !== queue.value) updateQuery({ queue: value || undefined, event_page: undefined }) },
})
const eventType = computed(() => typeof route.query.event_type === 'string' ? route.query.event_type.trim() : '')
const draftType = ref(eventType.value)
const rawEventId = computed(() => route.query.event_id)
function validEventId(value: unknown): number | null {
  if (typeof value !== 'string' || !/^[1-9]\d*$/.test(value)) return null
  const number = Number(value)
  return Number.isSafeInteger(number) && number > 0 ? number : null
}
const eventId = computed(() => validEventId(rawEventId.value))
const draftEventId = ref(typeof rawEventId.value === 'string' ? rawEventId.value : '')
const draftIdError = ref('')
const queryIdError = computed(() => rawEventId.value !== undefined && eventId.value === null ? '事件 ID 必须是有效的正整数；请修正或清除筛选。' : '')
const idValidationError = computed(() => draftIdError.value || queryIdError.value)
const hasFilters = computed(() => Boolean(queue.value || eventType.value || rawEventId.value !== undefined))
const expandedEvent = ref<number | undefined>()
const page = computed<number>({
  get: () => typeof route.query.event_page === 'string' ? Math.max(1, Number.parseInt(route.query.event_page, 10) || 1) : 1,
  set: value => { if (value !== page.value) updateQuery({ event_page: value === 1 ? undefined : String(value) }) },
})
const events = ref<OperationsEvent[]>([])
const total = ref(0)
const pageCount = computed(() => Math.ceil(total.value / 20))
const loading = ref(false)
const error = ref<string | null>(null)
const lastSuccessfulKey = ref('')
const currentQueryKey = computed(() => JSON.stringify({ page: page.value, queue: queue.value, eventType: eventType.value, eventId: rawEventId.value }))
const hasCurrentResult = computed(() => lastSuccessfulKey.value === currentQueryKey.value)
let requestId = 0

function updateQuery(patch: Record<string, string | undefined>) {
  void router.replace({ query: { ...route.query, tab: 'events', ...patch } })
}
function applyFilters() {
  const nextId = draftEventId.value.trim()
  draftIdError.value = nextId && validEventId(nextId) === null ? '事件 ID 必须是有效的正整数' : ''
  if (draftIdError.value) return
  updateQuery({ event_id: nextId || undefined, event_type: draftType.value.trim() || undefined, event_page: undefined })
}
function clearFilters() {
  draftEventId.value = ''
  draftType.value = ''
  draftIdError.value = ''
  updateQuery({ event_id: undefined, event_type: undefined, queue: undefined, event_page: undefined })
}
async function load() {
  const id = ++requestId
  const queryKey = currentQueryKey.value
  if (queryIdError.value) {
    events.value = []
    total.value = 0
    lastSuccessfulKey.value = ''
    loading.value = false
    error.value = queryIdError.value
    return
  }
  loading.value = true
  error.value = null
  if (queryKey !== lastSuccessfulKey.value) {
    events.value = []
    total.value = 0
  }
  try {
    const result = await client.operations.events({ page: page.value, list_rows: 20, ...(eventId.value !== null ? { event_id: eventId.value } : {}), ...(queue.value ? { queue_name: queue.value } : {}), ...(eventType.value ? { event_type: eventType.value } : {}) })
    if (id !== requestId) return
    events.value = result.data
    total.value = result.pagination?.totalItems ?? result.data.length
    expandedEvent.value = eventId.value !== null && result.data.some(event => event.id === eventId.value) ? eventId.value : undefined
    lastSuccessfulKey.value = queryKey
  } catch (cause) {
    if (id === requestId) error.value = isApiError(cause) ? cause.message : '无法加载事件明细'
  } finally { if (id === requestId) loading.value = false }
}
watch(eventType, value => { draftType.value = value })
watch(rawEventId, value => { draftEventId.value = typeof value === 'string' ? value : ''; draftIdError.value = '' })
watch([queue, eventType, rawEventId, page], () => { void load() }, { immediate: true })
</script>

<style scoped>
.event-records__filter { flex: 1 1 180px; min-width: 0; }
</style>
