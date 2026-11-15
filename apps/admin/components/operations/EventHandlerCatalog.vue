<template>
  <section aria-label="已注册处理能力">
    <p class="text-body-2 text-medium-emphasis mb-4">以下是当前版本注册的事件处理器。它说明事件发生后由谁处理，不代表所有发布事件的业务入口。</p>
    <AdminAsyncState :loading="loading" :error="error" :empty="!handlers.length" empty-title="尚无注册的处理器" @retry="load">
      <AdminResourceTableShell :result-count="handlers.length">
        <v-data-table :headers="headers" :items="handlers" item-value="handler_key" :items-per-page="-1" hide-default-footer>
          <template #item.event_type="{ item }">{{ item.event_type }}@{{ item.event_version }}</template>
          <template #item.signal_key="{ item }">{{ item.signal_key || '—' }}</template>
        </v-data-table>
      </AdminResourceTableShell>
    </AdminAsyncState>
  </section>
</template>

<script setup lang="ts">
import type { OperationsEventHandler } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import { isApiError, useApi } from '~/lib/api'

const client = useApi()
const handlers = ref<OperationsEventHandler[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const headers = [
  { title: '事件类型', key: 'event_type' }, { title: '处理器', key: 'handler_key' },
  { title: '队列', key: 'queue_name' }, { title: '执行方式', key: 'execution_mode' },
  { title: '最多尝试', key: 'max_attempts' }, { title: '实时信号', key: 'signal_key' },
]
async function load() {
  loading.value = true
  error.value = null
  try { handlers.value = await client.operations.eventHandlers() }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载处理能力目录' }
  finally { loading.value = false }
}
onMounted(load)
</script>
