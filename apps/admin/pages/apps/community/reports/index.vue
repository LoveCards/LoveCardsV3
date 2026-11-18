<template>
  <AdminPageContainer fluid>
    <AdminPageHeader title="举报处理" description="按状态审阅举报，处理动作会记录原因与操作者。" :breadcrumbs="[{ title: '内容' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminResourceTableShell v-model:visible-columns="visibleColumns" v-model:action-capacity="actionCapacity" :action-headers="headers" :action-labels="['查看举报', '处理举报']" :columns="columnOptions" :default-columns="defaultColumns" :result-count="lastSuccessfulKey ? totalItems : null" :result-state="loading && lastSuccessfulKey ? 'loading' : resultState" :view-query-keys="['search_value', 'status', 'resource_type', 'list_rows']" preference-key="community-reports" @apply-view="applySavedView" @reset-view="resetTableView">
      <template #search>
        <v-text-field v-model="searchValue" clearable label="搜索原因或补充说明" prepend-inner-icon="mdi-magnify" @keydown.enter="applySearch" @click:clear="clearSearch" />
      </template>
      <template #actions>
        <v-select v-model="status" class="report-filter" :items="statusItems" clearable label="状态" @update:model-value="resetAndLoad" />
        <v-select v-model="resourceType" class="report-filter" :items="resourceTypeItems" clearable label="目标类型" @update:model-value="resetAndLoad" />
        <v-btn v-if="hasFilters" variant="text" @click="clearFilters">清除筛选</v-btn>
      </template>
      <v-alert v-if="error && reports.length" class="ma-3" type="error" variant="tonal">
        {{ error }}
        <template #append><v-btn variant="outlined" @click="load">重试</v-btn></template>
      </v-alert>
      <v-data-table-server
          class="report-table admin-table-no-sort"

          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="reports"
          :items-length="totalItems"
          :items-per-page-options="[10, 20, 50]"
          :loading="loading"
          density="comfortable"
          item-value="id"
          items-per-page-text="每页行数"
        >
          <template #item.id="{ item }"><span class="text-medium-emphasis">#{{ item.id }}</span></template>
          <template #item.reporter_id="{ item }">用户 #{{ item.reporter_id }}</template>
          <template #item.target="{ item }">{{ targetLabel(item.target_type) }} #{{ item.target_id }}</template>
          <template #item.status="{ item }"><AdminStatusChip :status="item.status" /></template>
          <template #item.created_at="{ item }"><AdminTableTime :value="item.created_at" /></template>
          <template #item.reason="{ item }"><AdminTableSearchHighlight :text="item.reason" :query="appliedSearch" /></template>
          <template #item.action="{ item }">
            <AdminTableRowActions :actions="reportRowActions(item)" :capacity="actionCapacity" more-label="更多举报操作" />
          </template>
          <template #no-data>
            <v-empty-state :icon="error ? 'mdi-cloud-alert-outline' : 'mdi-flag-outline'" :title="error ? '无法加载举报' : '没有匹配的举报'" :text="error || '调整关键词或筛选条件后重试。'">
              <template #actions><v-btn v-if="error" variant="outlined" @click="load">重试</v-btn><v-btn v-else-if="hasFilters" variant="text" @click="clearFilters">清除筛选</v-btn></template>
            </v-empty-state>
          </template>
      </v-data-table-server>
    </AdminResourceTableShell>
  </AdminPageContainer>
  <ReportDetailDialog v-model="detailOpen" :report-id="detailId" :initial-mode="detailMode" @updated="load" />
</template>

<script setup lang="ts">
import type { AdminReport } from '@lovecards/sdk'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import AdminTableRowActions from '~/components/shared/admin/AdminTableRowActions.vue'
import ReportDetailDialog from '~/components/reports/ReportDetailDialog.vue'
import { useUserStore } from '~/stores/userStore'
import { useApi, isApiError } from '~/lib/api'

