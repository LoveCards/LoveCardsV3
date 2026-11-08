<template>
  <AdminPageContainer>
    <AdminPageHeader title="审计日志" description="按动作、操作者、资源和时间核对操作记录。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>

    <v-alert v-if="queryWarning" class="mb-4" type="warning" variant="tonal">URL 中有无效筛选条件，已忽略。</v-alert>
    <AdminResourceTableShell
      v-model:visible-columns="visibleColumns"
      :columns="columnOptions"
      :result-count="totalItems"
      preference-key="operations-audit"
      @reset-view="resetTableView"
    >
      <template #toolbar>
        <v-alert v-if="filterError" class="mb-3" type="error" variant="tonal">{{ filterError }}</v-alert>
        <v-row dense>
          <v-col cols="12" md="4">
            <v-combobox v-model="draft.action" :items="actionSuggestions" clearable label="动作代码（前缀）" variant="outlined" @keydown.enter="applyFilters" />
          </v-col>
          <v-col cols="12" sm="6" md="4">
            <v-combobox v-model="draft.resourceType" :items="resourceTypeSuggestions" clearable label="资源类型" variant="outlined" @keydown.enter="applyFilters" />
          </v-col>
          <v-col cols="12" sm="6" md="4">
            <v-select v-model="draft.actorMode" :items="actorModes" label="操作者" variant="outlined" />
            <v-text-field v-if="draft.actorMode === 'id'" v-model="draft.actorId" class="mt-2" label="操作者 ID" inputmode="numeric" variant="outlined" @keydown.enter="applyFilters" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="toDateInput(draft.from)" label="开始时间（站点时间）" type="datetime-local" step="1" variant="outlined" @update:model-value="draft.from = fromDateInput($event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="toDateInput(draft.to)" label="结束前（站点时间）" type="datetime-local" step="1" variant="outlined" @update:model-value="draft.to = fromDateInput($event)" />
          </v-col>
        </v-row>
        <p class="mt-1 mb-0 text-caption text-medium-emphasis">时间按站点时区 Asia/Shanghai 查询；结束时间不包含所选时刻。</p>
      </template>
      <template #actions>
        <v-btn prepend-icon="mdi-filter-variant" variant="text" @click="advancedOpen = true">更多筛选{{ advancedCount ? `（${advancedCount}）` : '' }}</v-btn>
        <v-btn variant="text" @click="clearFilters">清除筛选</v-btn>
        <v-btn color="primary" variant="flat" :loading="loading" @click="applyFilters">查询</v-btn>
      </template>

      <v-alert v-if="error && logs.length" class="ma-3" type="error" variant="tonal">
        {{ error }}
        <template #append><v-btn variant="outlined" @click="load">重试</v-btn></template>
      </v-alert>
      <v-data-table-server
        :items-per-page="itemsPerPage"
        :page="page"
        :headers="headers"
        :items="logs"
        :items-length="totalItems"
        :items-per-page-options="allowedPageSizes"
        :loading="loading"
        density="comfortable"
        item-value="id"
        items-per-page-text="每页行数"
        @update:page="changePage"
        @update:items-per-page="changePageSize"
      >
        <template #item.action="{ item }"><strong class="audit-action" :title="item.action">{{ item.action }}</strong><div class="text-caption text-medium-emphasis">操作者 {{ item.actor_id ? `#${item.actor_id}` : '系统或匿名' }}</div></template>
        <template #item.resource="{ item }"><AdminResourceLink v-if="item.resource_type" :resource-type="item.resource_type" :resource-id="item.resource_id" /><span v-else>—</span></template>
        <template #item.trace="{ item }"><code class="audit-code">{{ item.request_id || '—' }}</code><div class="audit-code text-caption text-medium-emphasis">{{ item.correlation_id || '—' }}</div></template>
        <template #item.created_at="{ item }"><time :datetime="item.created_at" :title="item.created_at">{{ item.created_at.slice(0, 19) }}</time></template>
        <template #item.detail="{ item }">
          <v-tooltip text="查看审计详情" location="top">
            <template #activator="{ props }">
              <v-btn v-bind="props" :aria-label="`查看审计记录 #${item.id}`" :to="`/apps/operations/audit/${item.id}`" icon="mdi-open-in-new" variant="text" />
            </template>
          </v-tooltip>
        </template>
        <template #no-data>
          <v-empty-state :icon="error ? 'mdi-cloud-alert-outline' : 'mdi-clipboard-text-clock-outline'" :title="error ? '无法加载审计日志' : '没有匹配的审计记录'" :text="error || '调整查询条件后重试。'">
            <template #actions><v-btn v-if="error" variant="outlined" @click="load">重试</v-btn><v-btn v-else variant="text" @click="clearFilters">清除筛选</v-btn></template>
          </v-empty-state>
        </template>
      </v-data-table-server>
    </AdminResourceTableShell>

    <v-dialog v-model="advancedOpen" aria-label="更多审计筛选" max-width="600">
      <v-card prepend-icon="mdi-filter-outline" title="更多筛选">
        <v-card-text class="pt-4">
          <v-alert v-if="filterError" class="mb-4" type="error" variant="tonal">{{ filterError }}</v-alert>
          <v-text-field v-model="draft.resourceId" clearable label="资源 ID（精确匹配）" variant="outlined" />
          <v-text-field v-model="draft.requestId" clearable label="Request ID（精确匹配）" variant="outlined" />
          <v-text-field v-model="draft.correlationId" clearable label="Correlation ID（精确匹配）" variant="outlined" />
          <p class="text-body-2 text-medium-emphasis">这些标识用于定位一项资源或同一次请求，不搜索审计详情内容。</p>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="advancedOpen = false">返回列表</v-btn>
          <v-btn color="primary" variant="flat" @click="applyFilters">应用查询</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsAuditLog } from '@lovecards/sdk'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import { useApi, isApiError } from '~/lib/api'

