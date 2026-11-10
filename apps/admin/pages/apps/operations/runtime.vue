<template>
  <AdminPageContainer>
    <AdminPageHeader title="运行状态" description="先确认通信是否正常，再查看事件处理和部署诊断。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" :disabled="hasChanges" @click="load">刷新</v-btn>
      </template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading && !runtime" :error="error && !runtime ? error : null" :empty="!runtime" @retry="load">
      <template v-if="runtime && communication">
          <v-alert v-if="error" class="mb-4" type="warning" variant="tonal">刷新失败，以下是上次读取的状态：{{ error }}</v-alert>
          <v-alert v-if="configConflict" class="mb-4" type="warning" variant="tonal">{{ configConflict }}</v-alert>
        <v-sheet border rounded class="pa-4 mb-5">
          <div class="d-flex flex-wrap align-center justify-space-between ga-4">
            <div>
              <h2 class="text-h6 mb-2">当前通信</h2>
              <div class="d-flex align-center flex-wrap ga-3">
                <AdminStatusChip :status="communication.status === 'ready' ? 'available' : 'degraded'" :label="communication.status === 'ready' ? '正常' : '故障'" />
                <span class="text-body-1">已选择{{ modeLabel(communication.desired_mode) }}获取主动更新</span>
              </div>
              <div class="text-body-2 text-medium-emphasis mt-2">最近检查：{{ communication.checked_at }} · 配置版本 {{ communication.revision }}</div>
            </div>
            <v-btn to="/apps/operations/deliveries" variant="text" append-icon="mdi-arrow-right">查看通知处理</v-btn>
          </div>
          <v-alert v-if="communication.status === 'degraded'" class="mt-4" type="warning" variant="tonal" icon="mdi-alert-outline">
            {{ modeLabel(communication.desired_mode) }}模式暂不可用：{{ reasonLabel(communication.reason_code) }}。主动提示已暂停；业务数据仍可手动刷新。请核对下方诊断或手动切换轮询。
          </v-alert>
          <v-alert v-if="communication.last_apply_state && communication.last_apply_state !== 'applied'" class="mt-4" type="info" variant="tonal">
            最近一次配置操作未生效：{{ communication.last_apply_state === 'rejected' ? '参数被拒绝' : '执行失败' }}{{ communication.last_apply_reason ? `（${communication.last_apply_reason}）` : '' }}。
          </v-alert>
        </v-sheet>

        <section aria-labelledby="runtime-summary-title" class="mb-6">
          <div class="d-flex align-center justify-space-between mb-3">
            <h2 id="runtime-summary-title" class="text-h6">需要处理的情况</h2>
            <span v-if="!hasRuntimeAttention" class="text-body-2 text-medium-emphasis">当前没有需要处理的异常</span>
          </div>
          <v-alert v-if="hasRuntimeAttention" :type="runtime.eventing.dead ? 'error' : 'warning'" variant="tonal" icon="mdi-alert-circle-outline">
            <div v-if="communication.status === 'degraded'">实时主动提示已暂停：{{ reasonLabel(communication.reason_code) }}。可以查看投递记录并处理故障。</div>
            <div v-if="runtime.eventing.dead">有 {{ runtime.eventing.dead }} 条事件进入失败状态。</div>
            <div v-if="runtime.eventing.expired_processing">有 {{ runtime.eventing.expired_processing }} 条处理租约已过期。</div>
          </v-alert>
          <v-sheet v-else border rounded class="pa-4 text-body-2">通信、事件处理和当前部署检查均未报告异常。</v-sheet>
        </section>

        <v-sheet border rounded class="pa-4 mb-6">
          <div class="d-flex flex-wrap align-center justify-space-between ga-3">
            <div>
              <div class="text-subtitle-1 font-weight-medium">通信方式</div>
              <div class="text-body-2 text-medium-emphasis mt-1">已选择：{{ modeLabel(communication.desired_mode) }} · 状态：{{ communication.status === 'ready' ? '正常' : '故障' }}</div>
            </div>
            <AdminActionGuard capability="operations.runtime.configure">
              <v-btn :color="settingsOpen ? undefined : 'primary'" :variant="settingsOpen ? 'outlined' : 'flat'" @click="settingsOpen = !settingsOpen">{{ settingsOpen ? '收起设置' : '修改通信设置' }}</v-btn>
            </AdminActionGuard>
          </div>
          <div class="d-flex flex-wrap ga-6 mt-4 text-body-2">
            <span>待处理事件 <strong>{{ runtime.eventing.pending }}</strong></span>
            <span>处理中 <strong>{{ runtime.eventing.processing }}</strong></span>
            <span>失败 <strong>{{ runtime.eventing.dead }}</strong></span>
            <span v-if="runtime.eventing.oldest_pending_age_seconds !== null && runtime.eventing.oldest_pending_age_seconds !== undefined">最早待处理 {{ formatAge(runtime.eventing.oldest_pending_age_seconds) }}</span>
          </div>
          <AdminActionGuard capability="operations.runtime.configure">
            <v-expand-transition>
              <div v-if="settingsOpen" class="mt-5">
                <v-divider class="mb-5" />
                <h3 class="text-subtitle-1 mb-3">通信方式与刷新频率</h3>
                <v-btn-toggle v-model="form.desired_mode" mandatory density="comfortable" class="mb-4">
                  <v-btn value="polling">轮询</v-btn>
                  <v-btn value="realtime">实时</v-btn>
                </v-btn-toggle>
                <div class="text-body-2 text-medium-emphasis mb-4">实时模式依赖 Redis、Gateway 和 Event Worker；启用前必须通过检查。运行中故障不会自动切换轮询。<NuxtLink to="/apps/settings/redis">配置 Redis</NuxtLink></div>
                <v-row dense>
                  <v-col v-for="field in primaryNumericFields" :key="field.key" cols="12" sm="6" md="4">
                    <v-text-field v-model="form[field.key]" :label="field.label" type="number" :hint="field.hint" persistent-hint density="compact" variant="outlined" />
                  </v-col>
                  <v-col cols="12" sm="6" md="4"><v-switch v-model="form.pause_when_hidden" label="页面隐藏时暂停轮询" density="compact" hide-details /></v-col>
                </v-row>
                <v-expansion-panels variant="accordion" class="mt-4">
                  <v-expansion-panel title="高级参数（诊断与部署策略）">
                    <v-expansion-panel-text>
                      <v-row dense>
                        <v-col v-for="field in advancedNumericFields" :key="field.key" cols="12" sm="6" md="4">
                          <v-text-field v-model="form[field.key]" :label="field.label" type="number" :hint="field.hint" persistent-hint density="compact" variant="outlined" />
                        </v-col>
                        <v-col cols="12" sm="6" md="4"><v-switch v-model="form.require_supervisor" label="要求部署进程治理" hint="只表示部署要求，不会自动启动进程" persistent-hint density="compact" /></v-col>
                      </v-row>
                    </v-expansion-panel-text>
                  </v-expansion-panel>
                </v-expansion-panels>
                <v-alert v-if="violations.length" class="mt-4" type="error" variant="tonal">
                  <div v-for="violation in violations" :key="violation.field + violation.message">{{ violation.field }}：{{ violation.message }}</div>
                </v-alert>
                <div class="d-flex flex-wrap mt-4 ga-2">
                  <v-btn variant="outlined" :loading="validating" @click="validateForm">校验参数</v-btn>
                  <v-btn color="primary" variant="flat" :disabled="!hasChanges" :loading="applying" @click="openApply">应用变更</v-btn>
                  <v-btn variant="text" prepend-icon="mdi-sync" :disabled="hasChanges" :loading="reconciling" @click="reconcile">重新探测</v-btn>
                </div>
              </div>
            </v-expand-transition>
          </AdminActionGuard>
        </v-sheet>

        <AdminDangerActionDialog v-model="applyDialog" title="应用通信运行时配置" action-label="确认应用" color="primary" :impact="applyImpact" :loading="applying" :error="applyError" @confirm="doApply" />

        <v-expansion-panels variant="accordion">
          <v-expansion-panel title="高级诊断" elevation="0" border>
            <v-expansion-panel-text>
              <p class="text-body-2 text-medium-emphasis mb-4">以下信息用于定位部署问题。正常使用时无需处理。</p>
              <h3 class="text-subtitle-1 mb-3">能力探测</h3>
              <AdminResourceTableShell class="mb-6">
                <v-data-table :headers="capabilityHeaders" :items="communication.capabilities" item-value="key" :items-per-page="-1" hide-default-footer>
                  <template #item.ready="{ item }"><AdminStatusChip :status="capabilityStatus(item)" :label="capabilityLabel(item)" /></template>
                  <template #item.reason="{ item }">{{ reasonLabel(item.reason_code) }}</template>
                </v-data-table>
              </AdminResourceTableShell>
              <h3 class="text-subtitle-1 mb-3">处理进程</h3>
              <AdminResourceTableShell class="mb-6">
                <v-data-table :headers="workerHeaders" :items="runtime.workers" item-value="worker_id" items-per-page-text="每页行数">
                  <template #item.state="{ item }"><AdminStatusChip :status="item.online ? 'active' : item.state" :label="item.online ? '在线' : '离线'" /></template>
                </v-data-table>
              </AdminResourceTableShell>
              <h3 class="text-subtitle-1 mb-3">事件队列</h3>
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
const configConflict = ref('')

