<template>
  <AdminPageContainer>
    <AdminPageHeader title="运行状态" description="核对当前部署实际启用的设施、通信模式、降级路径和 Worker 心跳。" :breadcrumbs="[{ title: '系统运维' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!runtime" @retry="load">
      <template v-if="runtime">
        <h2 class="text-h6 mb-3">通信模式</h2>
        <v-alert v-if="communication && communication.status === 'degraded'" class="mb-4" type="warning" variant="tonal">
          目标模式为 {{ modeLabel(communication.desired_mode) }}，但当前实际生效 {{ modeLabel(communication.effective_mode) }}：{{ reasonLabel(communication.reason_code) }}。业务契约保持一致，浏览器按轮询取数。
        </v-alert>
        <v-card v-if="communication" variant="outlined" class="mb-4 pa-4">
          <div class="d-flex flex-wrap align-center ga-3">
            <span>目标模式 <AdminStatusChip :status="communication.desired_mode === 'realtime' ? 'active' : 'available'" :label="modeLabel(communication.desired_mode)" /></span>
            <span>生效模式 <AdminStatusChip :status="communication.effective_mode === 'realtime' ? 'active' : 'available'" :label="modeLabel(communication.effective_mode)" /></span>
            <span>状态 <AdminStatusChip :status="communication.status === 'ready' ? 'available' : 'degraded'" :label="communication.status === 'ready' ? '就绪' : '降级'" /></span>
            <span class="text-caption text-medium-emphasis">配置版本 {{ communication.revision }} · 检查时间 {{ communication.checked_at }}</span>
          </div>
          <v-alert v-if="communication.last_apply_state && communication.last_apply_state !== 'applied'" class="mt-3" type="info" variant="tonal">
            最近一次配置操作未生效：{{ communication.last_apply_state === 'rejected' ? '参数被拒绝' : '执行失败' }}{{ communication.last_apply_reason ? `（${communication.last_apply_reason}）` : '' }}。
          </v-alert>
          <v-data-table class="mt-4" :headers="capabilityHeaders" :items="communication.capabilities" item-value="key" :items-per-page="10" items-per-page-text="每页行数">
            <template #item.ready="{ item }"><AdminStatusChip :status="capabilityStatus(item)" :label="capabilityLabel(item)" /></template>
            <template #item.reason="{ item }">{{ reasonLabel(item.reason_code) }}</template>
          </v-data-table>
          <AdminActionGuard capability="operations.runtime.configure">
            <v-divider class="my-4" />
            <h3 class="text-subtitle-1 mb-2">模式与运行参数</h3>
            <div class="mb-3">
              <v-btn-toggle v-model="form.desired_mode" mandatory dense>
                <v-btn value="polling">轮询</v-btn>
                <v-btn value="realtime">实时（需 Gateway 与 Worker）</v-btn>
              </v-btn-toggle>
            </div>
            <v-form @submit.prevent>
              <v-row dense>
                <v-col v-for="field in numericFields" :key="field.key" cols="12" sm="6" md="4">
                  <v-text-field v-model="form[field.key]" :label="field.label" type="number" :hint="field.hint" persistent-hint density="compact" variant="outlined" />
                </v-col>
                <v-col cols="12" sm="6" md="4">
                  <v-switch v-model="form.pause_when_hidden" label="页面隐藏时暂停轮询" density="compact" hide-details />
                </v-col>
                <v-col cols="12" sm="6" md="4">
                  <v-switch v-model="form.require_supervisor" label="部署要求 Supervisor 进程治理" density="compact" hide-details />
                </v-col>
              </v-row>
            </v-form>
            <v-alert v-if="violations.length" class="mt-3" type="error" variant="tonal">
              <div v-for="violation in violations" :key="violation.field + violation.message">{{ violation.field }}：{{ violation.message }}</div>
            </v-alert>
            <div class="d-flex flex-wrap mt-4 ga-2">
              <v-btn variant="outlined" :loading="validating" @click="validateForm">校验参数</v-btn>
              <v-btn color="primary" variant="elevated" :loading="applying" @click="openApply">应用变更</v-btn>
              <v-btn variant="text" prepend-icon="mdi-sync" :loading="reconciling" @click="reconcile">重新探测对账</v-btn>
            </div>
          </AdminActionGuard>
        </v-card>

        <AdminDangerActionDialog
          v-model="applyDialog"
          title="应用通信运行时配置"
          action-label="确认应用"
          :impact="applyImpact"
          :loading="applying"
          :error="applyError"
          @confirm="doApply"
        />

        <v-alert v-if="degradedCount" class="mb-4" type="warning" variant="tonal">
          当前有 {{ degradedCount }} 项设施处于降级或不可用状态，业务契约保持一致，但实时性或吞吐可能降低。
        </v-alert>
        <AdminResourceTableShell>
          <v-data-table :headers="facilityHeaders" :items="runtime.facilities" item-value="name" items-per-page-text="每页行数">
            <template #item.name="{ item }"><strong>{{ item.name }}</strong><div class="text-caption text-medium-emphasis">{{ item.effective_provider || '未选择 Provider' }}</div></template>
            <template #item.mode="{ item }">{{ item.desired_mode }}</template>
            <template #item.status="{ item }"><AdminStatusChip :status="item.ready ? 'available' : item.enabled ? 'degraded' : 'disabled'" /></template>
            <template #item.reason="{ item }">{{ facilityReason(item.reason_code, item.ready) }}</template>
            <template #item.checked="{ item }">{{ item.checked_at }}</template>
          </v-data-table>
        </AdminResourceTableShell>

        <h2 class="text-h6 mt-8 mb-3">Worker 心跳</h2>
        <AdminResourceTableShell>
          <v-data-table :headers="workerHeaders" :items="runtime.workers" item-value="worker_id" items-per-page-text="每页行数">
            <template #item.state="{ item }"><AdminStatusChip :status="item.online ? 'active' : item.state" /></template>
          </v-data-table>
        </AdminResourceTableShell>
        <v-alert class="mt-4" type="info" variant="tonal">
          Outbox：待处理 {{ runtime.eventing.pending }}，处理中 {{ runtime.eventing.processing }}，死信 {{ runtime.eventing.dead }}；最早待处理 {{ runtime.eventing.oldest_pending_at || '-' }}，过期租约 {{ runtime.eventing.expired_processing ?? 0 }}。
        </v-alert>
        <AdminResourceTableShell v-if="runtime.eventing.queues?.length" class="mt-4">
          <v-data-table :headers="queueHeaders" :items="runtime.eventing.queues" item-value="queue_name" items-per-page-text="每页行数">
            <template #item.execution_mode="{ item }"><AdminStatusChip :status="item.execution_mode" /></template>
          </v-data-table>
        </AdminResourceTableShell>
      </template>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
