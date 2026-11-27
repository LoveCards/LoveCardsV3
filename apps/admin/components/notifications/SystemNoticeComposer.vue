<template>
  <v-card data-testid="notice-compose">
    <v-card-text class="pa-4 pa-sm-6">
      <AdminAsyncState :loading="initializing" :error="initialError" @retry="refresh">
        <v-alert v-if="uncertain && !verificationEmpty" type="warning" variant="tonal" class="mb-5" role="alert">
          {{ error || '请求结果尚未确认，请先重新核对。' }}
          <p class="mt-1 mb-0">核对只读取服务器结果，不会再次发送。</p>
          <template #append><v-btn ref="verificationButton" variant="outlined" :loading="reading" @click="verifyOutcome">重新核对</v-btn></template>
        </v-alert>
        <v-alert v-else-if="error && !verificationEmpty" type="error" variant="tonal" class="mb-5" role="alert">{{ error }}</v-alert>
        <v-alert v-if="readError" type="error" variant="tonal" class="mb-5" role="alert">{{ readError }} 以下保留上次读取的结果。</v-alert>
        <v-alert v-if="verificationEmpty" type="info" variant="tonal" class="mb-5" role="status">
          尚未查到本次预览，通知未进入发送步骤。
          <div class="d-flex flex-wrap ga-2 mt-3">
            <v-btn ref="verificationButton" variant="outlined" :loading="reading" @click="verifyOutcome">重新核对</v-btn>
            <v-btn v-if="hasRecoverableDraft" variant="outlined" :loading="mutating" @click="prepare">重试预览</v-btn>
            <v-btn variant="text" :disabled="busy" @click="abandonPreview">放弃未发送的预览</v-btn>
          </div>
        </v-alert>

        <v-form v-if="!batch" @submit.prevent="prepare">
          <h2 ref="formHeading" tabindex="-1" class="text-h6 mb-1">发送系统通知</h2>
          <p class="text-body-2 text-medium-emphasis mb-6">先核对收件范围和正文，再确认发送。用户端显示为系统通知。</p>
          <v-row>
            <v-col cols="12" sm="7">
              <v-select v-model="audience" :items="audienceOptions" label="收件范围" :disabled="locked" hide-details />
            </v-col>
            <v-col cols="12" sm="5">
              <v-select v-model="severity" :items="severityOptions" label="提示级别" :disabled="locked" hide-details />
            </v-col>
            <v-col v-if="audience === 'selected'" cols="12">
              <v-autocomplete
                v-model="selectedRecipients"
                v-model:search="search"
                :items="candidateItems"
                :item-title="recipientLabel"
                item-value="id"
                return-object
                multiple
                chips
                closable-chips
                no-filter
                label="搜索并选择用户"
                placeholder="输入用户名或用户 ID"
                :loading="candidatesLoading"
                :disabled="locked"
                :error-messages="candidateError"
                hint="最多选择 200 位用户。"
                persistent-hint
                no-data-text="输入用户名或用户 ID 搜索"
                class="notice-compose__recipients"
              />
            </v-col>
            <v-col v-else cols="12">
              <p class="text-body-2 text-medium-emphasis mb-0">包含生成预览时未注销的正式账号，排除访客和已删除账号。确认后注册的新用户不在本批次内。</p>
            </v-col>
            <v-col cols="12">
              <v-textarea v-model="message" label="正文" rows="5" max-rows="12" auto-grow counter :disabled="locked" hint="使用纯文本；账号安全和审核结果继续由对应业务通知。" persistent-hint />
            </v-col>
            <v-col cols="12">
              <v-text-field v-model="actionUrl" label="站内跳转（可选）" placeholder="例如 /settings" maxlength="2048" :disabled="locked" hint="只支持站内路径，留空时通知不提供跳转。" persistent-hint />
            </v-col>
          </v-row>
          <p v-if="formError" class="text-body-2 text-error mt-4 mb-0" role="alert">{{ formError }}</p>
          <AdminConfigSaveBar :dirty="dirty" label="通知草稿" class="mt-6">
            <v-btn type="submit" color="primary" variant="flat" :loading="mutating" :disabled="locked">预览收件范围</v-btn>
          </AdminConfigSaveBar>
        </v-form>

        <template v-else>
          <section :data-testid="batch.status === 'prepared' ? 'notice-preview' : 'notice-receipt'" aria-labelledby="notice-batch-title">
            <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-5">
              <div>
                <h2 id="notice-batch-title" ref="batchHeading" tabindex="-1" class="text-h6">{{ batch.status === 'prepared' ? '核对发送预览' : '发送回执' }}</h2>
                <p class="text-caption text-medium-emphasis mt-1 mb-0">批次 #{{ batch.id }} · {{ formatTime(batch.created_at) }}</p>
              </div>
              <v-chip :color="batchColor" variant="tonal" size="small" role="status">{{ statusLabel }}</v-chip>
            </div>
            <dl class="notice-compose__summary mb-5">
              <div><dt>收件范围</dt><dd>{{ batch.audience === 'all' ? '全站用户' : '指定用户' }}</dd></div>
              <div><dt>收件人数</dt><dd>{{ formatNumber(batch.recipient_count) }} 人</dd></div>
              <div><dt>提示级别</dt><dd>{{ batch.severity === 'warning' ? '提醒通知' : '一般通知' }}</dd></div>
              <div><dt>{{ batch.confirmed_at ? '确认时间' : '名单冻结时间' }}</dt><dd>{{ formatTime(batch.confirmed_at || batch.created_at) }}</dd></div>
            </dl>
            <div v-if="batch.audience === 'selected' && batch.recipients.length" class="d-flex flex-wrap ga-2 mb-5" aria-label="本批次收件用户">
              <v-chip v-for="recipient in batch.recipients" :key="recipient.id" class="notice-compose__recipient-chip" size="small" variant="outlined">{{ recipientLabel(recipient) }}</v-chip>
            </div>
            <h3 class="text-subtitle-2 mb-2">通知正文</h3>
            <p class="notice-compose__message text-body-1 mb-4">{{ batch.message }}</p>
            <p v-if="batch.action_url" class="text-body-2 text-medium-emphasis notice-compose__path mb-4">站内跳转：{{ batch.action_url }}</p>

            <p v-if="batch.status === 'prepared'" class="text-body-2 text-medium-emphasis mb-0">当前内容和收件名单已固定，尚未发送。修改内容或范围需要重新生成预览。</p>
            <template v-else>
              <v-divider class="my-5" />
              <dl class="notice-compose__counts" aria-label="本批次处理数量">
                <div><dt>已生成</dt><dd>{{ formatNumber(batch.generated_count) }}</dd></div>
                <div><dt>已跳过</dt><dd>{{ formatNumber(batch.skipped_count) }}</dd></div>
                <div><dt>待生成</dt><dd>{{ formatNumber(batch.pending_count) }}</dd></div>
                <div><dt>失败</dt><dd>{{ formatNumber(batch.failed_count) }}</dd></div>
              </dl>
              <v-progress-linear class="mt-5" :model-value="progress" :color="batchColor || 'primary'" height="6" rounded aria-label="本批次处理进度" />
              <p class="text-caption text-medium-emphasis mt-3 mb-0">已生成表示通知已保存，用户已读状态单独统计。{{ batch.pending_count && (batch.status === 'queued' || batch.status === 'processing') ? '后台仍在处理，可以稍后刷新结果。' : '' }}</p>
              <v-alert v-if="batch.status === 'failed' || batch.status === 'partial_failure'" type="warning" variant="tonal" class="mt-4" role="status">本批次有处理任务失败。{{ eventPath ? '请先核对处理诊断，再重试可恢复的任务。' : '可联系有事件诊断权限的管理员核对失败原因。' }}</v-alert>
              <v-text-field v-if="batch.can_retry" v-model="retryReason" class="mt-5" label="重试原因" maxlength="255" counter :disabled="busy || Boolean(uncertain)" hint="将与本次恢复操作一起记录在审计日志中。" persistent-hint />
              <p v-if="readAt" class="text-caption text-medium-emphasis mt-1 mb-0">{{ readError ? '上次读取' : '读取于' }} {{ readAt }}</p>
            </template>
          </section>
          <AdminConfigSaveBar :dirty="false" label="系统通知" class="mt-6">
            <template v-if="batch.status === 'prepared'">
              <v-btn variant="text" :disabled="busy || Boolean(uncertain)" @click="editPreview">返回修改</v-btn>
              <v-btn color="primary" variant="flat" :disabled="!canConfirm || busy || Boolean(uncertain)" @click="openConfirmation('confirm')">确认发送</v-btn>
            </template>
            <template v-else>
              <v-btn variant="text" :disabled="busy || Boolean(uncertain)" @click="newNotice">新建通知</v-btn>
              <v-btn variant="outlined" :loading="reading" :disabled="mutating" @click="refresh">刷新结果</v-btn>
              <v-btn v-if="batch.can_retry" variant="outlined" :disabled="busy || Boolean(uncertain) || !retryReason.trim()" @click="openConfirmation('retry')">重试失败任务</v-btn>
            </template>
          </AdminConfigSaveBar>
          <div v-if="batch.status !== 'prepared' && (auditPath || eventPath)" class="d-flex flex-wrap ga-2 mt-4">
            <v-btn v-if="auditPath" :to="auditPath" variant="text" append-icon="mdi-arrow-right">发布审计</v-btn>
            <v-btn v-if="eventPath" :to="eventPath" variant="text" append-icon="mdi-arrow-right">处理诊断</v-btn>
          </div>
          <p v-if="batch.status === 'prepared' && batch.audience === 'all' && !canBroadcast" class="text-body-2 text-warning mt-4 mb-0" role="status">当前账号没有全站发送权限，不能确认此批次。</p>
        </template>
      </AdminAsyncState>
      <v-btn v-if="initialError && !initializing" class="mt-4" variant="outlined" @click="newNotice">新建通知</v-btn>
    </v-card-text>
  </v-card>
  <AdminDangerActionDialog v-model="confirmationOpen" :title="confirmationMode === 'retry' ? '重试失败任务' : '确认发送系统通知'" :action-label="confirmationMode === 'retry' ? '确认重试' : '确认发送'" :resource-label="batch ? `批次 #${batch.id} · ${formatNumber(batch.recipient_count)} 位用户` : ''" :impact="confirmationMode === 'retry' ? '继续处理本批次可重试的任务，已生成的通知不会重复创建。' : '通知将按当前固定的内容和收件名单分批生成，确认后不能直接撤回。'" :loading="mutating" :error="confirmationError" :color="confirmationMode === 'retry' ? 'primary' : 'warning'" @confirm="executeConfirmation" @after-leave="finishConfirmationFocus" />
  <AdminUnsavedChangesDialog :model-value="unsavedChangesDialog" subject="尚未生成预览的通知草稿" @stay="continueEditing" @discard="discardChanges" />
