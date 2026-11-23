<template>
  <AdminAsyncState
    :loading="loading"
    :error="error"
    :error-title="errorTitle"
    :empty="missing"
    empty-description="该记录可能已清理，或输入的任务 ID 无效。"
    empty-icon="mdi-send-outline"
    empty-title="处理任务不存在"
    skeleton-type="heading, list-item-two-line@6"
    @retry="load"
  >
    <template v-if="delivery">
      <AdminDetailSection title="处理概况">
        <p class="mb-4 text-body-2">处理记录只说明服务端如何执行；不表示浏览器已接收或用户已阅读。</p>
        <v-alert
        v-if="delivery.status === 'dead'"
        class="mb-5"
        icon="mdi-email-alert-outline"
        title="处理失败，需要人工检查"
        type="error"
        variant="tonal"
      >
        核对最后错误并排除原因后，可以重新运行该处理器。
        </v-alert>

        <v-row dense>
          <v-col cols="12" sm="6"><AdminDetailField label="处理结果"><span v-if="delivery.policy_decision === 'skipped'">{{ skipLabel(delivery.queue_name) }}</span><AdminStatusChip v-else :status="delivery.status" /></AdminDetailField></v-col>
          <v-col cols="12" sm="6"><AdminDetailField label="尝试次数" :value="delivery.attempt_count" /></v-col>
          <v-col cols="12" sm="6"><AdminDetailField label="事件" :value="delivery.display_name || delivery.event_type" /></v-col>
          <v-col cols="12" sm="6"><AdminDetailField label="创建时间" :value="delivery.created_at" /></v-col>
          <v-col cols="12" sm="6"><AdminDetailField label="最早可执行时间" :value="delivery.available_at" /></v-col>
          <v-col cols="12" sm="6"><AdminDetailField label="最近更新时间" :value="delivery.updated_at" /></v-col>
        </v-row>
      </AdminDetailSection>

      <AdminDetailSection v-if="delivery.last_error || delivery.status === 'dead'" title="最后失败信息">
        <v-alert v-if="delivery.last_error" type="error" variant="tonal">
          <pre class="delivery-detail__error">{{ delivery.last_error }}</pre>
        </v-alert>
        <v-sheet v-else border class="pa-4 text-body-2 text-medium-emphasis" rounded="lg">当前记录没有失败详情。</v-sheet>
      </AdminDetailSection>

      <AdminDetailSection title="技术信息">
        <v-expansion-panels>
        <v-expansion-panel title="展开执行元数据">
          <v-expansion-panel-text>
            <dl class="delivery-detail__metadata">
              <dt>事件 ID</dt><dd>{{ delivery.event_id }}</dd>
              <dt>处理器</dt><dd>{{ delivery.handler_key }}</dd>
              <dt>队列</dt><dd>{{ queueLabel(delivery.queue_name) }}</dd>
              <dt>执行模式</dt><dd>{{ delivery.execution_mode }}</dd>
              <template v-if="delivery.queue_name === 'notifications'">
                <dt>通知策略版本</dt><dd>{{ delivery.policy_revision ?? '旧记录未配置' }}</dd>
                <dt>通知策略判断</dt><dd>{{ policyDecisionLabel(delivery.policy_decision) }}</dd>
              </template>
              <dt>关联 ID</dt><dd>{{ delivery.correlation_id || '未提供' }}</dd>
            </dl>
          </v-expansion-panel-text>
        </v-expansion-panel>
        </v-expansion-panels>
      </AdminDetailSection>

      <AdminDetailSection v-if="actionError || (canRetry && delivery.status === 'dead')" title="任务操作">
        <v-alert v-if="actionError && !confirmRetry" class="mb-4" type="error" variant="tonal">{{ actionError }}</v-alert>
        <v-btn v-if="canRetry && delivery.status === 'dead'" prepend-icon="mdi-refresh" variant="tonal" @click="openRetry">重试任务</v-btn>
      </AdminDetailSection>
    </template>
  </AdminAsyncState>
  <AdminDangerActionDialog
    v-model="confirmRetry"
    title="重试处理任务"
    action-label="加入待处理队列"
    :resource-label="delivery ? `任务 #${delivery.id}` : ''"
    :impact="delivery ? `${delivery.event_type} 将再次进入待处理队列。请先确认失败原因已解除，并确保处理器能够幂等执行。` : ''"
    color="warning"
    :loading="retrying"
    :error="actionError"
    @confirm="retry"
  />
