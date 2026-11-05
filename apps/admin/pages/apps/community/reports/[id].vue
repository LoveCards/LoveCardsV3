<template>
  <AdminPageContainer>
    <AdminPageHeader :title="`举报 #${route.params.id}`" description="仅展示处理举报所需的上下文，不开放任意浏览私密内容。" :breadcrumbs="breadcrumbs">
      <template #actions>
        <AdminActionGuard v-if="report && canTransition" capability="reports.manage">
          <v-btn color="primary" prepend-icon="mdi-gavel" @click="openTransitionDialog">更新处理状态</v-btn>
        </AdminActionGuard>
        <AdminActionGuard v-if="report" capability="reports.manage">
          <v-btn prepend-icon="mdi-note-text-outline" variant="outlined" @click="openNoteDialog">追加备注</v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!report" @retry="load">
      <template v-if="report">
        <v-sheet border rounded="lg" class="pa-5">
          <div class="d-flex flex-wrap justify-space-between ga-3 mb-5">
            <div><div class="text-caption text-medium-emphasis">当前状态</div><AdminStatusChip :status="report.status" class="mt-1" /></div>
            <AdminResourceLink :resource-type="report.target_type" :resource-id="report.target_id" />
          </div>
          <v-row>
            <v-col cols="12" md="4">
              <strong>举报人</strong>
              <AdminResourceLink :resource-type="'user'" :resource-id="report.reporter_id" class="mt-1" />
            </v-col>
            <v-col cols="12" md="4"><strong>原因</strong><div>{{ report.reason }}</div></v-col>
            <v-col cols="12" md="4">
              <strong>负责人</strong>
              <div v-if="!report.assignee_id">未指派</div>
              <AdminResourceLink v-if="report.assignee_id" :resource-type="'user'" :resource-id="report.assignee_id" class="mt-1" />
            </v-col>
            <v-col cols="12"><strong>补充说明</strong><p class="mb-0 mt-1 text-pre-wrap">{{ report.details || '无' }}</p></v-col>
            <v-col v-if="report.resolution" cols="12"><strong>处理结论</strong><p class="mb-0 mt-1">{{ report.resolution }}</p></v-col>
          </v-row>
        </v-sheet>
        <v-sheet v-if="evidence" border rounded="lg" class="pa-5 mt-5">
          <div class="text-h6 mb-3">举报证据（提交时快照）</div>
          <v-alert class="mb-4" type="info" variant="tonal">证据取自举报提交时刻，正文按前 2000 字留存；哈希对应全文，原文后续被修改或删除不影响验证。</v-alert>
          <v-row>
            <v-col v-for="field in evidenceFields" :key="field.label" cols="12" :md="field.wide ? 12 : 4">
              <strong>{{ field.label }}</strong>
              <div class="text-pre-wrap">{{ field.value }}</div>
            </v-col>
          </v-row>
          <template v-if="evidenceContent">
            <div class="mt-4"><strong>当时内容</strong><p class="mb-0 mt-1 text-pre-wrap">{{ evidenceContent }}</p></div>
            <div v-if="evidenceTruncated" class="mt-1 text-caption text-medium-emphasis">内容超长，仅留存前 2000 字。</div>
          </template>
          <div v-if="evidenceSha" class="mt-3 text-caption text-medium-emphasis">内容哈希（sha256）：{{ evidenceSha }}</div>
        </v-sheet>
        <v-sheet border rounded="lg" class="pa-5 mt-5">
          <AdminAuditTrail :items="auditItems" />
        </v-sheet>
      </template>
    </AdminAsyncState>

    <v-dialog v-model="transitionDialog" aria-label="变更举报状态" max-width="560">
      <v-card>
        <v-card-title>更新举报状态</v-card-title>
        <v-card-text>
          <v-select v-model="nextStatus" :items="availableStatusItems" label="目标状态" variant="outlined" />
          <v-textarea v-model="resolution" class="mt-3" label="处理原因或结论" maxlength="1000" rows="4" variant="outlined" />
          <v-alert class="mt-3" type="info" variant="tonal">状态变化和原因将写入审计记录。</v-alert>
          <v-alert v-if="actionError" class="mt-3" type="error" variant="tonal">{{ actionError }}</v-alert>
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn variant="text" @click="transitionDialog = false">取消</v-btn><v-btn color="primary" :disabled="!nextStatus || !resolution.trim()" :loading="saving" @click="transition">确认</v-btn></v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="noteDialog" aria-label="追加备注" max-width="560">
      <v-card>
        <v-card-title>追加备注</v-card-title>
        <v-card-text>
          <v-textarea v-model="note" label="备注内容" maxlength="1000" rows="4" variant="outlined" />
          <v-alert class="mt-3" type="info" variant="tonal">备注不改变处理状态，将写入审计记录。</v-alert>
          <v-alert v-if="actionError" class="mt-3" type="error" variant="tonal">{{ actionError }}</v-alert>
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn variant="text" @click="noteDialog = false">取消</v-btn><v-btn color="primary" :disabled="!note.trim()" :loading="saving" @click="addNote">确认</v-btn></v-card-actions>
      </v-card>
    </v-dialog>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { AdminReport, AdminReportEvent, AdminReportEvidence } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminAuditTrail from '~/components/shared/admin/AdminAuditTrail.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'