</template>

<script setup lang="ts">
import type { SystemNoticeBatch, SystemNoticeRecipient } from '@lovecards/sdk'
import { onBeforeRouteUpdate } from 'vue-router'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useAdminUnsavedChanges } from '~/composables/useAdminUnsavedChanges'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const client = useApi()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const canBroadcast = computed(() => userStore.hasGrant('notifications.broadcast', 'global'))
const batch = ref<SystemNoticeBatch | null>(null)
const audience = ref<'selected' | 'all'>('selected')
const selectedRecipients = ref<SystemNoticeRecipient[]>([])
const message = ref('')
const actionUrl = ref('')
const severity = ref<'info' | 'warning'>('info')
const formError = ref('')
const error = ref('')
const initialError = ref<string | null>(null)
const readError = ref('')
const initializing = ref(true)
const reading = ref(false)
const mutating = ref(false)
const uncertain = ref<'' | 'prepare' | 'confirm' | 'retry'>('')
const verificationEmpty = ref(false)
const requestKey = ref<string | null>(null)
const hasRecoverableDraft = ref(false)
const readAt = ref('')
const search = ref('')
const candidates = ref<SystemNoticeRecipient[]>([])
const candidatesLoading = ref(false)
const candidateError = ref('')
const confirmationOpen = ref(false)
const confirmationMode = ref<'confirm' | 'retry'>('confirm')
const confirmationError = ref('')
const retryReason = ref('')
const formHeading = ref<HTMLElement | null>(null)
const batchHeading = ref<HTMLElement | null>(null)
const verificationButton = ref<{ $el: HTMLElement } | null>(null)
let confirmationTarget: { id: number; generation: number } | null = null
let focusAfterConfirmation = false
const busy = computed(() => initializing.value || reading.value || mutating.value)
const locked = computed(() => busy.value || Boolean(uncertain.value))
const dirty = computed(() => !batch.value && Boolean(message.value.trim() || actionUrl.value.trim() || selectedRecipients.value.length || audience.value === 'all'))
const refreshDisabled = computed(() => dirty.value && !uncertain.value)
const { unsavedChangesDialog, continueEditing, discardChanges, confirmUnsavedChanges } = useAdminUnsavedChanges(dirty)
const canConfirm = computed(() => Boolean(batch.value?.can_confirm) && (batch.value?.audience !== 'all' || canBroadcast.value))
const audienceOptions = computed(() => [{ title: '指定用户', value: 'selected' }, ...(canBroadcast.value ? [{ title: '全站用户', value: 'all' }] : [])])
const severityOptions = [{ title: '一般通知', value: 'info' }, { title: '提醒通知', value: 'warning' }]
const candidateItems = computed(() => Array.from(new Map([...selectedRecipients.value, ...candidates.value].map(item => [item.id, item])).values()))
const recipientLabel = (recipient: SystemNoticeRecipient) => `${recipient.username}#${recipient.id}`
const formatNumber = (value: number) => value.toLocaleString('zh-CN')
const formatTime = (value: string) => value.replace('T', ' ').replace(/\.\d+(?:Z)?$/, '').replace(/Z$/, '')
const statusNames: Record<SystemNoticeBatch['status'], string> = { prepared: '待确认', queued: '已受理', processing: '处理中', completed: '已完成', partial_failure: '部分失败', failed: '处理失败' }
const statusLabel = computed(() => batch.value ? statusNames[batch.value.status] : '')
const batchColor = computed(() => batch.value?.status === 'completed' ? 'success' : batch.value?.status === 'failed' ? 'error' : batch.value?.status === 'partial_failure' ? 'warning' : undefined)
const progress = computed(() => batch.value?.recipient_count ? Math.min(100, (batch.value.generated_count + batch.value.skipped_count + batch.value.failed_count) / batch.value.recipient_count * 100) : 0)
const auditPath = computed(() => batch.value && userStore.canAccessPath('/apps/operations/audit') ? `/apps/operations/audit?resource_type=system_notice_batch&resource_id=${batch.value.id}` : '')
const eventPath = computed(() => batch.value?.diagnostic_event_id && userStore.hasGrant('communications.read', 'global') && userStore.canAccessPath('/apps/operations/eventing') ? `/apps/operations/eventing?event_id=${batch.value.diagnostic_event_id}` : '')
const storageKey = computed(() => `lovecards.admin.system-notice.prepare.${userStore.userInfo?.id ?? 'unknown'}`)
const errorText = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback
const outcomeUnknown = (cause: unknown) => !isApiError(cause) || cause.status === 0 || cause.status === 408 || cause.status >= 500
let readSequence = 0
let candidateSequence = 0
let candidateTimer: ReturnType<typeof setTimeout> | undefined
let mounted = true
let syncingRoute = false