</template>

<script setup lang="ts">
import type { OperationsDelivery } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminDetailField from '~/components/shared/admin/AdminDetailField.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const props = defineProps<{ deliveryId: number }>()
const emit = defineEmits<{ updated: []; 'busy-change': [value: boolean] }>()
const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const canRetry = computed(() => userStore.hasGrant('operations.retry', 'global'))
const delivery = ref<OperationsDelivery | null>(null)
const loading = ref(false)
const missing = ref(false)
const error = ref<string | null>(null)
const errorTitle = ref('加载失败')
const confirmRetry = ref(false)
const retrying = ref(false)
const actionError = ref('')
const queueLabels: Record<string, string> = { notifications: '通知', realtime: '实时提示', storage: '文件处理' }
const queueLabel = (name: string) => queueLabels[name] ?? name
const skipLabel = (name: string) => name === 'notifications' ? '已按策略跳过通知' : name === 'realtime' ? '已跳过实时提示' : '已跳过'
function policyDecisionLabel(decision: OperationsDelivery['policy_decision']) {
  if (decision === 'skipped') return '已按策略跳过通知'
  if (decision === 'applied') return '已执行通知投影'
  if (decision === 'not-configured') return '旧记录或事件未配置策略'
  return '无策略判断记录'
}

let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  const id = props.deliveryId
  delivery.value = null
  missing.value = false
  error.value = null
  errorTitle.value = '加载失败'
  confirmRetry.value = false
  if (!Number.isSafeInteger(id) || id < 1) {
    missing.value = true
    loading.value = false
    return
  }
  loading.value = true
  try {
    const result = await client.operations.delivery(id)
    if (requestId !== latestRequest || id !== props.deliveryId) return
    delivery.value = result
  } catch (cause) {
    if (requestId !== latestRequest || id !== props.deliveryId) return
    if (isApiError(cause) && cause.status === 404) missing.value = true
    else {
      errorTitle.value = isApiError(cause) && cause.status === 403 ? '无权查看任务详情' : '无法加载任务详情'
      error.value = isApiError(cause) ? cause.message : '无法加载任务详情，请稍后重试。'
    }
  } finally {
    if (requestId === latestRequest && id === props.deliveryId) loading.value = false
  }
}

function openRetry() {
  if (!canRetry.value || delivery.value?.status !== 'dead') return
  actionError.value = ''
  confirmRetry.value = true
}
async function retry() {
  if (!canRetry.value || !delivery.value || delivery.value.status !== 'dead' || retrying.value) return
  const id = delivery.value.id
  retrying.value = true
  actionError.value = ''
  try {
    const current = await client.operations.delivery(id)
    if (current.status !== 'dead') {
      delivery.value = current
      confirmRetry.value = false
      actionError.value = '任务状态已变化，请核对后再操作。'
      return
    }
    await client.operations.retryDelivery(id)
    confirmRetry.value = false
    notifier.success('处理任务已加入待处理队列')
    emit('updated')
    await load()
  } catch (cause) {
    actionError.value = isApiError(cause) ? cause.message : cause instanceof Error ? cause.message : '重试失败，请稍后再试。'
  } finally {
    retrying.value = false
  }
}
watch(() => props.deliveryId, () => { void load() }, { immediate: true })
watch(retrying, value => emit('busy-change', value), { immediate: true })
</script>

<style scoped>
.delivery-detail__error { max-height: 18rem; margin: 0; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; }
.delivery-detail__metadata { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 8px 16px; }
.delivery-detail__metadata dd { margin: 0; overflow-wrap: anywhere; }
</style>
