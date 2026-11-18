<template>
  <section aria-label="已注册处理能力">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <h2 class="text-h6">注册能力</h2>
      <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新注册能力</v-btn>
    </div>
    <p class="text-body-2 text-medium-emphasis mb-4">以下是当前版本注册的事件处理器。它说明事件发生后由谁处理，不代表所有发布事件的业务入口。</p>
    <AdminAsyncState :loading="loading && !hasLoaded" :error="error && !hasLoaded ? error : null" :empty="!loading && !error && !handlers.length" empty-title="尚无注册的处理器" @retry="load">
      <v-alert v-if="error" type="warning" variant="tonal" class="mb-4">刷新失败，以下为上次成功读取的处理能力：{{ error }}</v-alert>
      <AdminResourceTableShell :result-count="hasLoaded ? handlers.length : null" :result-state="loading ? 'loading' : error ? 'stale' : 'ready'">
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
const hasLoaded = ref(false)
const headers = [
  { title: '事件类型', key: 'event_type', minWidth: 192 }, { title: '处理器', key: 'handler_key', minWidth: 208 },
  { title: '队列', key: 'queue_name', minWidth: 144 }, { title: '执行方式', key: 'execution_mode', minWidth: 120 },
  { title: '最多尝试', key: 'max_attempts', minWidth: 104 }, { title: '实时信号', key: 'signal_key', minWidth: 160 },
]
async function load() {
  loading.value = true
  error.value = null
  try {
    handlers.value = await client.operations.eventHandlers()
    hasLoaded.value = true
  }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载处理能力目录' }
  finally { loading.value = false }
}
onMounted(load)
</script>
