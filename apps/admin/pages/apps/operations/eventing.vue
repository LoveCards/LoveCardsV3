<template>
  <AdminPageContainer>
    <AdminPageHeader title="事件处理" description="选择定时或常驻消费，并查看事件积压与处理进程。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" :disabled="hasUnsavedChanges" @click="load">刷新</v-btn>
      </template>
    </AdminPageHeader>

    <AdminAsyncState :loading="loading && !runtime" :error="error && !runtime ? error : null" :empty="!runtime" @retry="load">
      <template v-if="runtime">
        <v-alert v-if="error" class="mb-4" type="warning" variant="tonal">刷新失败，以下是上次读取的状态：{{ error }}</v-alert>
        <v-alert v-if="configConflict" class="mb-4" type="warning" variant="tonal">{{ configConflict }}</v-alert>

        <v-sheet border rounded class="pa-4 mb-5">
          <div class="d-flex flex-wrap align-center justify-space-between ga-3">
            <div>
              <h2 class="text-h6 mb-2">当前事件策略</h2>
              <div class="d-flex align-center flex-wrap ga-3">
                <AdminStatusChip :status="eventProcessing?.status === 'ready' ? 'available' : 'degraded'" :label="eventProcessing?.state === 'draining' ? '切换中' : eventProcessing?.status === 'ready' ? '正常' : '需维护'" />
                <span class="text-body-1">{{ eventModeLabel(eventProcessing?.mode) }}</span>
              </div>
              <div class="text-body-2 text-medium-emphasis mt-2">{{ eventProcessing?.state === 'draining' ? `正在切换到${eventModeLabel(eventProcessing.target_mode)}，等待在途任务或租约结算后继续推进。` : `状态：${reasonLabel(eventProcessing?.reason_code)}` }}</div>
              <div v-if="eventProcessing?.mode === 'scheduled'" class="text-body-2 text-medium-emphasis mt-1">触发方式：{{ triggerLabel(eventProcessing.scheduled_trigger) }} · 最近执行：{{ eventProcessing.note?.finished_at ?? '尚无记录' }}</div>
            </div>
            <AdminActionGuard capability="operations.runtime.configure">
              <v-btn :variant="settingsOpen ? 'outlined' : 'flat'" :color="settingsOpen ? undefined : 'primary'" @click="settingsOpen = !settingsOpen">{{ settingsOpen ? '收起设置' : '修改事件处理' }}</v-btn>
            </AdminActionGuard>
          </div>
          <v-alert v-if="eventProcessing?.status === 'degraded'" class="mt-4" type="warning" variant="tonal">
            事件处理当前不可用：{{ reasonLabel(eventProcessing.reason_code) }}。待处理任务会保存在 MySQL，服务恢复或人工切换后继续消费。
          </v-alert>
          <AdminActionGuard capability="operations.runtime.configure">
            <v-expand-transition>
              <div v-if="settingsOpen" class="mt-5">
                <v-divider class="mb-5" />
                <div class="text-subtitle-1 mb-3">消费策略</div>
                <v-btn-toggle v-model="form.mode" mandatory density="comfortable" class="mb-4">
                  <v-btn value="scheduled">定时消费</v-btn>
                  <v-btn value="resident">常驻消费</v-btn>
                </v-btn-toggle>
                <div class="text-body-2 text-medium-emphasis mb-4">定时消费需要外部 cron 或受认证的 HTTPS POST 定时触发；常驻消费需要 Event Worker。两种策略不会自动代班。</div>
                <v-select v-if="form.mode === 'scheduled'" v-model="form.scheduled_trigger" :items="triggerOptions" item-title="title" item-value="value" label="已配置的定时触发方式" hint="此项是部署声明；还需查看最近一次执行是否成功" persistent-hint variant="outlined" density="compact" />
                <v-alert v-if="form.mode === 'scheduled' && communication?.desired_mode === 'realtime'" type="info" variant="tonal" class="mb-4">请先在<NuxtLink to="/apps/operations/communication">前端通信</NuxtLink>切换为轮询，再启用定时消费。</v-alert>
                <div class="d-flex flex-wrap ga-2 mt-4">
                  <v-btn color="primary" :disabled="!hasChanges || eventProcessing?.mode === 'unknown' || (form.mode === 'scheduled' && communication?.desired_mode === 'realtime')" :loading="applying" @click="openApply">{{ eventProcessing?.state === 'draining' ? '继续切换' : '应用事件策略' }}</v-btn>
                </div>
              </div>
            </v-expand-transition>
          </AdminActionGuard>
        </v-sheet>

        <AdminDangerActionDialog v-model="applyDialog" title="切换事件消费策略" action-label="确认应用" color="primary" :impact="applyImpact" :loading="applying" :error="applyError" @confirm="doApply" />

        <v-alert v-if="runtime.eventing.dead || runtime.eventing.expired_processing" :type="runtime.eventing.dead ? 'error' : 'warning'" variant="tonal" icon="mdi-alert-circle-outline" class="mb-5">
          <div v-if="runtime.eventing.dead">有 {{ runtime.eventing.dead }} 条事件进入失败状态。</div>
          <div v-if="runtime.eventing.expired_processing">有 {{ runtime.eventing.expired_processing }} 条处理租约已过期。</div>
        </v-alert>

        <v-sheet border rounded class="pa-4 mb-5">
          <div class="d-flex flex-wrap align-center justify-space-between ga-3">
            <h2 class="text-h6">事件队列</h2>
            <v-btn to="/apps/operations/deliveries" variant="text" append-icon="mdi-arrow-right">查看通知处理</v-btn>
          </div>
          <div class="d-flex flex-wrap ga-6 mt-3 text-body-2">
            <span>待处理 <strong>{{ runtime.eventing.pending }}</strong></span>
            <span>处理中 <strong>{{ runtime.eventing.processing }}</strong></span>
            <span>失败 <strong>{{ runtime.eventing.dead }}</strong></span>
            <span v-if="runtime.eventing.oldest_pending_age_seconds !== null && runtime.eventing.oldest_pending_age_seconds !== undefined">最早待处理 {{ formatAge(runtime.eventing.oldest_pending_age_seconds) }}</span>
          </div>
          <p class="text-body-2 text-medium-emphasis mt-3 mb-0">通知投递是事件队列中的一类任务；前端通信只负责把变化提示送到浏览器。</p>
        </v-sheet>

        <v-expansion-panels variant="accordion">
          <v-expansion-panel title="高级诊断" elevation="0" border>
            <v-expansion-panel-text>
              <p class="text-body-2 text-medium-emphasis mb-4">以下信息用于定位事件消费与部署问题。</p>
              <h3 class="text-subtitle-1 mb-3">处理进程</h3>
              <AdminResourceTableShell class="mb-6">
                <v-data-table :headers="workerHeaders" :items="runtime.workers" item-value="worker_id" items-per-page-text="每页行数">
                  <template #item.state="{ item }"><AdminStatusChip :status="item.online ? 'active' : item.state" :label="item.online ? '在线' : '离线'" /></template>
                </v-data-table>
              </AdminResourceTableShell>
              <h3 class="text-subtitle-1 mb-3">队列分布</h3>
              <AdminResourceTableShell v-if="runtime.eventing.queues?.length">
                <v-data-table :headers="queueHeaders" :items="runtime.eventing.queues" item-value="queue_name" :items-per-page="-1" hide-default-footer>
                  <template #item.execution_mode="{ item }"><AdminStatusChip :status="item.execution_mode" :label="executionModeLabel(item.execution_mode)" /></template>
                </v-data-table>
              </AdminResourceTableShell>
              <v-sheet v-else border rounded class="pa-4 text-body-2 text-medium-emphasis">暂无队列分布数据。</v-sheet>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
      </template>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { EventConsumptionUpdate, OperationsRuntimeStatus } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { isApiError, useApi } from '~/lib/api'

