<template>
  <AdminPageContainer>
    <AdminPageHeader title="运行状态" description="核对当前部署实际启用的设施、降级路径和 Worker 心跳。" :breadcrumbs="[{ title: '运维与审计' }, { title: '运行状态' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!runtime" @retry="load">
      <template v-if="runtime">
        <v-alert v-if="degradedCount" class="mb-4" type="warning" variant="tonal">
          当前有 {{ degradedCount }} 项设施处于降级或不可用状态，业务契约保持一致，但实时性或吞吐可能降低。
        </v-alert>
        <v-sheet border rounded="lg">
          <v-data-table :headers="facilityHeaders" :items="runtime.facilities" item-value="name" items-per-page-text="每页行数">
            <template #item.name="{ item }"><strong>{{ item.name }}</strong><div class="text-caption text-medium-emphasis">{{ item.effective_provider || '未选择 Provider' }}</div></template>
            <template #item.mode="{ item }">{{ item.desired_mode }}</template>
            <template #item.status="{ item }"><AdminStatusChip :status="item.ready ? 'available' : item.enabled ? 'degraded' : 'disabled'" /></template>
            <template #item.reason="{ item }">{{ item.reason_code || '-' }}</template>
            <template #item.checked="{ item }">{{ item.checked_at }}</template>
          </v-data-table>
        </v-sheet>

        <h2 class="text-h6 mt-8 mb-3">Worker 心跳</h2>
        <v-sheet border rounded="lg">
          <v-data-table :headers="workerHeaders" :items="runtime.workers" item-value="worker_id" items-per-page-text="每页行数">
            <template #item.state="{ item }"><AdminStatusChip :status="item.online ? 'active' : item.state" /></template>
          </v-data-table>
        </v-sheet>
        <v-alert class="mt-4" type="info" variant="tonal">
          Outbox：待处理 {{ runtime.eventing.pending }}，处理中 {{ runtime.eventing.processing }}，死信 {{ runtime.eventing.dead }}；最早待处理 {{ runtime.eventing.oldest_pending_at || '-' }}，过期租约 {{ runtime.eventing.expired_processing ?? 0 }}。
        </v-alert>
        <v-sheet v-if="runtime.eventing.queues?.length" class="mt-4" border rounded="lg">
          <v-data-table :headers="queueHeaders" :items="runtime.eventing.queues" item-value="queue_name" items-per-page-text="每页行数">
            <template #item.execution_mode="{ item }"><AdminStatusChip :status="item.execution_mode" /></template>
          </v-data-table>
        </v-sheet>
      </template>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsRuntimeStatus } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const runtime = ref<OperationsRuntimeStatus | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const degradedCount = computed(() => runtime.value?.facilities.filter((item: OperationsRuntimeStatus['facilities'][number]) => !item.ready).length ?? 0)
const facilityHeaders = [
  { title: '设施', key: 'name' }, { title: '期望模式', key: 'mode' }, { title: '状态', key: 'status' },
  { title: '原因', key: 'reason' }, { title: '检查时间', key: 'checked' },
]
const workerHeaders = [
  { title: 'Worker', key: 'worker_id' }, { title: '类型', key: 'worker_type' }, { title: '状态', key: 'state' },
  { title: '已处理', key: 'jobs_processed' }, { title: '心跳时间', key: 'heartbeat_at' },
]
const queueHeaders = [
  { title: '队列', key: 'queue_name' }, { title: '执行模式', key: 'execution_mode' },
  { title: '待处理', key: 'pending' }, { title: '处理中', key: 'processing' }, { title: '死信', key: 'dead' },
]

async function load() {
  loading.value = true
  error.value = null
  try { runtime.value = await client.operations.runtime() }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法读取运行环境' }
  finally { loading.value = false }
}
onMounted(load)
</script>
