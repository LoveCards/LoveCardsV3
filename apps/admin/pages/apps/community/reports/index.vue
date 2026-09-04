<template>
  <AdminPageContainer>
    <AdminPageHeader title="举报处理" description="按状态审阅举报，处理动作会记录原因与操作者。" :breadcrumbs="[{ title: '内容与社区' }, { title: '社区治理' }, { title: '举报处理' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <div class="d-flex flex-wrap ga-3 mb-4">
      <v-select v-model="status" :items="statusItems" clearable density="comfortable" label="状态" max-width="240" variant="outlined" @update:model-value="resetAndLoad" />
    </div>
    <AdminAsyncState :loading="loading && !reports.length" :error="error && !reports.length ? error : null" :empty="!reports.length" @retry="load">
      <v-alert v-if="error" class="mb-3" type="error" variant="tonal">
        {{ error }}
        <template #append><v-btn variant="outlined" @click="load">重试</v-btn></template>
      </v-alert>
      <v-sheet border rounded="lg">
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
          <template #item.target="{ item }"><AdminResourceLink :resource-type="item.target_type" :resource-id="item.target_id" /></template>
          <template #item.status="{ item }"><AdminStatusChip :status="item.status" /></template>
          <template #item.created_at="{ item }"><time>{{ item.created_at }}</time></template>
        </v-data-table-server>
      </v-sheet>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { AdminReport } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const route = useRoute()
const router = useRouter()
const reports = ref<AdminReport[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const itemsPerPage = ref(20)
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
const headers = [
  { title: '编号', key: 'id' }, { title: '举报人', key: 'reporter_id' }, { title: '目标', key: 'target' },
  { title: '原因', key: 'reason' }, { title: '状态', key: 'status' }, { title: '创建时间', key: 'created_at' },
].map((header) => ({ ...header, sortable: false }))
let latestRequest = 0

async function load() {
  const requestId = ++latestRequest
  loading.value = true
  error.value = null
  try {
    const result = await client.reports.listManaged({ page: page.value, list_rows: itemsPerPage.value, ...(status.value ? { status: status.value } : {}) })
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
function syncRouteQuery() {
  const query = { ...route.query }
  query.page = String(page.value)
  query.list_rows = String(itemsPerPage.value)
  if (status.value) query.status = status.value
  else delete query.status
  void router.replace({ query, hash: route.hash })
}
watch([page, itemsPerPage], () => {
  syncRouteQuery()
  void load()
}, { immediate: true })
</script>
