<template>
  <AdminPageContainer fluid>
    <AdminPageHeader title="处理任务" description="按队列与状态定位事件处理结果。处理完成不代表浏览器已收到或用户已阅读。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="loadAll">刷新</v-btn></template>
    </AdminPageHeader>
    <p class="mb-4 text-body-2">每行是一项处理任务。<NuxtLink to="/apps/operations/eventing?tab=events">查看业务事件</NuxtLink> · <NuxtLink v-if="queue === 'notifications'" to="/apps/operations/notification-policies">通知策略</NuxtLink></p>
    <v-sheet v-if="queue === 'notifications' && summary" border rounded class="mb-4 pa-4">
      <div class="d-flex flex-wrap ga-5">
        <span>失败 <strong>{{ summary.dead }}</strong></span><span>待处理 <strong>{{ summary.pending }}</strong></span>
        <span>处理中 <strong>{{ summary.processing }}</strong></span><span>已按策略跳过 <strong>{{ summary.skipped }}</strong></span>
      </div>
      <p class="mt-2 mb-0 text-caption text-medium-emphasis">通知队列当前累计状态 · 检查于 {{ summary.checked_at }}</p>
    </v-sheet>
    <v-alert v-if="queue === 'notifications' && summaryError" class="mb-4" type="warning" variant="tonal">处理汇总暂不可用：{{ summaryError }}<template #append><v-btn variant="outlined" @click="loadSummary">重试</v-btn></template></v-alert>
    <AdminResourceTableShell v-model:visible-columns="visibleColumns" :columns="columnOptions" :default-columns="defaultColumns" :result-count="resultCount" :result-state="resultState" :view-query-keys="['queue', 'view', 'list_rows']" preference-key="operations-deliveries" @reset-view="resetTableView" @apply-view="applySavedView">
      <template #toolbar>
        <v-select :model-value="queue" :items="queueOptions" item-title="title" item-value="value" label="处理队列" variant="outlined" density="compact" hide-details class="mb-3" @update:model-value="setQueue" />
        <v-select :model-value="view" class="d-sm-none w-100" :items="viewOptions" label="处理状态" variant="outlined" density="compact" hide-details @update:model-value="setView" />
        <div class="delivery-filter-scroll d-none d-sm-block">
          <v-btn-toggle :model-value="view" mandatory aria-label="处理状态筛选" @update:model-value="setView">
            <v-btn value="dead">失败</v-btn><v-btn value="all">全部</v-btn><v-btn value="pending">待处理</v-btn>
            <v-btn value="processing">处理中</v-btn><v-btn value="skipped">已跳过</v-btn>
          </v-btn-toggle>
        </div>
      </template>
      <AdminAsyncState :loading="loading && !deliveries.length" :error="error && !deliveries.length ? error : null" :empty="!loading && !deliveries.length" :empty-title="view === 'dead' ? '没有失败记录' : '没有匹配的处理记录'" empty-description="试试其他处理状态。" @retry="loadDeliveries">
        <v-alert v-if="error" class="ma-3" type="error" variant="tonal">{{ error }}<template #append><v-btn variant="outlined" @click="loadDeliveries">重试</v-btn></template></v-alert>
        <v-data-table-server class="admin-table-no-sort" v-model:items-per-page="itemsPerPage" v-model:page="page" :headers="headers" :items="deliveries" :items-length="totalItems" :items-per-page-options="[10, 20, 50]" :loading="loading" item-value="id" items-per-page-text="每页行数">
          <template #item.id="{ item }"><span class="text-medium-emphasis">#{{ item.id }}</span></template>
          <template #item.event="{ item }">{{ item.display_name || item.event_type }}</template>
          <template #item.event_type="{ item }">{{ item.event_type }}</template>
          <template #item.queue="{ item }">{{ queueLabel(item.queue_name) }}</template>
          <template #item.status="{ item }"><span v-if="item.policy_decision === 'skipped'">{{ skipLabel(item.queue_name) }}</span><AdminStatusChip v-else :status="item.status" /></template>
          <template #item.error="{ item }"><span class="delivery-error">{{ item.last_error || '—' }}</span></template>
          <template #item.updated_at="{ item }"><AdminTableTime :value="item.updated_at" /></template>
          <template #item.action="{ item }"><AdminTableRowAction @click="openDelivery(item.id)">查看</AdminTableRowAction></template>
        </v-data-table-server>
      </AdminAsyncState>
    </AdminResourceTableShell>
  </AdminPageContainer>
  <DeliveryDetailDialog v-model="inspectOpen" :delivery-id="inspectId" :source-path="route.fullPath" @updated="loadDeliveries" />
