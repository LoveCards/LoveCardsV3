<template>
  <AdminPageContainer fluid>
    <AdminPageHeader title="站内通知" description="查询已生成的通知元数据；已读状态来自用户操作，后台查看不会改变它。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminResourceTableShell v-model:visible-columns="visibleColumns" :columns="columnOptions" :default-columns="defaultColumns" :result-count="resultCount" :result-state="resultState" :view-query-keys="filterKeys.concat('list_rows')" preference-key="operations-notifications" @reset-view="resetTableView" @apply-view="applySavedView">
      <template #toolbar>
        <v-form @submit.prevent="applyFilters">
          <v-row dense align="center">
            <v-col cols="12" sm="4" lg="2"><v-text-field v-model="draft.recipient_id" label="接收用户 ID" inputmode="numeric" clearable /></v-col>
            <v-col cols="12" sm="4" lg="2"><v-text-field v-model="draft.type" label="通知类型" placeholder="例如 content.commented" clearable /></v-col>
            <v-col cols="12" sm="4" lg="2"><v-select v-model="draft.read_state" :items="readStates" label="读取状态" clearable /></v-col>
            <v-col cols="12" sm="6" lg="3"><v-text-field v-model="draft.from" type="datetime-local" step="1" label="生成开始时间" clearable /></v-col>
            <v-col cols="12" sm="6" lg="3"><v-text-field v-model="draft.to" type="datetime-local" step="1" label="生成结束时间" clearable /></v-col>
          </v-row>
          <p class="text-caption text-medium-emphasis mt-2 mb-0">通知类型精确匹配。时间按服务端保存值筛选；结束时间不包含在结果内。</p>
          <div class="d-flex flex-wrap align-center ga-2 mt-3">
            <v-btn type="submit" variant="outlined">查询</v-btn>
            <v-btn v-if="hasFilters" variant="text" @click="clearFilters">清除筛选</v-btn>
            <span v-if="hasFilters" class="text-caption text-medium-emphasis">已生效：{{ filterSummary }}</span>
          </div>
          <p v-if="formError" role="alert" class="text-body-2 text-error mt-2 mb-0">{{ formError }}</p>
        </v-form>
      </template>
      <AdminAsyncState :loading="loading && !hasCurrentResult" :error="error && !hasCurrentResult ? error : null" @retry="load">
        <v-alert v-if="error && hasCurrentResult" class="ma-3" type="error" variant="tonal">{{ error }}<template #append><v-btn variant="outlined" @click="load">重试</v-btn></template></v-alert>
        <v-data-table-server class="admin-table-no-sort" :headers="headers" :items="notifications" :items-length="totalItems" :items-per-page="itemsPerPage" :page="page" :items-per-page-options="[10, 20, 50]" :loading="loading" item-value="id" @update:page="setPage" @update:items-per-page="setPageSize">
          <template #item.id="{ item }"><span class="text-medium-emphasis">#{{ item.id }}</span></template>
          <template #item.type="{ item }">{{ item.type_label === '未知类型' ? `未知类型（${item.type}）` : item.type_label }}</template>
          <template #item.recipient_id="{ item }">用户 #{{ item.recipient_id }}</template>
          <template #item.resource="{ item }">{{ resourceLabel(item) }}</template>
          <template #item.read_state="{ item }">{{ item.read_at ? '已读' : '未读' }}</template>
          <template #item.created_at="{ item }"><AdminTableTime :value="item.created_at" /></template>
          <template #item.read_at="{ item }"><AdminTableTime :value="item.read_at" /></template>
          <template #item.action="{ item }"><AdminTableRowAction @click="openNotification(item.id)">查看</AdminTableRowAction></template>
          <template #no-data>{{ error ? '上次查询没有记录，当前结果读取失败。' : '没有匹配的通知记录' }}</template>
        </v-data-table-server>
      </AdminAsyncState>
    </AdminResourceTableShell>
  </AdminPageContainer>
  <NotificationDetailDialog v-model="inspectOpen" :notification-id="inspectId" />
