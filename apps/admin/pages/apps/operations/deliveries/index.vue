<template>
  <AdminPageContainer>
    <AdminPageHeader title="通知处理" description="检查站内提醒对应事件的处理结果。处理完成不代表用户已阅读。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="loadAll">刷新</v-btn></template>
    </AdminPageHeader>
    <p class="mb-4 text-body-2">仅显示通知处理队列。<NuxtLink to="/apps/operations/eventing">事件处理</NuxtLink> · <NuxtLink to="/apps/operations/notification-policies">通知策略</NuxtLink></p>
    <v-sheet v-if="summary" border rounded class="mb-4 pa-4">
      <div class="d-flex flex-wrap ga-5">
        <span>失败 <strong>{{ summary.dead }}</strong></span><span>待处理 <strong>{{ summary.pending }}</strong></span>
        <span>处理中 <strong>{{ summary.processing }}</strong></span><span>已按策略跳过 <strong>{{ summary.skipped }}</strong></span>
      </div>
      <p class="mt-2 mb-0 text-caption text-medium-emphasis">通知队列当前累计状态 · 检查于 {{ summary.checked_at }}</p>
    </v-sheet>
    <v-alert v-if="summaryError" class="mb-4" type="warning" variant="tonal">处理汇总暂不可用：{{ summaryError }}<template #append><v-btn variant="outlined" @click="loadSummary">重试</v-btn></template></v-alert>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <v-select :model-value="view" class="d-sm-none w-100" :items="viewOptions" label="处理状态" variant="outlined" density="compact" hide-details @update:model-value="setView" />
      <div class="delivery-filter-scroll d-none d-sm-block">
        <v-btn-toggle :model-value="view" mandatory aria-label="处理状态筛选" @update:model-value="setView">
          <v-btn value="dead">失败</v-btn><v-btn value="all">全部</v-btn><v-btn value="pending">待处理</v-btn>
          <v-btn value="processing">处理中</v-btn><v-btn value="skipped">按策略跳过</v-btn>
        </v-btn-toggle>
      </div>
      <span class="text-body-2 text-medium-emphasis">{{ totalItems }} 条记录</span>
    </div>
    <AdminAsyncState :loading="loading && !deliveries.length" :error="error && !deliveries.length ? error : null" :empty="!loading && !deliveries.length" :empty-title="view === 'dead' ? '没有失败记录' : '没有匹配的处理记录'" empty-description="试试其他处理状态。" @retry="loadDeliveries">
      <v-alert v-if="error" class="mb-3" type="error" variant="tonal">{{ error }}<template #append><v-btn variant="outlined" @click="loadDeliveries">重试</v-btn></template></v-alert>
      <AdminResourceTableShell v-model:visible-columns="visibleColumns" :columns="columnOptions" :result-count="totalItems" preference-key="operations-deliveries" @reset-view="resetTableView">
        <v-data-table-server v-model:items-per-page="itemsPerPage" v-model:page="page" :headers="headers" :items="deliveries" :items-length="totalItems" :items-per-page-options="[10, 20, 50]" :loading="loading" item-value="id" items-per-page-text="每页行数">
          <template #item.event="{ item }"><AdminResourceLink :label="item.display_name || item.event_type" resource-type="event_delivery" :resource-id="item.id" /><div class="text-caption text-medium-emphasis">{{ item.event_type }} · #{{ item.id }}</div></template>
          <template #item.status="{ item }"><span v-if="item.policy_decision === 'skipped'">已按策略跳过</span><AdminStatusChip v-else :status="item.status" /></template>
          <template #item.error="{ item }"><span class="delivery-error">{{ item.last_error || '-' }}</span></template>
          <template #item.action="{ item }"><NuxtLink :to="`/apps/operations/deliveries/${item.id}`">查看详情</NuxtLink></template>
        </v-data-table-server>
      </AdminResourceTableShell>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsDelivery, OperationsDeliverySummary } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'