const communication = computed<RuntimeCommunicationSnapshot | null>(() => runtime.value?.communication ?? null)
const hasChanges = computed(() => {
  const current = communication.value
  if (!current) return false
  if (form.desired_mode !== current.desired_mode) return true
  return Object.entries(current.settings).some(([key, value]) => String(form[key as keyof typeof form]) !== String(value))
})

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
const primaryNumericFields = [
  { key: 'notification_interval_seconds', label: '通知轮询间隔（秒）', hint: '5~300' },
  { key: 'message_interval_seconds', label: '私信轮询间隔（秒）', hint: '5~300' },
  { key: 'unread_interval_seconds', label: '未读数轮询间隔（秒）', hint: '5~120' },
] as const
const advancedNumericFields = [
  { key: 'polling_backoff_factor', label: '退避倍率', hint: '1~5' },
  { key: 'polling_max_interval_seconds', label: '轮询上限间隔（秒）', hint: '60~900' },
  { key: 'worker_stale_seconds', label: 'Worker 心跳过期窗口（秒）', hint: '10~300' },
  { key: 'scheduler_stale_seconds', label: '调度补偿台账窗口（秒）', hint: '300~86400' },
  { key: 'gateway_stale_seconds', label: 'Gateway 探测缓存（秒）', hint: '5~120' },
] as const

