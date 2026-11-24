<template>
  <AdminAsyncState :loading="loading" :error="error" :empty="!report" @retry="load">
    <template v-if="report">
        <AdminDetailSection title="举报概况">
          <v-row dense>
            <v-col cols="12" sm="6"><AdminDetailField label="当前状态"><AdminStatusChip :status="report.status" /></AdminDetailField></v-col>
            <v-col cols="12" sm="6">
              <AdminDetailField label="举报目标">
                <span v-if="report.target_type === 'message'">私信 #{{ report.target_id }}</span>
                <AdminResourceLink v-else :resource-type="report.target_type" :resource-id="report.target_id" />
              </AdminDetailField>
            </v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="举报人"><AdminResourceLink resource-type="user" :resource-id="report.reporter_id" /></AdminDetailField></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="原因" :value="report.reason" /></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="负责人"><AdminResourceLink v-if="report.assignee_id" resource-type="user" :resource-id="report.assignee_id" /><span v-else>未指派</span></AdminDetailField></v-col>
            <v-col cols="12"><AdminDetailField label="补充说明" :value="report.details || '无'" /></v-col>
            <v-col v-if="report.resolution" cols="12"><AdminDetailField label="处理结论" :value="report.resolution" /></v-col>
          </v-row>
        </AdminDetailSection>
        <AdminDetailSection v-if="evidence" title="举报证据（提交时快照）" description="正文保留前 2000 字；哈希对应提交时全文。">
          <v-row>
            <v-col v-for="field in evidenceFields" :key="field.label" cols="12" :sm="field.wide ? 12 : 6">
              <AdminDetailField :label="field.label" :value="field.value" />
            </v-col>
          </v-row>
          <template v-if="evidenceContent">
            <AdminDetailField class="mt-4" label="当时内容" :value="evidenceContent" />
            <div v-if="evidenceTruncated" class="mt-1 text-caption text-medium-emphasis">内容超长，仅留存前 2000 字。</div>
          </template>
          <div v-if="evidenceSha" class="mt-3 text-caption text-medium-emphasis">内容哈希（sha256）：{{ evidenceSha }}</div>
        </AdminDetailSection>
        <AdminDetailSection title="处理记录">
          <AdminStateTimeline :items="timelineItems" />
        </AdminDetailSection>

      <AdminDetailSection v-if="canManage" title="处理操作">
        <div v-if="mode === 'view'" class="d-flex flex-wrap ga-2">
          <v-btn v-if="canTransition" :disabled="saving" prepend-icon="mdi-gavel" variant="tonal" @click="startProcessing">更新处理状态</v-btn>
          <v-btn :disabled="saving" prepend-icon="mdi-note-text-outline" variant="outlined" @click="startNote">追加备注</v-btn>
        </div>
        <template v-else-if="mode === 'process'">
          <v-btn :disabled="saving" variant="text" class="mb-2" @click="startNote">改为追加备注</v-btn>
          <v-select :disabled="saving" v-model="nextStatus" :items="availableStatusItems" label="目标状态" variant="outlined" />
          <v-textarea :disabled="saving" v-model="resolution" class="mt-3" label="处理原因或结论" maxlength="255" rows="4" variant="outlined" />
          <v-alert class="mt-3" type="info" variant="tonal">
            状态变化和原因将写入审计记录；进入对用户有意义的终态时，由后端按通知规范处理。
            <span v-if="report.target_type === 'message'">此操作仅更新举报记录，私信仍可按原规则阅读。</span>
          </v-alert>
          <v-alert v-if="actionError" class="mt-3" type="error" variant="tonal">{{ actionError }}</v-alert>
          <div class="d-flex justify-end ga-2 mt-4"><v-btn variant="text" :disabled="saving" @click="cancelAction">取消</v-btn><v-btn color="primary" variant="flat" :disabled="saving || !nextStatus || !resolution.trim()" :loading="saving" @click="transition">确认处理</v-btn></div>
        </template>
        <template v-else>
          <v-btn v-if="canTransition" :disabled="saving" variant="text" class="mb-2" @click="startProcessing">改为更新处理状态</v-btn>
          <v-textarea :disabled="saving" v-model="note" label="备注内容" maxlength="1000" rows="4" variant="outlined" />
          <v-alert class="mt-3" type="info" variant="tonal">备注不改变处理状态，将写入审计记录，不发送用户通知。</v-alert>
          <v-alert v-if="actionError" class="mt-3" type="error" variant="tonal">{{ actionError }}</v-alert>
          <div class="d-flex justify-end ga-2 mt-4"><v-btn variant="text" :disabled="saving" @click="cancelAction">取消</v-btn><v-btn color="primary" variant="flat" :disabled="saving || !note.trim()" :loading="saving" @click="addNote">保存备注</v-btn></div>
        </template>
      </AdminDetailSection>
    </template>
  </AdminAsyncState>
  <AdminUnsavedChangesDialog :model-value="confirmDiscard" subject="举报尚未提交的处理内容" @stay="stayEditing" @discard="discardChanges" />
