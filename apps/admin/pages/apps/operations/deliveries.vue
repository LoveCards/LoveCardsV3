<template>
  <div>
    <AdminPageHeader title="通知投递" description="查看 Outbox 投递状态与失败原因，并对可恢复的失败执行人工重试。">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="loadAll">刷新</v-btn></template>
    </AdminPageHeader>
    <v-row v-if="stats" class="mb-4">
      <v-col cols="12" sm="4"><v-sheet border rounded="lg" class="pa-4"><div class="text-caption text-medium-emphasis">通知总量</div><div class="text-h5">{{ stats.total }}</div></v-sheet></v-col>
      <v-col cols="12" sm="4"><v-sheet border rounded="lg" class="pa-4"><div class="text-caption text-medium-emphasis">未读通知</div><div class="text-h5">{{ stats.unread }}</div></v-sheet></v-col>
      <v-col cols="12" sm="4"><v-sheet border rounded="lg" class="pa-4"><div class="text-caption text-medium-emphasis">覆盖用户</div><div class="text-h5">{{ stats.recipients }}</div></v-sheet></v-col>
    </v-row>
    <AdminAsyncState :loading="loading" :error="error" :empty="!deliveries.length" @retry="loadAll">
      <v-sheet border rounded="lg">
        <v-data-table :headers="headers" :items="deliveries" item-value="id">
          <template #item.event="{ item }"><strong>{{ item.event_type }}</strong><div class="text-caption text-medium-emphasis">#{{ item.event_id }} · {{ item.handler_key }}</div></template>
          <template #item.status="{ item }"><AdminStatusChip :status="item.status" /></template>
          <template #item.error="{ item }"><span class="delivery-error">{{ item.last_error || '-' }}</span></template>
          <template #item.action="{ item }">
            <AdminActionGuard capability="operations.retry">
              <v-btn v-if="['failed', 'dead', 'dead_letter'].includes(item.status)" color="primary" icon="mdi-refresh" title="重试投递" variant="text" @click="openRetry(item)" />
            </AdminActionGuard>
          </template>
        </v-data-table>
      </v-sheet>
      <v-pagination v-if="totalPages > 1" v-model="page" class="mt-4" :length="totalPages" @update:model-value="loadDeliveries" />
    </AdminAsyncState>
    <AdminDangerActionDialog v-model="retryDialog" title="重试事件投递" action-label="加入重试队列" :resource-label="selected ? `${selected.event_type} #${selected.event_id}` : ''" impact="该处理器将再次消费事件，业务处理器必须保持幂等。" :loading="retrying" :error="actionError" @confirm="handleRetry" />
  </div>
</template>

<script setup lang="ts">
import type { OperationsDelivery, OperationsNotificationStats } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'

const client = useApi()
const notifier = useAdminNotifier()
const deliveries = ref<OperationsDelivery[]>([])
const stats = ref<OperationsNotificationStats | null>(null)
const loading = ref(false)
const retrying = ref(false)
const error = ref<string | null>(null)
const actionError = ref('')
const page = ref(1)
const totalPages = ref(1)
const selected = ref<OperationsDelivery | null>(null)
const retryDialog = ref(false)
const headers = [
  { title: '事件', key: 'event' }, { title: '队列', key: 'queue_name' }, { title: '执行模式', key: 'execution_mode' },
  { title: '状态', key: 'status' }, { title: '尝试', key: 'attempt_count' }, { title: '最后错误', key: 'error' },
  { title: '更新时间', key: 'updated_at' }, { title: '操作', key: 'action', sortable: false },
]
async function loadDeliveries() {
  const result = await client.operations.deliveries({ page: page.value, list_rows: 20 })
  deliveries.value = result.data; totalPages.value = result.pagination?.totalPages ?? 1
}
async function loadAll() {
  loading.value = true; error.value = null
  try { const result = await Promise.all([loadDeliveries(), client.operations.notificationStats()]); stats.value = result[1] }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载投递状态' }
  finally { loading.value = false }
}
function openRetry(item: OperationsDelivery) { selected.value = item; actionError.value = ''; retryDialog.value = true }
async function retry(reason: string) {
  if (!selected.value) return
  retrying.value = true; actionError.value = ''
  try { await client.operations.retryDelivery(selected.value.id, { reason }); retryDialog.value = false; notifier.success('事件已加入重试队列'); await loadDeliveries() }
  catch (cause) { actionError.value = isApiError(cause) ? cause.message : '重试失败' }
  finally { retrying.value = false }
}
function handleRetry(payload: { reason: string }) { void retry(payload.reason) }
onMounted(loadAll)
</script>

<style scoped>
.delivery-error { display: inline-block; max-width: 36ch; overflow-wrap: anywhere; }
</style>
