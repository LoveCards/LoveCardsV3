<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="`投递 #${route.params.id}`"
      description="核对单次事件投递的处理器、执行状态、重试次数与端到端关联标识。"
      :breadcrumbs="[
        { title: '运维与审计' },
        { title: '通知投递', to: '/apps/operations/deliveries' },
        { title: `#${route.params.id}` },
      ]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" to="/apps/operations/deliveries" variant="outlined">
          返回列表
        </v-btn>
        <AdminActionGuard capability="operations.retry">
          <v-btn
            v-if="delivery?.status === 'dead'"
            color="primary"
            prepend-icon="mdi-refresh"
            variant="tonal"
            @click="openRetry"
          >
            重试投递
          </v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>

    <AdminAsyncState
      :loading="loading"
      :error="error"
      :error-title="errorTitle"
      :empty="missing"
      empty-description="该记录可能已清理，或输入的投递 ID 无效。"
      empty-icon="mdi-send-off-outline"
      empty-title="投递记录不存在"
      skeleton-type="heading, list-item-two-line@6"
      @retry="load"
    >
      <template v-if="delivery">
        <v-alert
          v-if="delivery.status === 'dead'"
          class="mb-5"
          icon="mdi-email-alert-outline"
          title="该投递已进入死信状态"
          type="error"
          variant="tonal"
        >
          核对最后失败信息和处理器幂等性后再执行人工重试。
        </v-alert>

        <v-sheet border rounded="lg" overflow-hidden>
          <v-list aria-label="投递摘要" lines="two">
            <v-list-item prepend-icon="mdi-list-status" subtitle="当前状态">
              <template #title>
                <AdminStatusChip :status="delivery.status" />
              </template>
              <template #append>
                <span class="text-body-2">已尝试 {{ delivery.attempt_count }} 次</span>
              </template>
            </v-list-item>
            <v-divider />
            <v-list-item
              prepend-icon="mdi-lightning-bolt-outline"
              :subtitle="`事件 #${delivery.event_id}`"
              :title="delivery.event_type"
            />
            <v-divider />
            <v-list-item
              prepend-icon="mdi-cog-transfer-outline"
              subtitle="处理器"
              :title="delivery.handler_key"
            />
            <v-divider />
            <v-list-item prepend-icon="mdi-tray-full" subtitle="队列与执行模式">
              <template #title>
                {{ delivery.queue_name }}
                <v-chip class="ml-2" size="small" variant="outlined">
                  {{ delivery.execution_mode }}
                </v-chip>
              </template>
            </v-list-item>
            <v-divider />
            <v-list-item
              prepend-icon="mdi-vector-link"
              subtitle="Correlation ID"
              :title="delivery.correlation_id || '未提供'"
            />
          </v-list>
        </v-sheet>

        <section class="mt-7" aria-labelledby="delivery-error-title">
          <h2 id="delivery-error-title" class="mb-3 text-h6">最后失败信息</h2>
          <v-alert
            v-if="delivery.last_error"
            type="error"
            variant="tonal"
          >
            <pre class="delivery-detail__error">{{ delivery.last_error }}</pre>
          </v-alert>
          <v-sheet v-else border class="pa-4 text-body-2 text-medium-emphasis" rounded="lg">
            当前记录没有失败详情。
          </v-sheet>
        </section>

        <section class="mt-7" aria-labelledby="delivery-timeline-title">
          <h2 id="delivery-timeline-title" class="mb-3 text-h6">投递时序</h2>
          <v-sheet border class="pa-5" rounded="lg">
            <AdminStateTimeline :items="timelineItems" />
          </v-sheet>
        </section>

        <v-alert
          class="mt-6"
          icon="mdi-shield-lock-outline"
          title="事件内容保持最小可见"
          type="info"
          variant="tonal"
        >
          本页不返回或展示 Outbox payload；故障定位只使用处理器、状态、错误和关联标识。
        </v-alert>
      </template>
    </AdminAsyncState>

    <AdminDangerActionDialog
      v-model="retryDialog"
      title="重试事件投递"
      action-label="加入重试队列"
      :resource-label="delivery ? `${delivery.event_type} · 投递 #${delivery.id}` : ''"
      impact="该处理器将再次消费事件。请先确认失败原因已解除，并确保业务处理器能够幂等执行。"
      :loading="retrying"
      :error="actionError"
      @confirm="handleRetry"
    />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsDelivery } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminStateTimeline, { type AdminTimelineItem } from '~/components/shared/admin/AdminStateTimeline.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { isApiError, useApi } from '~/lib/api'

const route = useRoute()
const client = useApi()
const notifier = useAdminNotifier()
const delivery = ref<OperationsDelivery | null>(null)
const loading = ref(false)
const missing = ref(false)
const error = ref<string | null>(null)
const errorTitle = ref('加载失败')
const retryDialog = ref(false)
const retrying = ref(false)
const actionError = ref('')

const timelineItems = computed<AdminTimelineItem[]>(() => {
  if (!delivery.value) return []
  return [
    {
      id: 'created',
      title: '创建投递记录',
      timestamp: delivery.value.created_at,
      description: `事件 #${delivery.value.event_id} 交给 ${delivery.value.handler_key}`,
    },
    {
      id: 'available',
      title: '进入可执行时间窗',
      timestamp: delivery.value.available_at,
      description: `${delivery.value.queue_name} · ${delivery.value.execution_mode}`,
    },
    {
      id: 'updated',
      title: '最近一次状态更新',
      timestamp: delivery.value.updated_at,
      description: `累计尝试 ${delivery.value.attempt_count} 次`,
      status: delivery.value.status,
    },
  ]
})

let latestRequest = 0
async function load() {
  const requestId = ++latestRequest
  const id = Number(route.params.id)
  delivery.value = null
  missing.value = false
  error.value = null
  errorTitle.value = '加载失败'

  if (!Number.isInteger(id) || id < 1) {
    missing.value = true
    return
  }

  loading.value = true
  try {
    const result = await client.operations.delivery(id)
    if (requestId !== latestRequest) return
    delivery.value = result
    if (result.status !== 'dead') retryDialog.value = false
  } catch (cause) {
    if (requestId !== latestRequest) return
    if (isApiError(cause) && cause.status === 404) {
      missing.value = true
    } else {
      errorTitle.value = isApiError(cause) && cause.status === 403 ? '无权查看投递详情' : '无法加载投递详情'
      error.value = isApiError(cause) ? cause.message : '无法加载投递详情，请稍后重试。'
    }
  } finally {
    if (requestId === latestRequest) loading.value = false
  }
}

function openRetry() {
  actionError.value = ''
  retryDialog.value = true
}

async function retry() {
  if (!delivery.value || delivery.value.status !== 'dead') return
  retrying.value = true
  actionError.value = ''
  try {
    await client.operations.retryDelivery(delivery.value.id)
    retryDialog.value = false
    notifier.success('事件已加入重试队列')
    await load()
  } catch (cause) {
    actionError.value = isApiError(cause) ? cause.message : '重试失败，请稍后再试。'
  } finally {
    retrying.value = false
  }
}

function handleRetry() {
  void retry()
}

watch(() => route.params.id, () => { void load() }, { immediate: true })
</script>

<style scoped>
.delivery-detail__error {
  max-height: 18rem;
  margin: 0;
  overflow: auto;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
</style>
