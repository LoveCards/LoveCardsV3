<template>
  <AdminPageContainer>
    <AdminPageHeader title="前端通信" description="管理浏览器获取主动更新的方式，并检查实时通信依赖。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" :disabled="hasChanges" @click="load">刷新</v-btn>
      </template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading && !runtime" :error="error && !runtime ? error : null" :empty="!runtime" @retry="load">
      <template v-if="runtime && communication">
        <v-alert v-if="error" class="mb-4" type="warning" variant="tonal">刷新失败，以下是上次读取的状态：{{ error }}</v-alert>
        <v-alert v-if="configConflict" class="mb-4" type="warning" variant="tonal">{{ configConflict }}</v-alert>

        <v-sheet border rounded class="pa-4 mb-5">
          <div class="d-flex flex-wrap align-center justify-space-between ga-3">
            <div>
              <h2 class="text-h6 mb-2">当前通信</h2>
              <div class="d-flex align-center flex-wrap ga-3">
                <AdminStatusChip :status="communication.status === 'ready' ? 'available' : 'degraded'" :label="communication.status === 'ready' ? '正常' : '故障'" />
                <span class="text-body-1">已选择{{ modeLabel(communication.desired_mode) }}获取主动更新</span>
              </div>
              <div class="text-body-2 text-medium-emphasis mt-2">最近检查：{{ communication.checked_at }} · 配置版本 {{ communication.revision }}</div>
            </div>
            <v-btn to="/apps/operations/eventing" variant="text" append-icon="mdi-arrow-right">查看事件处理</v-btn>
          </div>
          <v-alert v-if="communication.status === 'degraded'" class="mt-4" type="warning" variant="tonal" icon="mdi-alert-outline">
            {{ modeLabel(communication.desired_mode) }}模式暂不可用：{{ reasonLabel(communication.reason_code) }}。主动提示已暂停；业务数据仍可手动刷新。请核对下方诊断或手动切换轮询。
          </v-alert>
          <v-alert v-if="communication.last_apply_state && communication.last_apply_state !== 'applied'" class="mt-4" type="info" variant="tonal">
            最近一次配置操作未生效：{{ communication.last_apply_state === 'rejected' ? '参数被拒绝' : '执行失败' }}{{ communication.last_apply_reason ? `（${communication.last_apply_reason}）` : '' }}。
          </v-alert>
        </v-sheet>

        <v-sheet border rounded class="pa-4 mb-5">
          <div class="d-flex flex-wrap align-center justify-space-between ga-3">
            <div>
              <h2 class="text-h6 mb-2">通信方式</h2>
              <div class="text-body-2 text-medium-emphasis">轮询由浏览器定时读取 API；实时由 Gateway 推送变化提示。发送和读取完整业务数据仍走 PHP API。</div>
            </div>
            <AdminActionGuard capability="operations.runtime.configure">
              <v-btn :color="settingsOpen ? undefined : 'primary'" :variant="settingsOpen ? 'outlined' : 'flat'" @click="settingsOpen = !settingsOpen">{{ settingsOpen ? '收起设置' : '修改通信设置' }}</v-btn>
            </AdminActionGuard>
          </div>
          <v-alert v-if="eventProcessing && (eventProcessing.mode !== 'resident' || eventProcessing.state !== 'active')" class="mt-4" type="info" variant="tonal">
            当前事件处理为{{ eventModeLabel(eventProcessing.mode) }}{{ eventProcessing.state === 'draining' ? '，正在切换' : '' }}。启用实时通信前需先在<NuxtLink to="/apps/operations/eventing">事件处理</NuxtLink>启用常驻消费并确认就绪。
          </v-alert>
          <AdminActionGuard capability="operations.runtime.configure">
            <v-expand-transition>
              <div v-if="settingsOpen" class="mt-5">
                <v-divider class="mb-5" />
                <v-btn-toggle v-model="form.desired_mode" mandatory density="comfortable" class="mb-4">
                  <v-btn value="polling">轮询</v-btn>
                  <v-btn value="realtime" :disabled="eventProcessing?.mode !== 'resident' || eventProcessing?.state !== 'active'">实时</v-btn>
                </v-btn-toggle>
                <div class="text-body-2 text-medium-emphasis mb-4">实时模式依赖常驻事件处理、Redis、Gateway 和 Event Worker。运行中故障不会自动切换轮询。<NuxtLink to="/apps/settings/redis">配置 Redis</NuxtLink></div>
                <v-row dense>
                  <v-col v-for="field in primaryNumericFields" :key="field.key" cols="12" sm="6" md="4">
                    <v-text-field v-model="form[field.key]" :label="field.label" type="number" :hint="field.hint" persistent-hint density="compact" variant="outlined" />
                  </v-col>
                  <v-col cols="12" sm="6" md="4"><v-switch v-model="form.pause_when_hidden" label="页面隐藏时暂停轮询" density="compact" hide-details /></v-col>
                </v-row>
                <v-expansion-panels variant="accordion" class="mt-4">
                  <v-expansion-panel title="高级参数（诊断窗口）">
                    <v-expansion-panel-text>
                      <v-row dense>
                        <v-col v-for="field in advancedNumericFields" :key="field.key" cols="12" sm="6" md="4">
                          <v-text-field v-model="form[field.key]" :label="field.label" type="number" :hint="field.hint" persistent-hint density="compact" variant="outlined" />
                        </v-col>
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
          <v-expansion-panel title="实时依赖诊断" elevation="0" border>
            <v-expansion-panel-text>
              <p class="text-body-2 text-medium-emphasis mb-4">Redis 连接在系统配置中维护；事件消费策略和 Worker 状态在事件处理页面管理。</p>
              <AdminResourceTableShell>
                <v-data-table :headers="capabilityHeaders" :items="communication.capabilities" item-value="key" :items-per-page="-1" hide-default-footer>
                  <template #item.ready="{ item }"><AdminStatusChip :status="capabilityStatus(item)" :label="capabilityLabel(item)" /></template>
                  <template #item.reason="{ item }">{{ reasonLabel(item.reason_code) }}</template>
                </v-data-table>
              </AdminResourceTableShell>
              <div class="d-flex ga-2 mt-4">
                <v-btn to="/apps/settings/redis" variant="text">Redis 连接</v-btn>
                <v-btn to="/apps/operations/eventing" variant="text">事件处理</v-btn>
              </div>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
      </template>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsRuntimeStatus, RuntimeCommunicationSnapshot, RuntimeConfigUpdate } from '@lovecards/sdk'