</template>

<script setup lang="ts">
import type { AdminReport, AdminReportEvent, AdminReportEvidence } from '@lovecards/sdk'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminDetailField from '~/components/shared/admin/AdminDetailField.vue'
import AdminStateTimeline from '~/components/shared/admin/AdminStateTimeline.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'

const props = withDefaults(defineProps<{ reportId: number; initialMode?: 'view' | 'process' | 'note' }>(), { initialMode: 'view' })
const emit = defineEmits<{ loaded: [report: AdminReport | null]; updated: [] }>()
const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const canManage = computed(() => userStore.hasGrant('reports.manage', 'global'))
const report = ref<AdminReport | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const actionError = ref('')
const mode = ref<'view' | 'process' | 'note'>('view')
const nextStatus = ref('')
const resolution = ref('')
const note = ref('')
const confirmDiscard = ref(false)
const pendingMode = ref<'process' | 'note' | null>(null)
let latestLoad = 0
let resolveDiscard: ((allow: boolean) => void) | null = null
const statusItems = [
  { title: '待处理', value: 'open' }, { title: '处理中', value: 'reviewing' },
  { title: '已解决', value: 'resolved' }, { title: '已驳回', value: 'rejected' },
  { title: '已关闭', value: 'closed' },
]
const transitions: Record<string, readonly string[]> = {
  open: ['reviewing', 'rejected', 'closed'],
  reviewing: ['open', 'resolved', 'rejected'],
  resolved: ['closed'],
  rejected: ['closed'],
  closed: [],
}
const availableStatusItems = computed(() => {
  const allowed = transitions[report.value?.status ?? ''] ?? []
  return statusItems.filter((item) => allowed.includes(item.value))
})
const canTransition = computed(() => availableStatusItems.value.length > 0)
const timelineItems = computed(() => (report.value?.events ?? []).map((event: AdminReportEvent) => ({
  id: event.id, title: event.event_type, actor: event.actor_id ? `操作者 #${event.actor_id}` : '系统', timestamp: event.created_at,
  description: [event.payload.reason, event.payload.resolution, event.payload.note]
    .find((value): value is string => typeof value === 'string' && value.trim() !== '') ?? null,
})))

// 证据快照按目标类型白名单渲染（D-8④）：正文截断 2000 字，哈希对应全文。
const publicationStatusLabels: Record<number, string> = { 0: '正常', 1: '已封禁', 2: '已隐藏', 3: '待审核' }
const userStatusLabels: Record<number, string> = { 0: '正常', 1: '已封禁', 2: '已冻结', 3: '待审核' }
const evidence = computed<AdminReportEvidence | null>(() => report.value?.evidence_snapshot ?? null)
const evidenceFields = computed<{ label: string; value: string; wide?: boolean }[]>(() => {
  const snapshot = evidence.value
  const captured = snapshot?.captured
  if (!snapshot || !captured) return []
  const status = (value: number | undefined, labels: Record<number, string>) => (value === undefined ? '-' : (labels[value] ?? String(value)))
  switch (snapshot.type) {
    case 'card': return [
      { label: '卡片编号', value: `#${captured.id ?? '-'}` },
      { label: '作者', value: `#${captured.user_id ?? '-'}` },
      { label: '当时状态', value: status(captured.status, publicationStatusLabels) },
    ]
    case 'comment': return [
      { label: '评论编号', value: `#${captured.id ?? '-'}` },
      { label: '所属卡片', value: `#${captured.pid ?? '-'}` },
      { label: '当时状态', value: status(captured.status, publicationStatusLabels) },
    ]
    case 'message': return [
      { label: '消息编号', value: `#${captured.id ?? '-'}` },
      { label: '会话编号', value: `#${captured.conversation_id ?? '-'}` },
      { label: '发送人', value: `#${captured.sender_id ?? '-'}` },
      { label: '发送时间', value: captured.created_at ?? '-' },
    ]
    case 'file': return [
      { label: '文件编号', value: `#${captured.id ?? '-'}` },
      { label: '所有者', value: `#${captured.user_id ?? '-'}` },
      { label: 'MIME 类型', value: captured.mime_type ?? '-' },
      { label: '文件哈希', value: captured.hash ?? '-', wide: true },
    ]
    case 'user': return [
      { label: '用户名', value: captured.username ?? '-' },
      { label: '用户编号', value: captured.number ?? '-' },
      { label: '当时状态', value: status(captured.status, userStatusLabels) },
    ]
    default: return []
  }
})
const evidenceContent = computed(() => {
  const captured = evidence.value?.captured
  return (captured?.content ?? captured?.body ?? '').trim()
})
const evidenceSha = computed(() => evidence.value?.captured.content_sha256 ?? evidence.value?.captured.body_sha256 ?? '')
const evidenceTruncated = computed(() => evidenceContent.value.length >= 2000)


