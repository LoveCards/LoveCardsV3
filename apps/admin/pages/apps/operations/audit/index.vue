<template>
  <AdminPageContainer>
    <AdminPageHeader title="审计日志" description="按时间核对高风险操作、目标资源和端到端请求标识。" :breadcrumbs="[{ title: '系统运维' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading && !logs.length" :error="error && !logs.length ? error : null" :empty="!logs.length" @retry="load">
      <v-alert v-if="error" class="mb-3" type="error" variant="tonal">
        {{ error }}
        <template #append><v-btn variant="outlined" @click="load">重试</v-btn></template>
      </v-alert>
      <AdminResourceTableShell
        v-model:visible-columns="visibleColumns"
        :columns="columnOptions"
        :result-count="totalItems"
        preference-key="operations-audit"
        @reset-view="resetTableView"
      >
        <v-data-table-server
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="logs"
          :items-length="totalItems"
          :items-per-page-options="[10, 20, 30, 50]"
          :loading="loading"
          density="comfortable"
          item-value="id"
          items-per-page-text="每页行数"
        >
          <template #item.action="{ item }"><strong>{{ item.action }}</strong><div class="text-caption text-medium-emphasis">操作者 {{ item.actor_id ? `#${item.actor_id}` : '系统' }}</div></template>
          <template #item.resource="{ item }"><AdminResourceLink v-if="item.resource_type" :resource-type="item.resource_type" :resource-id="item.resource_id" /><span v-else>-</span></template>
          <template #item.trace="{ item }"><code>{{ item.request_id || '-' }}</code><div class="text-caption text-medium-emphasis">{{ item.correlation_id || '-' }}</div></template>
          <template #item.detail="{ item }">
            <v-tooltip text="查看审计详情" location="top">
              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  :aria-label="`查看审计记录 #${item.id}`"
                  :to="`/apps/operations/audit/${item.id}`"
                  icon="mdi-open-in-new"
                  variant="text"
                />
              </template>
            </v-tooltip>
          </template>
        </v-data-table-server>
      </AdminResourceTableShell>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsAuditLog } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const route = useRoute()
const router = useRouter()
const logs = ref<OperationsAuditLog[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const allowedPageSizes = [10, 20, 30, 50]
const queryInt = (value: unknown, fallback: number) => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback
}
const page = ref(queryInt(route.query.page, 1))
const requestedPageSize = queryInt(route.query.list_rows, 30)
const itemsPerPage = ref(allowedPageSizes.includes(requestedPageSize) ? requestedPageSize : 30)
const totalItems = ref(0)
const allHeaders = [
  { title: '动作', key: 'action' }, { title: '资源', key: 'resource' }, { title: 'Request / Correlation ID', key: 'trace' },
  { title: '时间', key: 'created_at' }, { title: '详情', key: 'detail', sortable: false },
].map((header) => ({ ...header, sortable: false }))
const visibleColumns = ref(allHeaders.map((header) => header.key))
const headers = computed(() => allHeaders.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map((header) => ({
  title: header.title,
  key: header.key,
  required: header.key === 'action' || header.key === 'detail',
}))
let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  loading.value = true; error.value = null
  try {
    const result = await client.operations.auditLogs({ page: page.value, list_rows: itemsPerPage.value })
    if (requestId !== latestRequest) return
    logs.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
  }
  catch (cause) { if (requestId === latestRequest) error.value = isApiError(cause) ? cause.message : '无法加载审计日志' }
  finally { if (requestId === latestRequest) loading.value = false }
}
function syncRouteQuery() {
  const next = { ...route.query, page: String(page.value), list_rows: String(itemsPerPage.value) }
  if (route.query.page === next.page && route.query.list_rows === next.list_rows) return
  void router.replace({ query: next })
}
function resetTableView() { page.value = 1; itemsPerPage.value = 30 }
watch(() => [route.query.page, route.query.list_rows], () => {
  const nextPage = queryInt(route.query.page, 1)
  const nextSize = queryInt(route.query.list_rows, 30)
  if (page.value !== nextPage) page.value = nextPage
  if (allowedPageSizes.includes(nextSize) && itemsPerPage.value !== nextSize) itemsPerPage.value = nextSize
})
watch([page, itemsPerPage], () => { syncRouteQuery(); void load() }, { immediate: true })
</script>
