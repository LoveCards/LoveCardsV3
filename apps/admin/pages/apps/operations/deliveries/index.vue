<template>
  <AdminPageContainer>
    <AdminPageHeader title="通知投递" description="查看 Outbox 投递状态与失败原因，并对可恢复的失败执行人工重试。" :breadcrumbs="[{ title: '运维与审计' }, { title: '通知投递' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="loadAll">刷新</v-btn></template>
    </AdminPageHeader>
    <v-sheet v-if="stats" class="mb-4" border rounded="lg" overflow-hidden>
      <v-list lines="one">
        <v-list-item prepend-icon="mdi-bell-outline" title="通知总量">
          <template #append><strong class="text-h6">{{ stats.total }}</strong></template>
        </v-list-item>
        <v-divider />
        <v-list-item prepend-icon="mdi-bell-badge-outline" title="未读通知">
          <template #append><strong class="text-h6">{{ stats.unread }}</strong></template>
        </v-list-item>
        <v-divider />
        <v-list-item prepend-icon="mdi-account-multiple-outline" title="覆盖用户">
          <template #append><strong class="text-h6">{{ stats.recipients }}</strong></template>
        </v-list-item>
      </v-list>
    </v-sheet>
    <v-alert v-if="statsError" class="mb-4" type="warning" variant="tonal">
      通知汇总暂时不可用：{{ statsError }}
      <template #append><v-btn variant="outlined" @click="loadStats">重试</v-btn></template>
    </v-alert>
    <AdminAsyncState :loading="loading && !deliveries.length" :error="error && !deliveries.length ? error : null" :empty="!deliveries.length" @retry="loadDeliveries">
      <v-alert v-if="error" class="mb-3" type="error" variant="tonal">
        {{ error }}
        <template #append><v-btn variant="outlined" @click="loadDeliveries">重试</v-btn></template>
      </v-alert>
      <AdminResourceTableShell
        v-model:visible-columns="visibleColumns"
        :columns="columnOptions"
        :result-count="totalItems"
        preference-key="operations-deliveries"
        @reset-view="resetTableView"
      >
        <v-data-table-server
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="deliveries"
          :items-length="totalItems"
          :items-per-page-options="[10, 20, 50]"
          :loading="loading"
          density="comfortable"
          item-value="id"
          items-per-page-text="每页行数"
        >
          <template #item.event="{ item }">
            <AdminResourceLink
              :label="item.event_type"
              resource-type="event_delivery"
              :resource-id="item.id"
            />
            <div class="text-caption text-medium-emphasis">事件 #{{ item.event_id }} · {{ item.handler_key }}</div>
          </template>
          <template #item.status="{ item }"><AdminStatusChip :status="item.status" /></template>
          <template #item.error="{ item }"><span class="delivery-error">{{ item.last_error || '-' }}</span></template>
          <template #item.action="{ item }">
            <AdminActionGuard capability="operations.retry">
              <v-tooltip v-if="item.status === 'dead'" text="重试投递" location="top">
                <template #activator="{ props }">
                  <v-btn v-bind="props" aria-label="重试投递" color="primary" icon="mdi-refresh" variant="text" @click="openRetry(item)" />
                </template>
              </v-tooltip>
            </AdminActionGuard>
          </template>
        </v-data-table-server>
      </AdminResourceTableShell>
    </AdminAsyncState>
    <AdminDangerActionDialog v-model="retryDialog" title="重试事件投递" action-label="加入重试队列" :resource-label="selected ? `${selected.event_type} #${selected.event_id}` : ''" impact="该处理器将再次消费事件，业务处理器必须保持幂等。" :loading="retrying" :error="actionError" @confirm="handleRetry" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsDelivery, OperationsNotificationStats } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'