const route = useRoute()
const client = useApi()
const notifier = useAdminNotifier()
const report = ref<AdminReport | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const actionError = ref('')
const transitionDialog = ref(false)
const nextStatus = ref('')
const resolution = ref('')
const noteDialog = ref(false)
const note = ref('')
const breadcrumbs = [{ title: '举报处理', to: '/apps/community/reports' }, { title: `#${route.params.id}`, disabled: true }]
const statusItems = [
  { title: '待处理', value: 'open' }, { title: '处理中', value: 'reviewing' }, { title: '已解决', value: 'resolved' },
  { title: '已驳回', value: 'rejected' }, { title: '已关闭', value: 'closed' },
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
const auditItems = computed(() => (report.value?.events ?? []).map((event: AdminReportEvent) => ({
  id: event.id, action: event.event_type, actor: event.actor_id, createdAt: event.created_at,
  detail: [event.payload.reason, event.payload.resolution, event.payload.note]
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

function openTransitionDialog() {
  nextStatus.value = availableStatusItems.value[0]?.value ?? ''
  resolution.value = ''
  actionError.value = ''
  transitionDialog.value = Boolean(nextStatus.value)
}

async function load() {
  loading.value = true; error.value = null
  try { report.value = await client.reports.getManaged(Number(route.params.id)) }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载举报详情' }
  finally { loading.value = false }
}
async function transition() {
  saving.value = true; actionError.value = ''
  try {
    const terminal = ['resolved', 'rejected', 'closed'].includes(nextStatus.value)
    report.value = await client.reports.transition(Number(route.params.id), {
      status: nextStatus.value,
      ...(terminal ? { resolution: resolution.value } : { note: resolution.value }),
    })
    transitionDialog.value = false; nextStatus.value = ''; resolution.value = ''; notifier.success('举报状态已更新')
  } catch (cause) { actionError.value = isApiError(cause) ? cause.message : '处理举报失败' }
  finally { saving.value = false }
}
function openNoteDialog() {
  note.value = ''
  actionError.value = ''
  noteDialog.value = true
}
async function addNote() {
  if (!report.value) return
  saving.value = true; actionError.value = ''
  try {
    // 同状态迁移 = 备注事件（服务端支持 from === status 的 note 路径）。
    report.value = await client.reports.transition(Number(route.params.id), { status: report.value.status, note: note.value })
    noteDialog.value = false; note.value = ''; notifier.success('备注已记录')
  } catch (cause) { actionError.value = isApiError(cause) ? cause.message : '追加备注失败' }
  finally { saving.value = false }
}
onMounted(load)
</script>
