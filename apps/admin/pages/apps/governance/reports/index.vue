<template>
  <div>
    <AdminPageHeader title="举报处理" description="按状态审阅举报，处理动作会记录原因与操作者。">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <div class="d-flex flex-wrap ga-3 mb-4">
      <v-select v-model="status" :items="statusItems" clearable density="comfortable" label="状态" max-width="240" variant="outlined" @update:model-value="resetAndLoad" />
    </div>
    <AdminAsyncState :loading="loading" :error="error" :empty="!reports.length" @retry="load">
      <v-sheet border rounded="lg">
        <v-data-table :headers="headers" :items="reports" item-value="id">
          <template #item.id="{ item }"><v-btn :to="`/apps/governance/reports/${item.id}`" variant="text">#{{ item.id }}</v-btn></template>
          <template #item.target="{ item }"><AdminResourceLink :resource-type="item.target_type" :resource-id="item.target_id" /></template>
          <template #item.status="{ item }"><AdminStatusChip :status="item.status" /></template>
          <template #item.created_at="{ item }"><time>{{ item.created_at }}</time></template>
        </v-data-table>
      </v-sheet>
      <v-pagination v-if="totalPages > 1" v-model="page" class="mt-4" :length="totalPages" @update:model-value="load" />
    </AdminAsyncState>
  </div>
</template>

<script setup lang="ts">
import type { AdminReport } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const reports = ref<AdminReport[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const totalPages = ref(1)
const status = ref<string | null>(null)
const statusItems = [
  { title: '待处理', value: 'open' }, { title: '处理中', value: 'reviewing' },
  { title: '已解决', value: 'resolved' }, { title: '已驳回', value: 'rejected' }, { title: '已关闭', value: 'closed' },
]
const headers = [
  { title: '编号', key: 'id' }, { title: '举报人', key: 'reporter_id' }, { title: '目标', key: 'target' },
  { title: '原因', key: 'reason' }, { title: '状态', key: 'status' }, { title: '创建时间', key: 'created_at' },
]

async function load() {
  loading.value = true
  error.value = null
  try {
    const result = await client.reports.listManaged({ page: page.value, list_rows: 20, ...(status.value ? { status: status.value } : {}) })
    reports.value = result.data
    totalPages.value = result.pagination?.totalPages ?? 1
  } catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载举报列表' }
  finally { loading.value = false }
}
function resetAndLoad() { page.value = 1; void load() }
onMounted(load)
</script>