const client = useApi()
const notifier = useAdminNotifier()
const runtime = ref<OperationsRuntimeStatus | null>(null)
const communication = computed(() => runtime.value?.communication ?? null)
const eventProcessing = computed(() => runtime.value?.event_processing ?? null)
const loading = ref(false)
const error = ref<string | null>(null)
const configConflict = ref('')
const settingsOpen = ref(false)
const applying = ref(false)
const applyDialog = ref(false)
const applyError = ref<string | undefined>(undefined)
const form = reactive<{ mode: 'scheduled' | 'resident'; scheduled_trigger: 'none' | 'cli' | 'http' }>({ mode: 'scheduled', scheduled_trigger: 'none' })
const triggerOptions = [
  { title: '尚未配置', value: 'none' },
  { title: 'CLI cron', value: 'cli' },
  { title: 'HTTPS POST 定时触发', value: 'http' },
]
const hasChanges = computed(() => {
  const current = eventProcessing.value
  if (!current) return false
  return current.state === 'draining' || form.mode !== current.mode || (form.mode === 'scheduled' && form.scheduled_trigger !== current.scheduled_trigger)
})
const hasUnsavedChanges = computed(() => {
  const current = eventProcessing.value
  if (!current) return false
  return form.mode !== (current.state === 'draining' && current.target_mode ? current.target_mode : current.mode)
    || (form.mode === 'scheduled' && form.scheduled_trigger !== current.scheduled_trigger)
})
const workerHeaders = [
  { title: 'Worker', key: 'worker_id' }, { title: '类型', key: 'worker_type' }, { title: '状态', key: 'state' },
  { title: '已处理', key: 'jobs_processed' }, { title: '心跳时间', key: 'heartbeat_at' },
]
const queueHeaders = [
  { title: '队列', key: 'queue_name' }, { title: '执行模式', key: 'execution_mode' },
  { title: '待处理', key: 'pending' }, { title: '处理中', key: 'processing' }, { title: '死信', key: 'dead' },
]
const reasonLabels: Record<string, string> = {
  'config-locked': '环境变量已锁定运行模式',
  'schema-not-ready': '运行时配置表未就绪（请先执行迁移）',
  'worker-heartbeat-stale': 'Event Worker 心跳超时或未运行',
  'worker-required-queue-missing': 'Event Worker 未覆盖全部业务任务队列',
  'worker-release-mismatch': 'Event Worker 与当前 Release 不一致',
  'event-consumer-unavailable': '事件消费状态表未就绪',
  'event-consumer-draining': '事件策略切换中，等待在途任务结算',
  'scheduler-trigger-unconfigured': '尚未声明定时触发源',
  'scheduler-run-failed': '最近一次定时执行失败',
  'scheduler-trigger-mismatch': '最近一次执行与声明的触发方式不一致',
  'probe-stale': '探测结果过期或从未运行',
  'probe-unavailable': '探测暂不可用',
}
function reasonLabel(code: string | null | undefined) { return code ? reasonLabels[code] ?? '需要关注' : '运行正常' }
function eventModeLabel(mode: string | null | undefined) { return mode === 'resident' ? '常驻消费' : mode === 'scheduled' ? '定时消费' : '不可用' }
function triggerLabel(value: string | null | undefined) { return triggerOptions.find(item => item.value === value)?.title ?? '未知' }
function executionModeLabel(mode: string) { return mode === 'request_safe' ? '短任务（Handler 声明）' : mode === 'daemon_required' ? '后台任务（Handler 声明）' : mode }
function formatAge(seconds: number) {
  if (seconds < 60) return `${seconds} 秒`
  const minutes = Math.floor(seconds / 60)
  return minutes < 60 ? `${minutes} 分钟` : `${Math.floor(minutes / 60)} 小时`
}
function syncForm() {
  const current = eventProcessing.value
  if (!current || current.mode === 'unknown') return
  form.mode = current.state === 'draining' && current.target_mode ? current.target_mode : current.mode
  form.scheduled_trigger = current.scheduled_trigger
}
const applyImpact = computed(() => {
  const current = eventProcessing.value
  if (!current) return '事件消费状态不可用。'
  if (current.state === 'draining') return `继续推进切换到${eventModeLabel(current.target_mode)}，在途任务或租约结算前不会启用新策略。本次操作写入审计日志。`
  return `事件消费：${eventModeLabel(current.mode)} → ${eventModeLabel(form.mode)}；定时触发：${triggerLabel(form.scheduled_trigger)}。切换时暂停新任务领取，等待在途任务结算。本次操作写入审计日志。`
})
function openApply() {
  applyError.value = undefined
  applyDialog.value = true
}
async function doApply() {
  const current = eventProcessing.value
  if (!current) return
  applying.value = true
  applyError.value = undefined
  try {
    const input: EventConsumptionUpdate = {
      mode: form.mode,
      expected_revision: current.revision,
      scheduled_trigger: form.mode === 'scheduled' ? form.scheduled_trigger : undefined,
    }
    const result = await client.operations.applyEventConsumption(input)
    applyDialog.value = false
    await load()
    configConflict.value = ''
    notifier.success(result.state === 'draining' ? '切换已开始，待在途任务结算后点击“继续切换”' : '事件消费策略已应用')
  }
  catch (cause) {
    applyError.value = isApiError(cause) ? cause.message : '切换事件消费策略失败'
    if (isApiError(cause) && cause.status === 409) {
      applyDialog.value = false
      await load()
      configConflict.value = '事件消费状态已变化。请核对最新状态后重新操作。'
    }
  }
  finally { applying.value = false }
}
async function load() {
  loading.value = true
  error.value = null
  try {
    runtime.value = await client.operations.runtime()
    syncForm()
  }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法读取事件处理状态' }
  finally { loading.value = false }
}
onMounted(load)
</script>