const validating = ref(false)
const applying = ref(false)
const reconciling = ref(false)
const settingsOpen = ref(false)
const applyDialog = ref(false)
const applyError = ref<string | undefined>(undefined)
const violations = ref<Array<{ field: string; message: string }>>([])

const capabilityHeaders = [
  { title: '能力', key: 'key' }, { title: '状态', key: 'ready' }, { title: 'Provider', key: 'provider' },
  { title: '原因', key: 'reason' }, { title: '检查时间', key: 'checked_at' },
]
const workerHeaders = [
  { title: 'Worker', key: 'worker_id' }, { title: '类型', key: 'worker_type' }, { title: '状态', key: 'state' },
  { title: '已处理', key: 'jobs_processed' }, { title: '心跳时间', key: 'heartbeat_at' },
]
const queueHeaders = [
  { title: '队列', key: 'queue_name' }, { title: '执行模式', key: 'execution_mode' },
  { title: '待处理', key: 'pending' }, { title: '处理中', key: 'processing' }, { title: '死信', key: 'dead' },
]

const hasRuntimeAttention = computed(() => {
  const snapshot = communication.value
  return Boolean(
    snapshot?.status === 'degraded'
      || runtime.value?.eventing.dead
      || runtime.value?.eventing.expired_processing,
  )
})