type View = 'dead' | 'all' | 'pending' | 'processing' | 'skipped'
const client = useApi()
const route = useRoute()
const router = useRouter()
const deliveries = ref<OperationsDelivery[]>([])
const summary = ref<OperationsDeliverySummary | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const summaryError = ref<string | null>(null)
const validViews: View[] = ['dead', 'all', 'pending', 'processing', 'skipped']
const viewOptions = [
  { title: '失败', value: 'dead' }, { title: '全部', value: 'all' }, { title: '待处理', value: 'pending' },
  { title: '处理中', value: 'processing' }, { title: '按策略跳过', value: 'skipped' },
]
const parseView = (value: unknown): View => typeof value === 'string' && validViews.includes(value as View) ? value as View : 'dead'
const view = ref<View>(parseView(route.query.view))
const allowedPageSizes = [10, 20, 50]
const queryInt = (value: unknown, fallback: number) => { const n = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN; return Number.isInteger(n) && n > 0 ? n : fallback }
const page = ref(queryInt(route.query.page, 1))
const requestedPageSize = queryInt(route.query.list_rows, 20)
const itemsPerPage = ref(allowedPageSizes.includes(requestedPageSize) ? requestedPageSize : 20)
const totalItems = ref(0)
const allHeaders = [
  { title: '业务事件', key: 'event' }, { title: '处理结果', key: 'status' }, { title: '尝试', key: 'attempt_count' },
  { title: '最后错误', key: 'error' }, { title: '更新时间', key: 'updated_at' }, { title: '操作', key: 'action' },
].map((header) => ({ ...header, sortable: false }))
const visibleColumns = ref(allHeaders.map((header) => header.key))
const headers = computed(() => allHeaders.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map((header) => ({ title: header.title, key: header.key, required: header.key === 'event' || header.key === 'action' }))
let latestDeliveryRequest = 0
let latestSummaryRequest = 0
async function loadDeliveries() {
  const requestId = ++latestDeliveryRequest
  loading.value = true
  error.value = null
  deliveries.value = []
  totalItems.value = 0
  try {
    const filters = view.value === 'skipped' ? { status: 'completed', policy_decision: 'skipped' } : view.value === 'all' ? {} : { status: view.value }
    const result = await client.operations.deliveries({ page: page.value, list_rows: itemsPerPage.value, queue_name: 'notifications', ...filters })
    if (requestId !== latestDeliveryRequest) return
    deliveries.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
  } catch (cause) {
    if (requestId === latestDeliveryRequest) error.value = isApiError(cause) ? cause.message : '无法加载通知处理记录'
  } finally { if (requestId === latestDeliveryRequest) loading.value = false }
}
async function loadSummary() {
  const requestId = ++latestSummaryRequest
  summaryError.value = null
  try { const result = await client.operations.deliverySummary(); if (requestId === latestSummaryRequest) summary.value = result }
  catch (cause) { if (requestId === latestSummaryRequest) summaryError.value = isApiError(cause) ? cause.message : '无法加载处理汇总' }
}
async function loadAll() { await Promise.all([loadDeliveries(), loadSummary()]) }
function syncRouteQuery() {
  const next = { ...route.query, view: view.value, page: String(page.value), list_rows: String(itemsPerPage.value) }
  if (route.query.view === next.view && route.query.page === next.page && route.query.list_rows === next.list_rows) return
  void router.replace({ query: next })
}
function resetTableView() { page.value = 1; itemsPerPage.value = 20 }
function setView(next: unknown) {
  const selected = parseView(next)
  if (selected === view.value) return
  view.value = selected
  page.value = 1
}
watch(() => [route.query.view, route.query.page, route.query.list_rows], () => {
  const nextView = parseView(route.query.view)
  const nextPage = queryInt(route.query.page, 1)
  const nextSize = queryInt(route.query.list_rows, 20)
  if (view.value !== nextView) view.value = nextView
  if (page.value !== nextPage) page.value = nextPage
  if (allowedPageSizes.includes(nextSize) && itemsPerPage.value !== nextSize) itemsPerPage.value = nextSize
})
let initialized = false
watch([view, page, itemsPerPage], () => {
  if (initialized) syncRouteQuery()
  initialized = true
  void loadDeliveries()
}, { immediate: true })
onMounted(() => void loadSummary())
</script>

<style scoped>
.delivery-filter-scroll { max-width: 100%; overflow-x: auto; }
.delivery-filter-scroll .v-btn-toggle { width: max-content; }
.delivery-error { display: inline-block; max-width: 36ch; overflow-wrap: anywhere; }
</style>
