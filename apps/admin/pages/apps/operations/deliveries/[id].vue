<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="`投递 #${route.params.id}`"
      description="查看这条站内提醒事件的处理结果、失败原因与重试条件。"
      :breadcrumbs="[
        { title: '系统' },
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
      empty-icon="mdi-send-outline"
      empty-title="投递记录不存在"
      skeleton-type="heading, list-item-two-line@6"
      @retry="load"
    >
      <template v-if="delivery">
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

        <v-sheet border rounded="lg" overflow-hidden>
          <v-list aria-label="投递摘要" lines="two">
            <v-list-item prepend-icon="mdi-list-status" subtitle="处理结果">
              <template #title>
                <span v-if="delivery.policy_decision === 'skipped'">已按策略跳过通知</span>
                <AdminStatusChip v-else :status="delivery.status" />
              </template>
              <template #append>
                <span class="text-body-2">已尝试 {{ delivery.attempt_count }} 次</span>
              </template>
            </v-list-item>
            <v-divider />
            <v-list-item
              prepend-icon="mdi-lightning-bolt-outline"
              :subtitle="delivery.event_type"
              :title="delivery.display_name || delivery.event_type"
            />
            <v-divider />
            <v-list-item prepend-icon="mdi-clock-outline" subtitle="创建时间" :title="delivery.created_at" />
            <v-divider />
            <v-list-item prepend-icon="mdi-clock-check-outline" subtitle="最早可执行时间" :title="delivery.available_at" />
            <v-divider />
            <v-list-item prepend-icon="mdi-update" subtitle="最近更新时间" :title="delivery.updated_at" />
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

        <v-expansion-panels class="mt-6">
          <v-expansion-panel title="技术信息">
            <v-expansion-panel-text>
              <dl class="delivery-detail__metadata">
                <dt>事件 ID</dt><dd>{{ delivery.event_id }}</dd>
                <dt>处理器</dt><dd>{{ delivery.handler_key }}</dd>
                <dt>队列</dt><dd>{{ delivery.queue_name }}</dd>
                <dt>执行模式</dt><dd>{{ delivery.execution_mode }}</dd>
                <dt>策略版本</dt><dd>{{ delivery.policy_revision ?? '旧记录未配置' }}</dd>
                <dt>策略判断</dt><dd>{{ policyDecisionLabel(delivery.policy_decision) }}</dd>
                <dt>关联 ID</dt><dd>{{ delivery.correlation_id || '未提供' }}</dd>
              </dl>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>

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

function policyDecisionLabel(decision: OperationsDelivery['policy_decision']) {
  if (decision === 'skipped') return '已按策略跳过通知'
  if (decision === 'applied') return '已执行通知投影'
  if (decision === 'not-configured') return '旧记录或事件未配置策略'
  return '无策略判断记录'
}

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
.delivery-detail__metadata { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 8px 16px; }
.delivery-detail__metadata dd { margin: 0; overflow-wrap: anywhere; }
</style>