import type { OperationsRuntimeStatus, RuntimeCommunicationSnapshot, RuntimeConfigUpdate } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useApi, isApiError } from '~/lib/api'

const client = useApi()
const notifier = useAdminNotifier()
const runtime = ref<OperationsRuntimeStatus | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const communication = computed<RuntimeCommunicationSnapshot | null>(() => runtime.value?.communication ?? null)

const form = reactive({
  desired_mode: 'polling',
  notification_interval_seconds: 30,
  message_interval_seconds: 45,
  unread_interval_seconds: 20,
  polling_backoff_factor: 2,
  polling_max_interval_seconds: 300,
  pause_when_hidden: true,
  worker_stale_seconds: 30,
  scheduler_stale_seconds: 3600,
  gateway_stale_seconds: 15,
  require_supervisor: false,
})
const numericFields = [
  { key: 'notification_interval_seconds', label: '通知轮询间隔（秒）', hint: '5~300' },
  { key: 'message_interval_seconds', label: '私信轮询间隔（秒）', hint: '5~300' },
  { key: 'unread_interval_seconds', label: '未读数轮询间隔（秒）', hint: '5~120' },
  { key: 'polling_backoff_factor', label: '退避倍率', hint: '1~5' },
  { key: 'polling_max_interval_seconds', label: '轮询上限间隔（秒）', hint: '60~900' },
  { key: 'worker_stale_seconds', label: 'Worker 心跳过期窗口（秒）', hint: '10~300' },
  { key: 'scheduler_stale_seconds', label: '调度补偿台账窗口（秒）', hint: '300~86400' },
  { key: 'gateway_stale_seconds', label: 'Gateway 探测缓存（秒）', hint: '5~120' },
] as const

const validating = ref(false)
const applying = ref(false)
const reconciling = ref(false)
const applyDialog = ref(false)
const applyError = ref<string | undefined>(undefined)
const violations = ref<Array<{ field: string; message: string }>>([])

const capabilityHeaders = [
  { title: '能力', key: 'key' }, { title: '状态', key: 'ready' }, { title: 'Provider', key: 'provider' },
  { title: '原因', key: 'reason' }, { title: '检查时间', key: 'checked_at' },
]
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

const degradedCount = computed(() => runtime.value?.facilities.filter((item: OperationsRuntimeStatus['facilities'][number]) => !item.ready).length ?? 0)

