<template>
  <div>
    <AdminPageHeader title="审计日志" description="按时间核对高风险操作、目标资源和端到端请求标识。">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!logs.length" @retry="load">
      <v-sheet border rounded="lg">
        <v-data-table :headers="headers" :items="logs" item-value="id">
          <template #item.action="{ item }"><strong>{{ item.action }}</strong><div class="text-caption text-medium-emphasis">操作者 {{ item.actor_id ? `#${item.actor_id}` : '系统' }}</div></template>
          <template #item.resource="{ item }"><AdminResourceLink v-if="item.resource_type" :resource-type="item.resource_type" :resource-id="item.resource_id" /><span v-else>-</span></template>
          <template #item.trace="{ item }"><code>{{ item.request_id || '-' }}</code><div class="text-caption text-medium-emphasis">{{ item.correlation_id || '-' }}</div></template>
          <template #item.payload="{ item }"><v-btn icon="mdi-code-json" title="查看审计上下文" variant="text" @click="showPayload(item)" /></template>
        </v-data-table>
      </v-sheet>
      <v-pagination v-if="totalPages > 1" v-model="page" class="mt-4" :length="totalPages" @update:model-value="load" />
    </AdminAsyncState>
    <v-dialog v-model="payloadDialog" max-width="720"><v-card><v-card-title>审计上下文</v-card-title><v-card-text><pre class="audit-payload">{{ selectedPayload }}</pre></v-card-text><v-card-actions><v-spacer /><v-btn @click="payloadDialog = false">关闭</v-btn></v-card-actions></v-card></v-dialog>
  </div>
</template>

<script setup lang="ts">
import type { OperationsAuditLog } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const logs = ref<OperationsAuditLog[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const totalPages = ref(1)
const payloadDialog = ref(false)
const selectedPayload = ref('')
const headers = [
  { title: '动作', key: 'action' }, { title: '资源', key: 'resource' }, { title: 'Request / Correlation ID', key: 'trace' },
  { title: '时间', key: 'created_at' }, { title: '上下文', key: 'payload', sortable: false },
]
async function load() {
  loading.value = true; error.value = null
  try { const result = await client.operations.auditLogs({ page: page.value, list_rows: 30 }); logs.value = result.data; totalPages.value = result.pagination?.totalPages ?? 1 }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载审计日志' }
  finally { loading.value = false }
}
function showPayload(item: OperationsAuditLog) { selectedPayload.value = JSON.stringify(item.payload, null, 2); payloadDialog.value = true }
onMounted(load)
</script>

<style scoped>
.audit-payload { max-height: 60vh; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