import AdminResourceTableShell from '~/components/shared/admin/AdminResourceTableShell.vue'
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
const communication = computed<RuntimeCommunicationSnapshot | null>(() => runtime.value?.communication ?? null)
const eventProcessing = computed(() => runtime.value?.event_processing ?? null)
const loading = ref(false)
const error = ref<string | null>(null)
const configConflict = ref('')
const settingsOpen = ref(false)
const applyDialog = ref(false)
const applyError = ref<string | undefined>(undefined)
const validating = ref(false)
const applying = ref(false)
const reconciling = ref(false)
const violations = ref<Array<{ field: string; message: string }>>([])

const form = reactive({
  desired_mode: 'polling',
  notification_interval_seconds: 30,
  message_interval_seconds: 45,
  unread_interval_seconds: 20,
  pause_when_hidden: true,
  worker_stale_seconds: 30,
  scheduler_stale_seconds: 3600,
  gateway_stale_seconds: 15,
})
const primaryNumericFields = [
  { key: 'notification_interval_seconds', label: '通知轮询间隔（秒）', hint: '5~300' },
  { key: 'message_interval_seconds', label: '私信轮询间隔（秒）', hint: '5~300' },
  { key: 'unread_interval_seconds', label: '未读数轮询间隔（秒）', hint: '5~120' },
] as const
const advancedNumericFields = [
  { key: 'worker_stale_seconds', label: 'Worker 心跳过期窗口（秒）', hint: '10~300' },
  { key: 'scheduler_stale_seconds', label: '定时消费运行记录有效期（秒）', hint: '300~86400' },
  { key: 'gateway_stale_seconds', label: 'Gateway 探测缓存（秒）', hint: '5~120' },
] as const
const capabilityHeaders = [
  { title: '能力', key: 'key' }, { title: '状态', key: 'ready' }, { title: 'Provider', key: 'provider' },
  { title: '原因', key: 'reason' }, { title: '检查时间', key: 'checked_at' },
]
const hasChanges = computed(() => {
  const current = communication.value
  if (!current) return false
  if (form.desired_mode !== current.desired_mode) return true
  return Object.entries(current.settings).some(([key, value]) => String(form[key as keyof typeof form]) !== String(value))
})
const reasonLabels: Record<string, string> = {
  'config-locked': '环境变量已锁定运行模式',
  'schema-not-ready': '运行时配置表未就绪（请先执行迁移）',
  'worker-heartbeat-stale': 'Event Worker 心跳超时或未运行',
  'worker-realtime-queue-missing': 'Event Worker 未声明消费实时信号队列',
  'worker-required-queue-missing': 'Event Worker 未覆盖全部业务任务队列',
  'worker-release-mismatch': 'Event Worker 与当前 Release 不一致',
  'gateway-not-configured': '未配置 Realtime Gateway',
  'gateway-unreachable': 'Gateway 健康检查不可达',
  'gateway-protocol-mismatch': 'Gateway 协议版本或游标能力不匹配',
  'gateway-auth-unavailable': 'Gateway 鉴权凭证不可用',
  'gateway-not-ready': 'Gateway 自报未就绪',
  'realtime-transport-degraded': 'Redis 实时信号通道不可用',
  'redis-not-configured': 'Redis 尚未配置',
  'redis-unreachable': 'Redis 连接不可用',
  'event-consumer-unavailable': '事件消费状态表未就绪',
  'event-consumer-draining': '事件策略切换中，等待在途任务结算',
  'event-consumer-not-resident': '事件处理尚未切换为常驻消费',
  'probe-stale': '探测结果过期或从未运行',
  'probe-unavailable': '探测暂不可用',
  'not-required': '当前模式不要求',
}
function reasonLabel(code: string | null | undefined) { return code ? reasonLabels[code] ?? '需要关注' : '运行正常' }
function modeLabel(mode: string) { return mode === 'realtime' ? '实时' : mode === 'polling' ? '轮询' : mode }
function eventModeLabel(mode: string | null | undefined) { return mode === 'resident' ? '常驻消费' : mode === 'scheduled' ? '定时消费' : '不可用' }
function capabilityStatus(item: { ready: boolean | null }) { return item.ready === null ? 'disabled' : item.ready ? 'available' : 'degraded' }
function capabilityLabel(item: { ready: boolean | null }) { return item.ready === null ? '不适用' : item.ready ? '可用' : '不可用' }