const reasonLabels: Record<string, string> = {
  'config-locked': '环境变量已锁定运行模式',
  'schema-not-ready': '运行时配置表未就绪（请先执行迁移）',
  'worker-heartbeat-stale': 'Event Worker 心跳超时或未运行',
  'gateway-not-configured': '未配置 Realtime Gateway',
  'gateway-unreachable': 'Gateway 健康检查不可达',
  'gateway-protocol-mismatch': 'Gateway 协议版本或游标能力不匹配',
  'gateway-auth-unavailable': 'Gateway 鉴权凭证不可用',
  'gateway-not-ready': 'Gateway 自报未就绪',
  'realtime-transport-degraded': '实时信号通道降级（Gateway 已回源轮询）',
  'supervisor-required-missing': '部署策略要求 Supervisor，但未探测到',
  'probe-stale': '探测结果过期或从未运行',
  'probe-unavailable': '探测暂不可用',
  'not-required': '部署策略未要求',
}
function reasonLabel(code: string | null | undefined) {
  if (!code) return '运行正常'
  return reasonLabels[code] ?? '需要关注'
}
function modeLabel(mode: string) { return mode === 'realtime' ? '实时' : '轮询' }
function capabilityStatus(item: { ready: boolean | null }) {
  if (item.ready === null) return 'disabled'
  return item.ready ? 'available' : 'degraded'
}
function capabilityLabel(item: { ready: boolean | null }) {
  if (item.ready === null) return '不适用'
  return item.ready ? '可用' : '不可用'
}

function facilityReason(reason: string | null | undefined, ready: boolean) {
  if (ready) return '运行正常'
  return ({
    primary_not_configured: '主通道未配置',
    provider_unknown: '服务提供方未知',
  } as Record<string, string>)[reason || ''] ?? '需要关注'
}

function syncFormFromSnapshot(snapshot: RuntimeCommunicationSnapshot | null) {
  if (!snapshot) return
  form.desired_mode = snapshot.desired_mode
  Object.assign(form, snapshot.settings)
}

const applyImpact = computed(() => {
  const current = communication.value
  if (!current) return '即将修改通信运行时配置。'
  const modeChange = current.desired_mode !== form.desired_mode
    ? `目标模式 ${modeLabel(current.desired_mode)} → ${modeLabel(form.desired_mode)}`
    : '目标模式不变'
  return `${modeChange}；生效模式由服务端探测实时判定，Gateway 或 Worker 不可用时将自动保持轮询。本次操作写入审计日志。`
})

function buildUpdate(): RuntimeConfigUpdate {
  return {
    desired_mode: form.desired_mode as 'polling' | 'realtime',
    expected_revision: communication.value?.revision ?? 1,
    notification_interval_seconds: Number(form.notification_interval_seconds),
    message_interval_seconds: Number(form.message_interval_seconds),
    unread_interval_seconds: Number(form.unread_interval_seconds),
    polling_backoff_factor: Number(form.polling_backoff_factor),
    polling_max_interval_seconds: Number(form.polling_max_interval_seconds),
    pause_when_hidden: Boolean(form.pause_when_hidden),
    worker_stale_seconds: Number(form.worker_stale_seconds),
    scheduler_stale_seconds: Number(form.scheduler_stale_seconds),
    gateway_stale_seconds: Number(form.gateway_stale_seconds),
    require_supervisor: Boolean(form.require_supervisor),
  }
}

async function validateForm() {
  validating.value = true
  violations.value = []
  try {
    const result = await client.operations.validateRuntimeConfig(buildUpdate())
    violations.value = result.violations
    if (result.valid) violations.value = []
  }
  catch (cause) { violations.value = [{ field: 'request', message: isApiError(cause) ? cause.message : '校验请求失败' }] }
  finally { validating.value = false }
}

function openApply() {
  applyError.value = undefined
  violations.value = []
  applyDialog.value = true
}

async function doApply() {
  applying.value = true
  applyError.value = undefined
  try {
    const snapshot = await client.operations.applyRuntimeConfig(buildUpdate())
    applyDialog.value = false
    await load()
    notifier.success('通信运行时配置已应用并写入审计')
    void snapshot
  }
  catch (cause) {
    if (isApiError(cause)) {
      applyError.value = cause.message
      if (cause.status === 409) await load()
    } else applyError.value = '应用配置失败'
  }
  finally { applying.value = false }
}

async function reconcile() {
  reconciling.value = true
  try {
    await client.operations.reconcileRuntime()
    await load()
  }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '对账失败' }
  finally { reconciling.value = false }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    runtime.value = await client.operations.runtime()
    syncFormFromSnapshot(communication.value)
  }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法读取运行环境' }
  finally { loading.value = false }
}

onMounted(load)
</script>
