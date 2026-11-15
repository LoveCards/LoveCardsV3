<template>
  <section aria-label="事件明细">
    <p class="text-body-2 text-medium-emphasis mb-4">每行是一件已经发生的业务事件；展开后可查看它对应的处理任务。这里不展示事件正文或私密数据。</p>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <v-select v-model="queue" :items="queueOptions" item-title="title" item-value="value" label="处理队列" variant="outlined" density="compact" hide-details class="flex-grow-1" />
      <v-text-field v-model="draftType" label="事件类型" placeholder="例如 message.sent" variant="outlined" density="compact" hide-details class="flex-grow-1" @keyup.enter="applyType" />
      <v-btn variant="outlined" @click="applyType">筛选</v-btn>
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn>
    </div>
    <AdminAsyncState :loading="loading && !events.length" :error="error && !events.length ? error : null" :empty="!loading && !events.length" empty-title="没有匹配的事件" @retry="load">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
      <v-expansion-panels variant="accordion">
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
        <span class="text-body-2">共 {{ total }} 条事件</span>
        <v-pagination v-if="pageCount > 1" v-model="page" :length="pageCount" :total-visible="5" aria-label="事件页码" />
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
const page = computed<number>({
  get: () => typeof route.query.event_page === 'string' ? Math.max(1, Number.parseInt(route.query.event_page, 10) || 1) : 1,
  set: value => { if (value !== page.value) updateQuery({ event_page: value === 1 ? undefined : String(value) }) },
})
const events = ref<OperationsEvent[]>([])
const total = ref(0)
const pageCount = computed(() => Math.ceil(total.value / 20))
const loading = ref(false)
const error = ref<string | null>(null)
let requestId = 0

function updateQuery(patch: Record<string, string | undefined>) {
  void router.push({ query: { ...route.query, tab: 'events', ...patch } })
}
function applyType() {
  const next = draftType.value.trim()
  if (next !== eventType.value || page.value !== 1) updateQuery({ event_type: next || undefined, event_page: undefined })
}
async function load() {
  const id = ++requestId
  loading.value = true
  error.value = null
  events.value = []
  total.value = 0
  try {
    const result = await client.operations.events({ page: page.value, list_rows: 20, ...(queue.value ? { queue_name: queue.value } : {}), ...(eventType.value ? { event_type: eventType.value } : {}) })
    if (id !== requestId) return
    events.value = result.data
    total.value = result.pagination?.totalItems ?? result.data.length
  } catch (cause) {
    if (id === requestId) error.value = isApiError(cause) ? cause.message : '无法加载事件明细'
  } finally { if (id === requestId) loading.value = false }
}
watch(eventType, value => { draftType.value = value })
watch([queue, eventType, page], () => { void load() }, { immediate: true })
</script>