</template>

<script setup lang="ts">
import type { AdminNotification } from '@lovecards/sdk'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import AdminTableRowAction from '~/components/shared/admin/AdminTableRowAction.vue'
import NotificationDetailDialog from '~/components/notifications/NotificationDetailDialog.vue'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const route = useRoute()
const router = useRouter()
const filterKeys = ['recipient_id', 'type', 'read_state', 'from', 'to']
const readStates = [{ title: '未读', value: 'unread' }, { title: '已读', value: 'read' }]
type FilterValues = { recipient_id: string; type: string; read_state: string; from: string; to: string }
const queryText = (value: unknown) => typeof value === 'string' ? value.trim() : ''
const positiveInt = (value: unknown, fallback: number) => { const n = Number(value); return Number.isSafeInteger(n) && n > 0 ? n : fallback }
const page = computed(() => positiveInt(route.query.page, 1))
const itemsPerPage = computed(() => [10, 20, 50].includes(Number(route.query.list_rows)) ? Number(route.query.list_rows) : 20)
const filters = computed<FilterValues>(() => ({ recipient_id: queryText(route.query.recipient_id), type: queryText(route.query.type), read_state: queryText(route.query.read_state), from: queryText(route.query.from), to: queryText(route.query.to) }))
const draft = reactive<FilterValues>({ recipient_id: '', type: '', read_state: '', from: '', to: '' })
const formError = ref('')
const hasFilters = computed(() => filterKeys.some(key => Boolean(filters.value[key as keyof FilterValues])))
const filterSummary = computed(() => [
  filters.value.recipient_id ? `接收用户 #${filters.value.recipient_id}` : '',
  filters.value.type,
  filters.value.read_state === 'read' ? '已读' : filters.value.read_state === 'unread' ? '未读' : '',
  filters.value.from ? `生成自 ${filters.value.from}` : '',
  filters.value.to ? `生成早于 ${filters.value.to}` : '',
].filter(Boolean).join(' · '))
const normalizedTime = (value: string) => { const text = value.trim().replace('T', ' '); return text.length === 16 ? `${text}:00` : text }
function validationError(value: FilterValues) {
  if (value.recipient_id && (!/^[1-9]\d*$/.test(value.recipient_id) || !Number.isSafeInteger(Number(value.recipient_id)))) return '接收用户 ID 必须是正整数。'
  if (value.type.length > 64) return '通知类型不能超过 64 个字符。'
  if (value.read_state && !['read', 'unread'].includes(value.read_state)) return '读取状态无效，请重新选择。'
  for (const time of [value.from, value.to]) if (time && !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(time)) return '请使用有效的日期时间。'
  if (value.from && value.to && value.from >= value.to) return '结束时间必须晚于开始时间。'
  return ''
}
function replaceQuery(patch: Record<string, string | undefined>) { void router.replace({ query: { ...route.query, ...patch } }) }
function applyFilters() {
  const selected = { recipient_id: queryText(draft.recipient_id), type: queryText(draft.type), read_state: queryText(draft.read_state), from: normalizedTime(draft.from || ''), to: normalizedTime(draft.to || '') }
  formError.value = validationError(selected)
  if (formError.value) return
  replaceQuery({ ...Object.fromEntries(filterKeys.map(key => [key, selected[key as keyof FilterValues] || undefined])), page: '1' })
}
function clearFilters() { formError.value = ''; replaceQuery({ ...Object.fromEntries(filterKeys.map(key => [key, undefined])), page: '1' }) }
function setPage(value: number) { if (value !== page.value) replaceQuery({ page: String(value) }) }
function setPageSize(value: number) { if ([10, 20, 50].includes(value) && value !== itemsPerPage.value) replaceQuery({ list_rows: String(value), page: '1' }) }
function resetTableView() { formError.value = ''; replaceQuery({ ...Object.fromEntries(filterKeys.map(key => [key, undefined])), page: '1', list_rows: '20' }) }
function applySavedView(saved: Record<string, string | null | (string | null)[]>) {
  const patch = Object.fromEntries(filterKeys.map(key => [key, queryText(saved[key]) || undefined]))
  replaceQuery({ ...patch, page: '1', list_rows: String([10, 20, 50].includes(Number(saved.list_rows)) ? Number(saved.list_rows) : 20) })
}
const allHeaders = [
  { title: 'ID', key: 'id', minWidth: 88 }, { title: '通知类型', key: 'type', minWidth: 176 },
  { title: '接收用户', key: 'recipient_id', minWidth: 144 }, { title: '关联对象', key: 'resource', minWidth: 176 },
  { title: '读取状态', key: 'read_state', minWidth: 112 }, { title: '生成时间', key: 'created_at', minWidth: 176 },
  { title: '已读时间', key: 'read_at', minWidth: 176 }, { title: '操作', key: 'action', minWidth: 96 },
].map(header => ({ ...header, sortable: false }))
const defaultColumns = ['type', 'recipient_id', 'resource', 'read_state', 'created_at', 'action']
const visibleColumns = ref([...defaultColumns])
const headers = computed(() => allHeaders.filter(header => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map(header => ({ title: header.title, key: header.key, required: header.key === 'type' || header.key === 'action' }))
const resourceNames: Record<string, string> = { user: '用户', card: '卡片', comment: '评论', file: '文件', report: '举报', message: '私信', conversation: '会话' }
function resourceLabel(item: AdminNotification) { return item.resource_type && item.resource_id ? `${resourceNames[item.resource_type] ?? item.resource_type} #${item.resource_id}` : '—' }
const inspectOpen = ref(false)
const inspectId = ref<number | null>(null)
function openNotification(id: number) { inspectId.value = id; inspectOpen.value = true }
const notifications = ref<AdminNotification[]>([])
const totalItems = ref(0)
const resultCount = ref<number | null>(null)
const resultState = ref<'ready' | 'loading' | 'stale'>('ready')
const loading = ref(false)
const error = ref<string | null>(null)
const queryKey = computed(() => JSON.stringify({ ...filters.value, page: page.value, list_rows: itemsPerPage.value }))
const lastSuccessfulKey = ref('')
const hasCurrentResult = computed(() => lastSuccessfulKey.value === queryKey.value)
let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  const key = queryKey.value
  const selected = { ...filters.value }
  error.value = null
  if (!hasCurrentResult.value) { notifications.value = []; totalItems.value = 0; resultCount.value = null; lastSuccessfulKey.value = '' }
  const invalid = validationError(selected)
  if (invalid) { error.value = invalid; loading.value = false; return }
  loading.value = true
  resultState.value = hasCurrentResult.value ? 'loading' : 'ready'
  try {
    const result = await client.notifications.listManaged({ page: page.value, list_rows: itemsPerPage.value,
      ...(selected.recipient_id ? { recipient_id: Number(selected.recipient_id) } : {}), ...(selected.type ? { type: selected.type } : {}),
      ...(selected.read_state ? { read_state: selected.read_state as 'read' | 'unread' } : {}),
      ...(selected.from ? { from: selected.from } : {}), ...(selected.to ? { to: selected.to } : {}),
    })
    if (requestId !== latestRequest) return
    notifications.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
    resultCount.value = totalItems.value
    resultState.value = 'ready'
    lastSuccessfulKey.value = key
  } catch (cause) {
    if (requestId === latestRequest) { error.value = isApiError(cause) ? cause.message : '无法加载通知记录，请重试。'; if (hasCurrentResult.value) resultState.value = 'stale' }
  } finally { if (requestId === latestRequest) loading.value = false }
}
watch(filters, value => { Object.assign(draft, { ...value, from: value.from.replace(' ', 'T'), to: value.to.replace(' ', 'T') }); formError.value = '' }, { immediate: true })
watch(queryKey, () => { void load() }, { immediate: true })
onBeforeUnmount(() => { latestRequest += 1 })
</script>
