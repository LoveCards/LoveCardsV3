<template>
  <AdminPageContainer>
    <AdminPageHeader title="举报处理" description="按状态审阅举报，处理动作会记录原因与操作者。" :breadcrumbs="[{ title: '内容' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminResourceTableShell v-model:visible-columns="visibleColumns" :columns="columnOptions" :default-columns="defaultColumns" :result-count="totalItems" preference-key="community-reports" @reset-view="resetTableView">
      <template #search>
        <v-text-field v-model="searchValue" clearable label="搜索原因或补充说明" prepend-inner-icon="mdi-magnify" @keydown.enter="resetAndLoad" @click:clear="resetAndLoad" />
      </template>
      <template #actions>
        <v-select v-model="status" class="report-filter" :items="statusItems" clearable label="状态" @update:model-value="resetAndLoad" />
        <v-select v-model="resourceType" class="report-filter" :items="resourceTypeItems" clearable label="目标类型" @update:model-value="resetAndLoad" />
        <v-btn variant="text" @click="clearFilters">清除筛选</v-btn>
      </template>
      <v-alert v-if="error && reports.length" class="ma-3" type="error" variant="tonal">
        {{ error }}
        <template #append><v-btn variant="outlined" @click="load">重试</v-btn></template>
      </v-alert>
      <v-data-table-server
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
          <template #item.id="{ item }"><v-btn :to="`/apps/community/reports/${item.id}`" variant="text">#{{ item.id }}</v-btn></template>
          <template #item.reporter_id="{ item }"><AdminResourceLink :resource-type="'user'" :resource-id="item.reporter_id" /></template>
          <template #item.target="{ item }"><AdminResourceLink :resource-type="item.target_type" :resource-id="item.target_id" /></template>
          <template #item.status="{ item }"><AdminStatusChip :status="item.status" /></template>
          <template #item.created_at="{ item }"><time>{{ item.created_at }}</time></template>
          <template #no-data>
            <v-empty-state :icon="error ? 'mdi-cloud-alert-outline' : 'mdi-flag-outline'" :title="error ? '无法加载举报' : '没有匹配的举报'" :text="error || '调整关键词或筛选条件后重试。'">
              <template #actions><v-btn v-if="error" variant="outlined" @click="load">重试</v-btn><v-btn v-else variant="text" @click="clearFilters">清除筛选</v-btn></template>
            </v-empty-state>
          </template>
      </v-data-table-server>
    </AdminResourceTableShell>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { AdminReport } from '@lovecards/sdk'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'

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
const allHeaders = [
  { title: '编号', key: 'id' }, { title: '举报人', key: 'reporter_id' }, { title: '目标', key: 'target' },
  { title: '原因', key: 'reason' }, { title: '状态', key: 'status' }, { title: '创建时间', key: 'created_at' },
].map((header) => ({ ...header, sortable: false }))
const defaultColumns = allHeaders.map((header) => header.key)
const visibleColumns = ref([...defaultColumns])
const headers = computed(() => allHeaders.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map((header) => ({ title: header.title, key: header.key, required: header.key === 'id' }))
let latestRequest = 0

async function load() {
  const requestId = ++latestRequest
  loading.value = true
  error.value = null
  try {
    const result = await client.reports.listManaged({
      page: page.value,
      list_rows: itemsPerPage.value,
      ...(status.value ? { status: status.value } : {}),
      ...(resourceType.value ? { resource_type: resourceType.value } : {}),
      ...(searchValue.value.trim() ? { search_value: searchValue.value.trim() } : {}),
    })
    if (requestId !== latestRequest) return
    reports.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
  } catch (cause) { if (requestId === latestRequest) error.value = isApiError(cause) ? cause.message : '无法加载举报列表' }
  finally { if (requestId === latestRequest) loading.value = false }
}
function resetAndLoad() {
  syncRouteQuery()
  if (page.value === 1) void load()
  else page.value = 1
}
function clearFilters() {
  status.value = null
  resourceType.value = null
  searchValue.value = ''
  resetAndLoad()
}
function resetTableView() {
  visibleColumns.value = [...defaultColumns]
  itemsPerPage.value = 20
  clearFilters()
}
function syncRouteQuery() {
  const query = { ...route.query }
  query.page = String(page.value)
  query.list_rows = String(itemsPerPage.value)
  if (status.value) query.status = status.value
  else delete query.status
  if (resourceType.value) query.resource_type = resourceType.value
  else delete query.resource_type
  if (searchValue.value.trim()) query.search_value = searchValue.value.trim()
  else delete query.search_value
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