function savePendingKey(key: string | null) {
  try { if (key) sessionStorage.setItem(storageKey.value, key); else sessionStorage.removeItem(storageKey.value) } catch { /* 浏览器禁止存储时仍可通过服务器最近批次恢复。 */ }
}
function savedPendingKey() { try { return sessionStorage.getItem(storageKey.value) } catch { return null } }
function newRequestKey() { return Array.from(crypto.getRandomValues(new Uint8Array(16)), value => value.toString(16).padStart(2, '0')).join('') }
function batchQueryId() { const value = Number(route.query.batch); return Number.isSafeInteger(value) && value > 0 ? value : null }
async function syncBatchQuery(id: number | null) {
  syncingRoute = true
  try { await router.replace({ query: { ...route.query, view: 'send', batch: id ? String(id) : undefined } }) }
  finally { syncingRoute = false }
}
function acceptBatch(result: SystemNoticeBatch) {
  batch.value = result
  requestKey.value = result.request_key
  uncertain.value = ''
  verificationEmpty.value = false
  hasRecoverableDraft.value = false
  error.value = ''
  readError.value = ''
  savePendingKey(null)
  readAt.value = new Date().toLocaleString('zh-CN')
}
async function readBatch(id: number) {
  const sequence = ++readSequence
  reading.value = true
  readError.value = ''
  try {
    const result = await client.notifications.getBatch(id)
    if (!mounted || sequence !== readSequence) return false
    acceptBatch(result)
    initialError.value = null
    return true
  } catch (cause) {
    if (sequence === readSequence) {
      const text = isApiError(cause) && cause.status === 404 ? '发送批次不存在或无权查看。' : errorText(cause, '无法读取发送批次，请重试。')
      if (batch.value) readError.value = text
      else initialError.value = text
    }
    return false
  } finally { if (sequence === readSequence) reading.value = false }
}
async function loadLatest(key?: string) {
  const sequence = ++readSequence
  reading.value = true
  try {
    const result = await client.notifications.latestBatch(key ? { request_key: key } : undefined)
    if (!mounted || sequence !== readSequence) return
    initialError.value = null
    if (result.batch) { acceptBatch(result.batch); await syncBatchQuery(result.batch.id) }
    else if (key) { verificationEmpty.value = true; uncertain.value = 'prepare'; error.value = '' }
  } catch (cause) {
    if (sequence === readSequence) {
      if (key) error.value = errorText(cause, '无法核对预览结果，请重试。')
      else initialError.value = errorText(cause, '无法读取最近发送批次，请重试。')
    }
  } finally { if (sequence === readSequence) reading.value = false }
}
async function refresh() {
  if (mutating.value) return
  if (route.query.batch !== undefined && !batchQueryId()) { initialError.value = '批次编号无效，请新建通知或打开有效的发送回执。'; return }
  if (uncertain.value) { await verifyOutcome(); return }
  if (refreshDisabled.value) return
  if (batch.value || batchQueryId()) await readBatch(batch.value?.id ?? batchQueryId()!)
  else await loadLatest()
  initializing.value = false
}
async function verifyOutcome() {
  if (mutating.value || reading.value) return
  if (batch.value) await readBatch(batch.value.id)
  else if (requestKey.value) await loadLatest(requestKey.value)
}
function validateDraft() {
  if (audience.value === 'all' && !canBroadcast.value) return '当前账号没有全站发送权限。'
  if (audience.value === 'selected' && (!selectedRecipients.value.length || selectedRecipients.value.length > 200)) return '请选择 1 至 200 位接收用户。'
  if (!message.value.trim()) return '请输入通知正文。'
  const url = actionUrl.value.trim()
  if (url && (!url.startsWith('/') || url.startsWith('//') || url.includes('\\') || url.includes('://'))) return '跳转地址必须是站内路径。'
  if (new TextEncoder().encode(JSON.stringify({ message: message.value.trim(), action_url: url || null, severity: severity.value })).length > 4096) return '通知内容过长，请缩短正文或跳转地址。'
  return ''
}
async function prepare() {
  if (mutating.value || reading.value || batch.value || (uncertain.value && !verificationEmpty.value)) return
  formError.value = validateDraft()
  if (formError.value) return
  requestKey.value ??= newRequestKey()
  const key = requestKey.value
  savePendingKey(key)
  hasRecoverableDraft.value = true
  mutating.value = true
  error.value = ''
  verificationEmpty.value = false
  try {
    const result = await client.notifications.prepareBatch({ request_key: key, audience: audience.value, ...(audience.value === 'selected' ? { recipient_ids: selectedRecipients.value.map(item => item.id) } : {}), message: message.value.trim(), ...(actionUrl.value.trim() ? { action_url: actionUrl.value.trim() } : {}), severity: severity.value })
    if (!mounted) return
    acceptBatch(result)
    await syncBatchQuery(result.id)
    await nextTick()
    batchHeading.value?.focus()
  } catch (cause) {
    if (!mounted) return
    error.value = errorText(cause, '无法生成预览，请重试。')
    if (outcomeUnknown(cause) || (isApiError(cause) && cause.status === 409)) {
      uncertain.value = 'prepare'
      await nextTick()
      verificationButton.value?.$el.focus()
    }
    else { savePendingKey(null); requestKey.value = null }
  } finally { if (mounted) mutating.value = false }
}
function openConfirmation(mode: 'confirm' | 'retry') {
  if (!batch.value || busy.value || uncertain.value) return
  confirmationTarget = { id: batch.value.id, generation: batch.value.generation }
  confirmationMode.value = mode
  confirmationError.value = ''
  confirmationOpen.value = true
}
async function executeConfirmation() {
  if (!batch.value || mutating.value || uncertain.value) return
  const mode = confirmationMode.value
  if (!confirmationTarget || confirmationTarget.id !== batch.value.id || confirmationTarget.generation !== batch.value.generation) { confirmationError.value = '当前批次已更新，请关闭后重新核对。'; return }
  if (mode === 'confirm' && !canConfirm.value) return
  if (mode === 'retry' && (!batch.value.can_retry || !retryReason.value.trim())) return
  const id = batch.value.id
  mutating.value = true
  confirmationError.value = ''
  error.value = ''
  try {
    const result = mode === 'confirm' ? await client.notifications.confirmBatch(id) : await client.notifications.retryBatch(id, { expected_generation: confirmationTarget.generation, reason: retryReason.value.trim() })
    if (!mounted) return
    acceptBatch(result)
    retryReason.value = ''
    focusAfterConfirmation = true
    confirmationOpen.value = false
  } catch (cause) {
    if (!mounted) return
    const text = errorText(cause, '无法确认处理结果，请先核对。')
    if (outcomeUnknown(cause)) { uncertain.value = mode; error.value = text; focusAfterConfirmation = true; confirmationOpen.value = false }
    else confirmationError.value = text
  } finally { if (mounted) mutating.value = false }
}
async function finishConfirmationFocus() {
  if (!focusAfterConfirmation) return
  focusAfterConfirmation = false
  await nextTick()
  if (uncertain.value) verificationButton.value?.$el.focus()
  else batchHeading.value?.focus()
}
async function editPreview() {
  if (!batch.value || busy.value || uncertain.value) return
  audience.value = batch.value.audience
  selectedRecipients.value = [...batch.value.recipients]
  message.value = batch.value.message
  actionUrl.value = batch.value.action_url || ''
  severity.value = batch.value.severity
  batch.value = null
  requestKey.value = null
  formError.value = ''
  savePendingKey(null)
  await syncBatchQuery(null)
  await nextTick()
  formHeading.value?.focus()
}
async function newNotice() {
  if (busy.value || uncertain.value) return
  batch.value = null
  audience.value = 'selected'
  selectedRecipients.value = []
  message.value = ''
  actionUrl.value = ''
  severity.value = 'info'
  requestKey.value = null
  error.value = ''; readError.value = ''; formError.value = ''
  initialError.value = null
  retryReason.value = ''
  savePendingKey(null)
  await syncBatchQuery(null)
  await nextTick()
  formHeading.value?.focus()
}
function abandonPreview() {
  if (!verificationEmpty.value || busy.value || batch.value) return
  uncertain.value = ''
  verificationEmpty.value = false
  error.value = ''
  requestKey.value = null
  savePendingKey(null)
}
watch(search, value => {
  if (candidateTimer) clearTimeout(candidateTimer)
  const sequence = ++candidateSequence
  const query = (value || '').trim()
  candidateError.value = ''
  if (!query || locked.value) { candidates.value = []; candidatesLoading.value = false; return }
  candidateTimer = setTimeout(async () => {
    candidatesLoading.value = true
    try { const result = await client.notifications.recipientCandidates({ query, limit: 20 }); if (mounted && sequence === candidateSequence) candidates.value = result }
    catch (cause) { if (sequence === candidateSequence) { candidates.value = []; candidateError.value = errorText(cause, '无法搜索用户，请重试。') } }
    finally { if (sequence === candidateSequence) candidatesLoading.value = false }
  }, 250)
})
watch(() => route.query.batch, value => {
  const id = Number(value)
  if (value !== undefined && (!Number.isSafeInteger(id) || id <= 0)) { batch.value = null; initialError.value = '批次编号无效，请新建通知或打开有效的发送回执。'; return }
  if (Number.isSafeInteger(id) && id > 0 && batch.value?.id !== id && !mutating.value) { batch.value = null; void readBatch(id) }
})
onBeforeRouteUpdate((to, from) => !syncingRoute && String(to.query.batch ?? '') !== String(from.query.batch ?? '') ? confirmUnsavedChanges() : true)
watch(canBroadcast, value => { if (!value && !batch.value) audience.value = 'selected' })
onMounted(async () => {
  const id = batchQueryId()
  if (route.query.batch !== undefined && !id) { initialError.value = '批次编号无效，请新建通知或打开有效的发送回执。'; initializing.value = false; return }
  if (id) await readBatch(id)
  else {
    requestKey.value = savedPendingKey()
    if (requestKey.value) uncertain.value = 'prepare'
    await loadLatest(requestKey.value || undefined)
  }
  initializing.value = false
})
onBeforeUnmount(() => { mounted = false; readSequence++; candidateSequence++; if (candidateTimer) clearTimeout(candidateTimer) })
defineExpose({ refresh, loading: busy, refreshDisabled })
</script>

<style scoped>
.notice-compose__summary, .notice-compose__counts {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}
.notice-compose__summary dt, .notice-compose__counts dt { color: rgb(var(--v-theme-on-surface-variant)); font-size: 0.875rem; }
.notice-compose__summary dd, .notice-compose__counts dd { margin: 6px 0 0; overflow-wrap: anywhere; }
.notice-compose__counts dd { font-size: 1.25rem; font-weight: 500; font-variant-numeric: tabular-nums; }
.notice-compose__message { white-space: pre-wrap; overflow-wrap: anywhere; }
.notice-compose__path { overflow-wrap: anywhere; }
.notice-compose__recipients :deep(.v-chip) { max-width: 100%; }
.notice-compose__recipients :deep(.v-chip__content) { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.notice-compose__recipient-chip { max-width: 100%; }
.notice-compose__recipient-chip :deep(.v-chip__content) { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
@media (max-width: 699.98px) {
  .notice-compose__summary, .notice-compose__counts { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 16px; }
}
</style>
