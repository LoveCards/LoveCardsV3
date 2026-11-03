<template>
  <AdminPageHeader title="账号恢复记录" description="查看密码恢复请求及处理状态。这里只展示记录，不提供恢复凭证。" :breadcrumbs="[{ title: '用户与权限' }]" />
  <AdminPageContainer fluid>
      <v-alert v-if="error && challenges.length" class="mb-3" type="error" variant="tonal">
        {{ error }}
        <template #append><v-btn variant="outlined" @click="load">重试</v-btn></template>
      </v-alert>
      <AdminResourceTableShell
        v-model:visible-columns="visibleColumns"
        :columns="columnOptions"
        :default-columns="defaultColumns"
        preference-key="security-recovery"
        @reset-view="resetTableView"
      >
        <template #search>
          <v-select v-model="status" :items="statusOptions" label="恢复状态" clearable />
        </template>
        <template #actions><v-btn prepend-icon="mdi-refresh" variant="text" :loading="loading" @click="load">刷新</v-btn></template>
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
          :header-props="{ scope: 'col' }"
        >
          <template #loading><v-skeleton-loader type="table-row-divider@6" /></template>
          <template #item.user_id="{ item }">
            <div class="py-3">
              <AdminResourceLink v-if="item.user_id" resource-type="user" :resource-id="item.user_id" />
              <span v-else class="text-body-2">未关联用户</span>
              <div v-if="!visibleColumns.includes('purpose')" class="text-caption text-medium-emphasis">{{ purposeLabel(item.purpose) }}</div>
            </div>
          </template>
          <template #item.purpose="{ item }">{{ purposeLabel(item.purpose) }}</template>
          <template #item.state="{ item }"><AdminStatusChip :status="item.state" :label="statusOptions.find(option => option.value === item.state)?.title" /></template>
          <template #item.created_at="{ item }"><span class="text-body-2 text-medium-emphasis">{{ item.created_at }}</span></template>
          <template #item.expires_at="{ item }"><span class="text-body-2 text-medium-emphasis">{{ item.expires_at }}</span></template>
          <template #item.operate="{ item }">
            <v-btn v-if="item.user_id" :to="`/apps/users/${item.user_id}/security`" variant="text">查看账号安全</v-btn>
            <span v-else class="text-medium-emphasis">—</span>
          </template>
          <template #no-data>
            <v-empty-state icon="mdi-history" :title="error ? '无法加载恢复记录' : page > 1 ? '当前页没有恢复记录' : '暂无恢复记录'" :text="error || (status ? '没有符合当前状态的恢复记录，可清除筛选查看全部。' : '用户发起密码恢复后，相关记录会显示在这里。')">
              <template #actions>
                <v-btn v-if="error" variant="outlined" @click="load">重试</v-btn>
                <v-btn v-else-if="page > 1" variant="text" @click="page = 1">返回第一页</v-btn>
                <v-btn v-else-if="status" variant="text" @click="status = null">清除筛选</v-btn>
              </template>
            </v-empty-state>
          </template>
        </v-data-table-server>
      </AdminResourceTableShell>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { RecoveryChallenge } from '@lovecards/sdk'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const client = useApi()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const canReadSecurity = computed(() => userStore.hasGrant('users.security.read', 'global'))
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
const statusOptions = [
  { title: '待验证', value: 'active' },
  { title: '已使用', value: 'consumed' },
  { title: '已过期', value: 'expired' },
]
const queryStatus = () => statusOptions.some(option => option.value === route.query.status) ? String(route.query.status) : null
const status = ref<string | null>(queryStatus())
const allHeaders = [
  { title: '用户', key: 'user_id', minWidth: 240 }, { title: '编号', key: 'id' }, { title: '用途', key: 'purpose' },
  { title: '申请时间', key: 'created_at' }, { title: '状态', key: 'state' }, { title: '验证尝试', key: 'attempts' }, { title: '到期时间', key: 'expires_at' },
  { title: '操作', key: 'operate' },
].map((header) => ({ ...header, sortable: false }))
const defaultColumns = ['user_id', 'created_at', 'state', 'attempts', 'operate']
const visibleColumns = ref([...defaultColumns])
const availableHeaders = computed(() => allHeaders.filter((header) => header.key !== 'operate' || canReadSecurity.value))
const headers = computed(() => availableHeaders.value.filter((header) => visibleColumns.value.includes(header.key)))
const columnOptions = computed(() => availableHeaders.value.map((header) => ({
  title: header.title,
  key: header.key,
  required: header.key === 'user_id',
})))
const purposeLabel = (purpose: string) => purpose === 'password_recovery' ? '密码恢复' : purpose
let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  loading.value = true; error.value = null
  try {
    const result = await client.users.recovery({ page: page.value, list_rows: itemsPerPage.value, ...(status.value ? { status: status.value } : {}) })
    if (requestId !== latestRequest) return
    challenges.value = result.data
    totalItems.value = result.pagination?.totalItems ?? result.data.length
    page.value = result.pagination?.currentPage ?? page.value
  }
  catch (cause) { if (requestId === latestRequest) error.value = isApiError(cause) ? cause.message : '无法加载恢复记录' }
  finally { if (requestId === latestRequest) loading.value = false }
}
function syncRouteQuery() {
  const next = { ...route.query, page: String(page.value), list_rows: String(itemsPerPage.value), status: status.value || undefined }
  if (route.query.page === next.page && route.query.list_rows === next.list_rows && route.query.status === next.status) return
  void router.replace({ query: next })
}
function resetTableView() { status.value = null; page.value = 1; itemsPerPage.value = 20 }
watch(() => [route.query.page, route.query.list_rows], () => {
  const nextPage = queryInt(route.query.page, 1)
  const nextSize = queryInt(route.query.list_rows, 20)
  if (page.value !== nextPage) page.value = nextPage
  if (allowedPageSizes.includes(nextSize) && itemsPerPage.value !== nextSize) itemsPerPage.value = nextSize
})
watch(() => route.query.status, () => { status.value = queryStatus() })
watch(status, () => { page.value = 1 })
watch([page, itemsPerPage, status], () => { syncRouteQuery(); void load() }, { immediate: true })
</script>