const client = useApi()
const route = useRoute()
const router = useRouter()
const notifier = useAdminNotifier()
const deliveries = ref<OperationsDelivery[]>([])
const stats = ref<OperationsNotificationStats | null>(null)
const loading = ref(false)
const retrying = ref(false)
const error = ref<string | null>(null)
const statsError = ref<string | null>(null)
const actionError = ref('')
const allowedPageSizes = [10, 20, 50]
const queryInt = (value: unknown, fallback: number) => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback
}
const page = ref(queryInt(route.query.page, 1))
const requestedPageSize = queryInt(route.query.list_rows, 20)
const itemsPerPage = ref(allowedPageSizes.includes(requestedPageSize) ? requestedPageSize : 20)
const totalItems = ref(0)
const selected = ref<OperationsDelivery | null>(null)
const retryDialog = ref(false)
const allHeaders = [
  { title: '事件', key: 'event' }, { title: '队列', key: 'queue_name' }, { title: '执行模式', key: 'execution_mode' },
  { title: '状态', key: 'status' }, { title: '尝试', key: 'attempt_count' }, { title: '最后错误', key: 'error' },
  { title: '更新时间', key: 'updated_at' }, { title: '操作', key: 'action', sortable: false },
].map((header) => ({ ...header, sortable: false }))
const visibleColumns = ref(allHeaders.map((header) => header.key))
const headers = computed(() => allHeaders.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map((header) => ({
  title: header.title,
  key: header.key,
  required: header.key === 'event' || header.key === 'action',
}))
let latestDeliveryRequest = 0
let latestStatsRequest = 0
async function loadDeliveries() {
  const requestId = ++latestDeliveryRequest
  loading.value = true
  error.value = null
  try {
    const result = await client.operations.deliveries({ page: page.value, list_rows: itemsPerPage.value })
    if (requestId !== latestDeliveryRequest) return
    deliveries.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
  } catch (cause) {
    if (requestId === latestDeliveryRequest) error.value = isApiError(cause) ? cause.message : '无法加载投递状态'
  } finally {
    if (requestId === latestDeliveryRequest) loading.value = false
  }
}
async function loadStats() {
  const requestId = ++latestStatsRequest
  statsError.value = null
  try {
    const result = await client.operations.notificationStats()
    if (requestId === latestStatsRequest) stats.value = result
  } catch (cause) {
    if (requestId === latestStatsRequest) statsError.value = isApiError(cause) ? cause.message : '无法加载通知汇总'
  }
}
async function loadAll() {
  await Promise.all([loadDeliveries(), loadStats()])
}
function openRetry(item: OperationsDelivery) { selected.value = item; actionError.value = ''; retryDialog.value = true }
async function retry() {
  if (!selected.value) return
  retrying.value = true; actionError.value = ''
  try { await client.operations.retryDelivery(selected.value.id); retryDialog.value = false; notifier.success('事件已加入重试队列'); await loadDeliveries() }
  catch (cause) { actionError.value = isApiError(cause) ? cause.message : '重试失败' }
  finally { retrying.value = false }
}
function handleRetry() { void retry() }
function syncRouteQuery() {
  const next = {
    ...route.query,
    page: String(page.value),
    list_rows: String(itemsPerPage.value),
  }
  if (route.query.page === next.page && route.query.list_rows === next.list_rows) return
  void router.replace({ query: next })
}
function resetTableView() {
  page.value = 1
  itemsPerPage.value = 20
}
watch(() => [route.query.page, route.query.list_rows], () => {
  const nextPage = queryInt(route.query.page, 1)
  const nextSize = queryInt(route.query.list_rows, 20)
  if (page.value !== nextPage) page.value = nextPage
  if (allowedPageSizes.includes(nextSize) && itemsPerPage.value !== nextSize) itemsPerPage.value = nextSize
})
watch([page, itemsPerPage], () => {
  syncRouteQuery()
  void loadDeliveries()
}, { immediate: true })
onMounted(() => void loadStats())
</script>

<style scoped>
.delivery-error { display: inline-block; max-width: 36ch; overflow-wrap: anywhere; }
</style>