type AuditFilters = { action: string; actor: string; resource_type: string; resource_id: string; request_id: string; correlation_id: string; from: string; to: string }
type AuditDraft = { action: string; actorMode: 'all' | 'system' | 'id'; actorId: string; resourceType: string; resourceId: string; requestId: string; correlationId: string; from: string; to: string }

const client = useApi()
const route = useRoute()
const router = useRouter()
const logs = ref<OperationsAuditLog[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const filterError = ref('')
const advancedOpen = ref(false)
const totalItems = ref(0)
const allowedPageSizes = [10, 20, 30, 50]
const filterKeys = ['action', 'actor', 'resource_type', 'resource_id', 'request_id', 'correlation_id', 'from', 'to'] as const
const actionSuggestions = ['auth.recovery.request', 'auth.recovery.failed', 'auth.recovery.reset', 'auth.session.revoke', 'communications.policies.update']
const resourceTypeSuggestions = ['user', 'card', 'comment', 'security_challenge', 'auth_session', 'notification_policies']
const actorModes = [
  { title: '全部操作者', value: 'all' },
  { title: '系统或匿名', value: 'system' },
  { title: '指定 ID', value: 'id' },
]
const emptyDraft = (): AuditDraft => ({ action: '', actorMode: 'all', actorId: '', resourceType: '', resourceId: '', requestId: '', correlationId: '', from: '', to: '' })
const draft = reactive<AuditDraft>(emptyDraft())
const advancedCount = computed(() => [draft.resourceId, draft.requestId, draft.correlationId].filter(Boolean).length)

const allHeaders = [
  { title: '动作', key: 'action', minWidth: 270 }, { title: '资源', key: 'resource' }, { title: 'Request / Correlation ID', key: 'trace', minWidth: 270 },
  { title: '时间', key: 'created_at' }, { title: '详情', key: 'detail' },
].map((header) => ({ ...header, sortable: false }))
const visibleColumns = ref(allHeaders.map((header) => header.key))
const headers = computed(() => allHeaders.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map((header) => ({ title: header.title, key: header.key, required: header.key === 'action' || header.key === 'detail' }))

const queryValue = (value: unknown) => typeof value === 'string' ? value : ''
const queryInt = (value: unknown, fallback: number, max: number) => {
  const number = Number(queryValue(value))
  return Number.isInteger(number) && number > 0 && number <= max ? number : fallback
}
const validDateTime = (value: string) => {
  if (!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)) return false
  const [date, time] = value.split(' ')
  if (!date || !time) return false
  const [year, month, day] = date.split('-').map(Number)
  const [hour, minute, second] = time.split(':').map(Number)
  const actual = new Date(Date.UTC(year!, month! - 1, day!))
  return actual.getUTCFullYear() === year && actual.getUTCMonth() + 1 === month && actual.getUTCDate() === day
    && hour! < 24 && minute! < 60 && second! < 60
}
const readRoute = () => {
  const filters: Partial<AuditFilters> = {}
  let invalid = false
  for (const key of filterKeys) {
    const raw = route.query[key]
    if (raw == null) continue
    const value = queryValue(raw)
    const valid = key === 'action' ? value.length <= 128 && /^[A-Za-z][A-Za-z0-9._:-]*$/.test(value)
      : key === 'actor' ? value === 'system' || (/^[1-9]\d{0,9}$/.test(value) && Number(value) <= 2147483647)
        : key === 'resource_type' ? value.length <= 64 && /^[A-Za-z][A-Za-z0-9._:-]*$/.test(value)
          : key === 'from' || key === 'to' ? validDateTime(value)
            : value.trim().length > 0 && value.length <= 128
    if (valid) filters[key] = value
    else invalid = true
  }
  if (filters.from && filters.to && filters.from >= filters.to) {
    delete filters.from
    delete filters.to
    invalid = true
  }
  const rawPage = queryInt(route.query.page, 1, 1000000)
  const rawSize = queryInt(route.query.list_rows, 30, 100)
  if (route.query.page != null && String(rawPage) !== route.query.page) invalid = true
  if (route.query.list_rows != null && !allowedPageSizes.includes(rawSize)) invalid = true
  return { filters, page: rawPage, size: allowedPageSizes.includes(rawSize) ? rawSize : 30, invalid }
}
const routeState = computed(readRoute)
const page = computed(() => routeState.value.page)
const itemsPerPage = computed(() => routeState.value.size)
const queryWarning = computed(() => routeState.value.invalid)

const toDateInput = (value: string) => value.replace(' ', 'T')
const fromDateInput = (value: unknown) => typeof value === 'string' && value ? value.replace('T', ' ').replace(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})$/, '$1:00') : ''
function syncDraft() {
  const filters = routeState.value.filters
  Object.assign(draft, emptyDraft(), {
    action: filters.action || '',
    actorMode: filters.actor === 'system' ? 'system' : filters.actor ? 'id' : 'all',
    actorId: filters.actor && filters.actor !== 'system' ? filters.actor : '',
    resourceType: filters.resource_type || '',
    resourceId: filters.resource_id || '',
    requestId: filters.request_id || '',
    correlationId: filters.correlation_id || '',
    from: filters.from || '',
    to: filters.to || '',
  })
}
function navigationQuery(pageNumber: number, size: number, filters = routeState.value.filters) {
  const query = { ...route.query }
  for (const key of filterKeys) delete query[key]
  Object.assign(query, filters)
  query.page = String(pageNumber)
  query.list_rows = String(size)
  return query
}
function changePage(next: number) { if (next !== page.value) void router.push({ query: navigationQuery(next, itemsPerPage.value), hash: route.hash }) }
function changePageSize(next: number) { if (next !== itemsPerPage.value) void router.push({ query: navigationQuery(1, next), hash: route.hash }) }
function applyFilters() {
  filterError.value = ''
  const filters: Partial<AuditFilters> = {}
  const action = String(draft.action || '').trim()
  const resourceType = String(draft.resourceType || '').trim()
  if (action) {
    if (action.length > 128 || !/^[A-Za-z][A-Za-z0-9._:-]*$/.test(action)) return void (filterError.value = '动作代码格式无效。')
    filters.action = action
  }
  if (resourceType) {
    if (resourceType.length > 64 || !/^[A-Za-z][A-Za-z0-9._:-]*$/.test(resourceType)) return void (filterError.value = '资源类型格式无效。')
    filters.resource_type = resourceType
  }
  if (draft.actorMode === 'system') filters.actor = 'system'
  if (draft.actorMode === 'id') {
    const actorId = draft.actorId.trim()
    if (!/^[1-9]\d{0,9}$/.test(actorId) || Number(actorId) > 2147483647) return void (filterError.value = '请输入有效的操作者 ID。')
    filters.actor = actorId
  }
  for (const [key, value] of [['resource_id', draft.resourceId], ['request_id', draft.requestId], ['correlation_id', draft.correlationId]] as const) {
    if (!value) continue
    const trimmed = value.trim()
    if (!trimmed || trimmed.length > 128) return void (filterError.value = '资源或请求标识不能为空且最多 128 个字符。')
    filters[key] = trimmed
  }
  for (const key of ['from', 'to'] as const) {
    if (!draft[key]) continue
    if (!validDateTime(draft[key])) return void (filterError.value = '请选择有效的站点时间。')
    filters[key] = draft[key]
  }
  if (filters.from && filters.to && filters.from >= filters.to) return void (filterError.value = '结束时间必须晚于开始时间。')
  advancedOpen.value = false
  void router.push({ query: navigationQuery(1, itemsPerPage.value, filters), hash: route.hash })
}
function clearFilters() {
  Object.assign(draft, emptyDraft())
  filterError.value = ''
  advancedOpen.value = false
  void router.push({ query: navigationQuery(1, itemsPerPage.value, {}), hash: route.hash })
}
function resetTableView() { void router.push({ query: navigationQuery(1, 30), hash: route.hash }) }

let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  loading.value = true
  error.value = null
  const { filters, page: currentPage, size } = routeState.value
  try {
    const result = await client.operations.auditLogs({ page: currentPage, list_rows: size, ...filters })
    if (requestId !== latestRequest) return
    logs.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
  } catch (cause) {
    if (requestId === latestRequest) error.value = isApiError(cause) ? cause.message : '无法加载审计日志'
  } finally {
    if (requestId === latestRequest) loading.value = false
  }
}
watch(() => route.fullPath, () => { syncDraft(); void load() }, { immediate: true })
</script>

<style scoped>
.audit-action { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.audit-code { overflow-wrap: anywhere; }
</style>
