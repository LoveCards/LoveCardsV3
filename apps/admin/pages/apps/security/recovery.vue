<template>
  <AdminPageContainer>
    <AdminPageHeader title="账号恢复" description="只展示恢复挑战的状态与次数，不显示验证码、Token 或密钥。" :breadcrumbs="[{ title: '用户与安全' }, { title: '账号恢复' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <v-alert class="mb-4" type="info" variant="tonal">恢复凭证不会在管理端返回；需要调查具体用户时进入用户安全详情。</v-alert>
    <AdminAsyncState :loading="loading && !challenges.length" :error="error && !challenges.length ? error : null" :empty="!challenges.length" @retry="load">
      <v-alert v-if="error" class="mb-3" type="error" variant="tonal">
        {{ error }}
        <template #append><v-btn variant="outlined" @click="load">重试</v-btn></template>
      </v-alert>
      <AdminResourceTableShell
        v-model:visible-columns="visibleColumns"
        :columns="columnOptions"
        :result-count="totalItems"
        preference-key="security-recovery"
        @reset-view="resetTableView"
      >
        <v-data-table-server
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :headers="headers"
          :items="challenges"
          :items-length="totalItems"
          :items-per-page-options="[10, 20, 50]"
          :loading="loading"
          density="comfortable"
          item-value="id"
          items-per-page-text="每页行数"
        >
          <template #item.user_id="{ item }"><AdminResourceLink v-if="item.user_id" resource-type="user" :resource-id="item.user_id" /></template>
          <template #item.state="{ item }"><AdminStatusChip :status="item.state" /></template>
        </v-data-table-server>
      </AdminResourceTableShell>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { RecoveryChallenge } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const route = useRoute()
const router = useRouter()
const challenges = ref<RecoveryChallenge[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const allowedPageSizes = [10, 20, 50]
const queryInt = (value: unknown, fallback: number) => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN
  return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback
}
const page = ref(queryInt(route.query.page, 1))
const requestedPageSize = queryInt(route.query.list_rows, 20)
const itemsPerPage = ref(allowedPageSizes.includes(requestedPageSize) ? requestedPageSize : 20)
const totalItems = ref(0)
const allHeaders = [
  { title: '编号', key: 'id' }, { title: '用户', key: 'user_id' }, { title: '用途', key: 'purpose' },
  { title: '状态', key: 'state' }, { title: '尝试次数', key: 'attempts' }, { title: '到期时间', key: 'expires_at' }, { title: '创建时间', key: 'created_at' },
].map((header) => ({ ...header, sortable: false }))
const visibleColumns = ref(allHeaders.map((header) => header.key))
const headers = computed(() => allHeaders.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = allHeaders.map((header) => ({
  title: header.title,
  key: header.key,
  required: header.key === 'id' || header.key === 'user_id',
}))
let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  loading.value = true; error.value = null
  try {
    const result = await client.users.recovery({ page: page.value, list_rows: itemsPerPage.value })
    if (requestId !== latestRequest) return
    challenges.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
  }
  catch (cause) { if (requestId === latestRequest) error.value = isApiError(cause) ? cause.message : '无法加载恢复挑战' }
  finally { if (requestId === latestRequest) loading.value = false }
}
function syncRouteQuery() {
  const next = { ...route.query, page: String(page.value), list_rows: String(itemsPerPage.value) }
  if (route.query.page === next.page && route.query.list_rows === next.list_rows) return
  void router.replace({ query: next })
}
function resetTableView() { page.value = 1; itemsPerPage.value = 20 }
watch(() => [route.query.page, route.query.list_rows], () => {
  const nextPage = queryInt(route.query.page, 1)
  const nextSize = queryInt(route.query.list_rows, 20)
  if (page.value !== nextPage) page.value = nextPage
  if (allowedPageSizes.includes(nextSize) && itemsPerPage.value !== nextSize) itemsPerPage.value = nextSize
})
watch([page, itemsPerPage], () => { syncRouteQuery(); void load() }, { immediate: true })
</script>