const dirty = computed(() => mode.value === 'process'
  ? Boolean(resolution.value.trim() || (nextStatus.value && nextStatus.value !== availableStatusItems.value[0]?.value))
  : mode.value === 'note' && Boolean(note.value.trim()))

function activateMode(next: 'process' | 'note') {
  nextStatus.value = availableStatusItems.value[0]?.value ?? ''
  resolution.value = ''
  note.value = ''
  actionError.value = ''
  mode.value = next
}
function startProcessing() {
  if (!canManage.value || !canTransition.value || saving.value) return
  if (dirty.value) { pendingMode.value = 'process'; confirmDiscard.value = true; return }
  activateMode('process')
}
function startNote() {
  if (!canManage.value || saving.value) return
  if (dirty.value) { pendingMode.value = 'note'; confirmDiscard.value = true; return }
  activateMode('note')
}
async function requestClose(): Promise<boolean> {
  if (saving.value) return false
  if (!dirty.value) return true
  confirmDiscard.value = true
  return new Promise<boolean>((resolve) => { resolveDiscard = resolve })
}
function stayEditing() {
  confirmDiscard.value = false
  pendingMode.value = null
  resolveDiscard?.(false)
  resolveDiscard = null
}
function discardChanges() {
  confirmDiscard.value = false
  const next = pendingMode.value
  pendingMode.value = null
  if (next) activateMode(next)
  else {
    mode.value = 'view'
    nextStatus.value = ''
    resolution.value = ''
    note.value = ''
  }
  resolveDiscard?.(!next)
  resolveDiscard = null
}
async function cancelAction() {
  if (await requestClose()) mode.value = 'view'
}
async function load() {
  const requestId = ++latestLoad
  loading.value = true
  error.value = null
  report.value = null
  emit('loaded', null)
  try {
    const next = await client.reports.getManaged(props.reportId)
    if (requestId !== latestLoad) return
    report.value = next
    emit('loaded', next)
    mode.value = 'view'
    nextStatus.value = ''
    resolution.value = ''
    note.value = ''
    if (props.initialMode === 'process') startProcessing()
    else if (props.initialMode === 'note') startNote()
  } catch (cause) {
    if (requestId === latestLoad) error.value = isApiError(cause) ? cause.message : '无法加载举报详情'
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}
async function refreshAfterAction() {
  try {
    const next = await client.reports.getManaged(props.reportId)
    report.value = next
    emit('loaded', next)
  } catch {
    error.value = '操作已提交，但无法读取最新举报状态；请刷新后核对处理结果。'
  }
  emit('updated')
}
async function transition() {
  if (saving.value || !report.value || !canManage.value || !availableStatusItems.value.some((item) => item.value === nextStatus.value) || !resolution.value.trim()) return
  saving.value = true
  actionError.value = ''
  try {
    const terminal = ['resolved', 'rejected', 'closed'].includes(nextStatus.value)
    await client.reports.transition(props.reportId, {
      status: nextStatus.value,
      ...(terminal ? { resolution: resolution.value } : { note: resolution.value }),
    })
    mode.value = 'view'
    nextStatus.value = ''
    resolution.value = ''
    await refreshAfterAction()
    if (!error.value) notifier.success('举报状态已更新')
  } catch (cause) {
    actionError.value = isApiError(cause) ? cause.message : '处理举报失败'
  } finally {
    saving.value = false
  }
}
async function addNote() {
  if (saving.value || !report.value || !canManage.value || !note.value.trim()) return
  saving.value = true
  actionError.value = ''
  try {
    await client.reports.transition(props.reportId, { status: report.value.status, note: note.value })
    mode.value = 'view'
    note.value = ''
    await refreshAfterAction()
    if (!error.value) notifier.success('备注已记录')
  } catch (cause) {
    actionError.value = isApiError(cause) ? cause.message : '追加备注失败'
  } finally {
    saving.value = false
  }
}
const beforeUnload = (event: BeforeUnloadEvent) => {
  if (!dirty.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => {
  latestLoad += 1
  window.removeEventListener('beforeunload', beforeUnload)
  resolveDiscard?.(false)
})
onBeforeRouteLeave(requestClose)
onBeforeRouteUpdate((to, from) => JSON.stringify(to.params) !== JSON.stringify(from.params) ? requestClose() : true)
watch(() => props.reportId, () => { void load() }, { immediate: true })
defineExpose({ requestClose })
</script>