function syncFormFromSnapshot(snapshot: RuntimeCommunicationSnapshot | null) {
  if (!snapshot) return
  form.desired_mode = snapshot.desired_mode
  Object.assign(form, snapshot.settings)
}
function buildUpdate(): RuntimeConfigUpdate {
  return {
    desired_mode: form.desired_mode as 'polling' | 'realtime',
    expected_revision: communication.value?.revision ?? 1,
    notification_interval_seconds: Number(form.notification_interval_seconds),
    message_interval_seconds: Number(form.message_interval_seconds),
    unread_interval_seconds: Number(form.unread_interval_seconds),
    pause_when_hidden: Boolean(form.pause_when_hidden),
    worker_stale_seconds: Number(form.worker_stale_seconds),
    scheduler_stale_seconds: Number(form.scheduler_stale_seconds),
    gateway_stale_seconds: Number(form.gateway_stale_seconds),
  }
}
const applyImpact = computed(() => {
  const current = communication.value
  if (!current) return '即将修改通信运行时配置。'
  const changes: string[] = []
  if (current.desired_mode !== form.desired_mode) changes.push(`通信方式：${modeLabel(current.desired_mode)} → ${modeLabel(form.desired_mode)}`)
  const fieldLabels: Record<string, string> = Object.fromEntries([...primaryNumericFields, ...advancedNumericFields].map(field => [field.key, field.label]))
  fieldLabels.pause_when_hidden = '页面隐藏时暂停轮询'
  for (const [key, value] of Object.entries(current.settings)) {
    const next = form[key as keyof typeof form]
    if (String(value) === String(next)) continue
    const display = (item: unknown) => typeof item === 'boolean' ? (item ? '是' : '否') : String(item)
    changes.push(`${fieldLabels[key] ?? key}：${display(value)} → ${display(next)}`)
  }
  return `${changes.join('；')}。开启实时需通过依赖检查，运行中故障会暂停主动提示，不会自动切换轮询。本次操作写入审计日志。`
})
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
    await client.operations.applyRuntimeConfig(buildUpdate())
    applyDialog.value = false
    await load()
    configConflict.value = ''
    notifier.success('通信运行时配置已应用并写入审计')
  }
  catch (cause) {
    if (isApiError(cause)) {
      applyError.value = cause.message
      if (cause.status === 409) {
        applyDialog.value = false
        try {
          runtime.value = await client.operations.runtime()
          configConflict.value = '配置已被其他操作更新。你的修改仍保留，请核对最新配置并重新审阅。'
        }
        catch { configConflict.value = '配置已被其他操作更新，暂时无法读取最新版本。你的修改仍保留，请稍后刷新。' }
      }
    }
    else applyError.value = '应用配置失败'
  }
  finally { applying.value = false }
}
async function reconcile() {
  reconciling.value = true
  try { await client.operations.reconcileRuntime(); await load() }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '对账失败' }
  finally { reconciling.value = false }
}
async function load() {
  loading.value = true
  error.value = null
  try { runtime.value = await client.operations.runtime(); syncFormFromSnapshot(communication.value) }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法读取运行环境' }
  finally { loading.value = false }
}
onMounted(load)
</script>