const userStore = useUserStore()
const actionCapacity = ref(1)
const detailOpen = ref(false)
const detailId = ref<number | null>(null)
const detailMode = ref<'view' | 'process'>('view')
const canManage = computed(() => userStore.hasGrant('reports.manage', 'global'))
const canWrite = computed(() => !loading.value && resultState.value === 'ready')
function openDetail(id: number, mode: 'view' | 'process') {
  detailId.value = id
  detailMode.value = mode
  detailOpen.value = true
}
const reportRowActions = (item: AdminReport) => [
  { key: 'view', label: '查看举报', onClick: () => openDetail(item.id, 'view') },
  ...(canManage.value && item.status !== 'closed'
    ? [{ key: 'process', label: '处理举报', disabled: !canWrite.value, onClick: () => openDetail(item.id, 'process') }]
    : []),
]
const client = useApi()
const route = useRoute()
const router = useRouter()
const reports = ref<AdminReport[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const queryInt = (value: unknown, fallback: number) => {
  const number = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN
  return Number.isInteger(number) && number > 0 ? number : fallback
}
const page = ref(queryInt(route.query.page, 1))
const requestedPageSize = queryInt(route.query.list_rows, 20)
const itemsPerPage = ref([10, 20, 50].includes(requestedPageSize) ? requestedPageSize : 20)
const totalItems = ref(0)
const lastSuccessfulKey = ref<string | null>(null)
const resultState = ref<'ready' | 'stale'>('ready')
const statusItems = [
  { title: '待处理', value: 'open' }, { title: '处理中', value: 'reviewing' },
  { title: '已解决', value: 'resolved' }, { title: '已驳回', value: 'rejected' }, { title: '已关闭', value: 'closed' },
]
const status = ref<string | null>(
  typeof route.query.status === 'string' && statusItems.some((item) => item.value === route.query.status)
    ? route.query.status
    : null,
)
const resourceTypeItems = [
  { title: '卡片', value: 'card' }, { title: '评论', value: 'comment' }, { title: '用户', value: 'user' },
  { title: '私信', value: 'message' }, { title: '文件', value: 'file' },
]
const resourceType = ref<string | null>(
  typeof route.query.resource_type === 'string' && resourceTypeItems.some((item) => item.value === route.query.resource_type)
    ? route.query.resource_type
    : null,
)
const searchValue = ref(typeof route.query.search_value === 'string' ? route.query.search_value : '')
const appliedSearch = ref(searchValue.value.trim())
const hasFilters = computed(() => Boolean(appliedSearch.value || status.value || resourceType.value))
const targetLabel = (type: string) => resourceTypeItems.find((item) => item.value === type)?.title ?? '目标'
const allHeaders = [
  { title: '编号', key: 'id', minWidth: 88 }, { title: '举报人', key: 'reporter_id', minWidth: 128 }, { title: '目标', key: 'target', minWidth: 144 },
  { title: '原因', key: 'reason', minWidth: 280 }, { title: '状态', key: 'status', minWidth: 112 }, { title: '创建时间', key: 'created_at', minWidth: 176 },
  { title: '操作', key: 'action', minWidth: 112 },
].map((header) => ({ ...header, sortable: false }))
const defaultColumns = allHeaders.map((header) => header.key)
const visibleColumns = ref([...defaultColumns])
const headers = computed(() => allHeaders.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map((header) => ({ title: header.title, key: header.key, required: header.key === 'id' || header.key === 'action' }))
let latestRequest = 0

async function load() {
  const requestId = ++latestRequest
  const params = {
    page: page.value,
    list_rows: itemsPerPage.value,
    ...(status.value ? { status: status.value } : {}),
    ...(resourceType.value ? { resource_type: resourceType.value } : {}),
    ...(appliedSearch.value ? { search_value: appliedSearch.value } : {}),
  }
  const key = JSON.stringify(params)
  const refreshing = key === lastSuccessfulKey.value
  if (!refreshing) {
    reports.value = []
    totalItems.value = 0
    lastSuccessfulKey.value = null
  }
  loading.value = true
  error.value = null
  try {
    const result = await client.reports.listManaged(params)
    if (requestId !== latestRequest) return
    reports.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
    lastSuccessfulKey.value = key
    resultState.value = 'ready'
  } catch (cause) {
    if (requestId !== latestRequest) return
    error.value = isApiError(cause) ? cause.message : '无法加载举报列表'
    resultState.value = 'stale'
  } finally {
    if (requestId === latestRequest) loading.value = false
  }
}
function resetAndLoad() {
  if (page.value === 1) {
    syncRouteQuery()
    void load()
  } else page.value = 1
}
function applySearch() {
  appliedSearch.value = searchValue.value.trim()
  resetAndLoad()
}
function clearSearch() {
  searchValue.value = ''
  appliedSearch.value = ''
  resetAndLoad()
}
function clearFilters() {
  status.value = null
  resourceType.value = null
  searchValue.value = ''
  appliedSearch.value = ''
  resetAndLoad()
}
function applySavedView(query: Record<string, string | null | (string | null)[]>) {
  const nextStatus = query.status
  const nextType = query.resource_type
  status.value = typeof nextStatus === 'string' && statusItems.some((item) => item.value === nextStatus) ? nextStatus : null
  resourceType.value = typeof nextType === 'string' && resourceTypeItems.some((item) => item.value === nextType) ? nextType : null
  searchValue.value = typeof query.search_value === 'string' ? query.search_value : ''
  appliedSearch.value = searchValue.value.trim()
  const nextSize = queryInt(query.list_rows, 20)
  itemsPerPage.value = [10, 20, 50].includes(nextSize) ? nextSize : 20
  resetAndLoad()
}
function resetTableView() {
  visibleColumns.value = [...defaultColumns]
  itemsPerPage.value = 20
  clearFilters()
}
function syncRouteQuery() {
  const query: Record<string, string> = { page: String(page.value), list_rows: String(itemsPerPage.value) }
  if (status.value) query.status = status.value
  if (resourceType.value) query.resource_type = resourceType.value
  if (appliedSearch.value) query.search_value = appliedSearch.value
  void router.replace({ query, hash: route.hash })
}
watch([page, itemsPerPage], () => {
  syncRouteQuery()
  void load()
}, { immediate: true })
</script>

<style scoped>
.report-filter { min-width: 150px; max-width: 180px; }
</style>