const reasonLabels: Record<string, string> = {
  'config-locked': '环境变量已锁定运行模式',
  'schema-not-ready': '运行时配置表未就绪（请先执行迁移）',
  'worker-heartbeat-stale': 'Event Worker 心跳超时或未运行',
  'worker-realtime-queue-missing': 'Event Worker 未声明消费实时信号队列',
  'worker-release-mismatch': 'Event Worker 与当前 Release 不一致',
  'gateway-not-configured': '未配置 Realtime Gateway',
  'gateway-unreachable': 'Gateway 健康检查不可达',
  'gateway-protocol-mismatch': 'Gateway 协议版本或游标能力不匹配',
  'gateway-auth-unavailable': 'Gateway 鉴权凭证不可用',
  'gateway-not-ready': 'Gateway 自报未就绪',
  'realtime-transport-degraded': 'Redis 实时信号通道不可用',
  'redis-not-configured': 'Redis 尚未配置',
  'redis-unreachable': 'Redis 连接不可用',
  'supervisor-required-missing': '部署策略要求 Supervisor，但未探测到',
  'probe-stale': '探测结果过期或从未运行',
  'probe-unavailable': '探测暂不可用',
  'not-required': '部署策略未要求',
}
function reasonLabel(code: string | null | undefined) {
  if (!code) return '运行正常'
  return reasonLabels[code] ?? '需要关注'
}
function modeLabel(mode: string) { return mode === 'realtime' ? '实时' : mode === 'polling' ? '轮询' : mode }
function formatAge(seconds: number) {
  if (seconds < 60) return `${seconds} 秒`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes} 分钟`
  return `${Math.floor(minutes / 60)} 小时`
}
function capabilityStatus(item: { ready: boolean | null }) {
  if (item.ready === null) return 'disabled'
  return item.ready ? 'available' : 'degraded'
}
function capabilityLabel(item: { ready: boolean | null }) {
  if (item.ready === null) return '不适用'
  return item.ready ? '可用' : '不可用'
}

function executionModeLabel(mode: string) {
  return mode === 'request_safe' ? '请求内处理' : mode === 'daemon_required' ? '常驻进程' : mode
}

function syncFormFromSnapshot(snapshot: RuntimeCommunicationSnapshot | null) {
  if (!snapshot) return
  form.desired_mode = snapshot.desired_mode
  Object.assign(form, snapshot.settings)
}

const applyImpact = computed(() => {
  const current = communication.value
  if (!current) return '即将修改通信运行时配置。'
  const changes: string[] = []
  if (current.desired_mode !== form.desired_mode) {
    changes.push(`通信方式：${modeLabel(current.desired_mode)} → ${modeLabel(form.desired_mode)}`)
  }
  const fieldLabels: Record<string, string> = Object.fromEntries(
    [...primaryNumericFields, ...advancedNumericFields].map((field) => [field.key, field.label]),
  )
  fieldLabels.pause_when_hidden = '页面隐藏时暂停轮询'
  fieldLabels.require_supervisor = '要求部署进程治理'
  for (const [key, value] of Object.entries(current.settings)) {
    const next = form[key as keyof typeof form]
    if (String(value) === String(next)) continue
    const display = (item: unknown) => typeof item === 'boolean' ? (item ? '是' : '否') : String(item)
    changes.push(`${fieldLabels[key] ?? key}：${display(value)} → ${display(next)}`)
  }
  return `${changes.join('；')}。开启实时需通过依赖检查，运行中故障会暂停主动提示，不会自动切换轮询。本次操作写入审计日志。`
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

async function validateForm(): Promise<boolean> {
  validating.value = true
  violations.value = []
  try {
    const result = await client.operations.validateRuntimeConfig(buildUpdate())
    violations.value = result.violations
    if (result.valid) violations.value = []
    return result.valid
  }
  catch (cause) {
    violations.value = [{ field: 'request', message: isApiError(cause) ? cause.message : '校验请求失败' }]
    return false
  }
  finally { validating.value = false }
}

async function openApply() {
  if (!hasChanges.value || validating.value) return
  applyError.value = undefined
  if (!await validateForm()) return
  configConflict.value = ''
  applyDialog.value = true
}

async function doApply() {
  applying.value = true
  applyError.value = undefined
  try {
    const snapshot = await client.operations.applyRuntimeConfig(buildUpdate())
    applyDialog.value = false
    await load()
    configConflict.value = ''
    notifier.success('通信运行时配置已应用并写入审计')
    void snapshot
  }
  catch (cause) {
    if (isApiError(cause)) {
      applyError.value = cause.message
      if (cause.status === 409) {
        applyDialog.value = false
        try {
          runtime.value = await client.operations.runtime()
          configConflict.value = '配置已被其他操作更新。你的修改仍保留，请核对最新配置并重新审阅。'
        } catch {
          configConflict.value = '配置已被其他操作更新，暂时无法读取最新版本。你的修改仍保留，请稍后刷新。'
        }
      }
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