</template>

<script setup lang="ts">
import { useLatestAdminRequest } from '~/composables/useLatestAdminRequest'
import type { OperationsDelivery, OperationsDeliverySummary } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import DeliveryDetailDialog from '~/components/operations/DeliveryDetailDialog.vue'
import { useApi, isApiError } from '~/lib/api'

type View = 'dead' | 'all' | 'pending' | 'processing' | 'skipped'
type Queue = 'notifications' | 'realtime' | 'storage' | 'all'
const client = useApi()
const route = useRoute()
const router = useRouter()
const inspectOpen = ref(false)
const inspectId = ref<number | null>(null)
const openDelivery = (id: number) => { inspectId.value = id; inspectOpen.value = true }
const deliveries = ref<OperationsDelivery[]>([])
const summary = ref<OperationsDeliverySummary | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const summaryError = ref<string | null>(null)
const validViews: View[] = ['dead', 'all', 'pending', 'processing', 'skipped']
const validQueues: Queue[] = ['notifications', 'realtime', 'storage', 'all']
const queueOptions = [
  { title: '通知', value: 'notifications' },
  { title: '全部队列', value: 'all' },
  { title: '实时提示', value: 'realtime' },
  { title: '文件处理', value: 'storage' },
]
const parseQueue = (value: unknown): Queue => typeof value === 'string' && validQueues.includes(value as Queue) ? value as Queue : 'notifications'
const queue = ref<Queue>(parseQueue(route.query.queue))
const queueLabel = (name: string) => queueOptions.find(option => option.value === name)?.title ?? name
const skipLabel = (name: string) => name === 'notifications' ? '已按策略跳过通知' : name === 'realtime' ? '已跳过实时提示' : '已跳过'
const viewOptions = [
  { title: '失败', value: 'dead' }, { title: '全部', value: 'all' }, { title: '待处理', value: 'pending' },
  { title: '处理中', value: 'processing' }, { title: '已跳过', value: 'skipped' },
]
const parseView = (value: unknown): View => typeof value === 'string' && validViews.includes(value as View) ? value as View : 'dead'
const view = ref<View>(parseView(route.query.view))
const allowedPageSizes = [10, 20, 50]
const queryInt = (value: unknown, fallback: number) => { const n = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN; return Number.isInteger(n) && n > 0 ? n : fallback }
const page = ref(queryInt(route.query.page, 1))
const requestedPageSize = queryInt(route.query.list_rows, 20)
const itemsPerPage = ref(allowedPageSizes.includes(requestedPageSize) ? requestedPageSize : 20)
const totalItems = ref(0)
const resultCount = ref<number | null>(null)
const resultState = ref<'ready' | 'loading' | 'stale'>('ready')
let lastSuccessfulKey: string | null = null
const allHeaders = [
  { title: 'ID', key: 'id', minWidth: 88 }, { title: '业务事件', key: 'event', minWidth: 240 },
  { title: '事件类型', key: 'event_type', minWidth: 200 }, { title: '队列', key: 'queue', minWidth: 120 },
  { title: '处理结果', key: 'status', minWidth: 128 }, { title: '尝试', key: 'attempt_count', minWidth: 88 },
  { title: '最后错误', key: 'error', minWidth: 240 }, { title: '更新时间', key: 'updated_at', minWidth: 176 },
  { title: '操作', key: 'action', minWidth: 112 },
].map((header) => ({ ...header, sortable: false }))
const defaultColumns = allHeaders.filter((header) => header.key !== 'id').map((header) => header.key)
const visibleColumns = ref([...defaultColumns])
const headers = computed(() => allHeaders.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map((header) => ({ title: header.title, key: header.key, required: header.key === 'event' || header.key === 'action' }))
const beginDeliveryRequest = useLatestAdminRequest()
const beginSummaryRequest = useLatestAdminRequest()
async function loadDeliveries() {
  const isCurrent = beginDeliveryRequest()
  const filters = view.value === 'skipped' ? { status: 'completed', policy_decision: 'skipped' } : view.value === 'all' ? {} : { status: view.value }
  const params = { page: page.value, list_rows: itemsPerPage.value, ...(queue.value === 'all' ? {} : { queue_name: queue.value }), ...filters }
  const queryKey = JSON.stringify(params)
  if (queryKey !== lastSuccessfulKey) {
    deliveries.value = []
    totalItems.value = 0
    resultCount.value = null
    lastSuccessfulKey = null
  } else {
    resultState.value = 'loading'
  }
  loading.value = true
  error.value = null
  try {
    const result = await client.operations.deliveries(params)
    if (!isCurrent()) return
    deliveries.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
    resultCount.value = totalItems.value
    resultState.value = 'ready'
    lastSuccessfulKey = queryKey
  } catch (cause) {
    if (isCurrent()) {
      error.value = isApiError(cause) ? cause.message : '无法加载处理任务'
      if (lastSuccessfulKey === queryKey) resultState.value = 'stale'
    }
  } finally { if (isCurrent()) loading.value = false }
}
async function loadSummary() {
  const isCurrent = beginSummaryRequest()
  summaryError.value = null
  try { const result = await client.operations.deliverySummary(); if (isCurrent()) summary.value = result }
  catch (cause) { if (isCurrent()) summaryError.value = isApiError(cause) ? cause.message : '无法加载处理汇总' }
}
async function loadAll() { await Promise.all([loadDeliveries(), ...(queue.value === 'notifications' ? [loadSummary()] : [])]) }
function syncRouteQuery() {
  const next = { ...route.query, queue: queue.value === 'notifications' ? undefined : queue.value, view: view.value, page: String(page.value), list_rows: String(itemsPerPage.value) }
  if (route.query.queue === next.queue && route.query.view === next.view && route.query.page === next.page && route.query.list_rows === next.list_rows) return
  void router.replace({ query: next })
}
function applySavedView(saved: Record<string, string | null | (string | null)[]>) {
  const nextQueue = parseQueue(saved.queue)
  const nextView = parseView(saved.view)
  const size = queryInt(saved.list_rows, 20)
  queue.value = nextQueue
  view.value = nextView
  itemsPerPage.value = allowedPageSizes.includes(size) ? size : 20
  page.value = 1
  syncRouteQuery()
  if (nextQueue === 'notifications' && !summary.value) void loadSummary()
}
function resetTableView() {
  queue.value = 'notifications'
  view.value = 'dead'
  page.value = 1
  itemsPerPage.value = 20
  if (!summary.value) void loadSummary()
}
function setQueue(next: unknown) {
  const selected = parseQueue(next)
  if (selected === queue.value) return
  queue.value = selected
  page.value = 1
  if (selected === 'notifications' && !summary.value) void loadSummary()
}
function setView(next: unknown) {
  const selected = parseView(next)
  if (selected === view.value) return
  view.value = selected
  page.value = 1
}
watch(() => [route.query.queue, route.query.view, route.query.page, route.query.list_rows], () => {
  const nextQueue = parseQueue(route.query.queue)
  if (queue.value !== nextQueue) queue.value = nextQueue
  const nextView = parseView(route.query.view)
  const nextPage = queryInt(route.query.page, 1)
  const nextSize = queryInt(route.query.list_rows, 20)
  if (view.value !== nextView) view.value = nextView
  if (page.value !== nextPage) page.value = nextPage
  if (allowedPageSizes.includes(nextSize) && itemsPerPage.value !== nextSize) itemsPerPage.value = nextSize
})
let initialized = false
watch([queue, view, page, itemsPerPage], () => {
  if (initialized) syncRouteQuery()
  initialized = true
  void loadDeliveries()
}, { immediate: true })
onMounted(() => { if (queue.value === 'notifications') void loadSummary() })
</script>

<style scoped>
.delivery-filter-scroll { max-width: 100%; overflow-x: auto; }
.delivery-filter-scroll .v-btn-toggle { width: max-content; }
.delivery-error { display: inline-block; max-width: 36ch; overflow-wrap: anywhere; }
</style>
