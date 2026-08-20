<template>
  <div>
    <AdminPageHeader :title="`举报 #${route.params.id}`" description="仅展示处理举报所需的上下文，不开放任意浏览私密内容。" :breadcrumbs="breadcrumbs">
      <template #actions>
        <AdminActionGuard capability="reports.manage">
          <v-btn color="primary" prepend-icon="mdi-gavel" @click="transitionDialog = true">更新处理状态</v-btn>
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
            <v-col cols="12" md="4"><strong>举报人</strong><div>#{{ report.reporter_id }}</div></v-col>
            <v-col cols="12" md="4"><strong>原因</strong><div>{{ report.reason }}</div></v-col>
            <v-col cols="12" md="4"><strong>负责人</strong><div>{{ report.assignee_id ? `#${report.assignee_id}` : '未指派' }}</div></v-col>
            <v-col cols="12"><strong>补充说明</strong><p class="mb-0 mt-1 text-pre-wrap">{{ report.details || '无' }}</p></v-col>
            <v-col v-if="report.resolution" cols="12"><strong>处理结论</strong><p class="mb-0 mt-1">{{ report.resolution }}</p></v-col>
          </v-row>
        </v-sheet>
        <v-sheet border rounded="lg" class="pa-5 mt-5">
          <AdminAuditTrail :items="auditItems" />
        </v-sheet>
      </template>
    </AdminAsyncState>

    <v-dialog v-model="transitionDialog" max-width="560">
      <v-card>
        <v-card-title>更新举报状态</v-card-title>
        <v-card-text>
          <v-select v-model="nextStatus" :items="statusItems" label="目标状态" variant="outlined" />
          <v-textarea v-model="resolution" class="mt-3" label="处理原因或结论" maxlength="1000" rows="4" variant="outlined" />
          <v-alert class="mt-3" type="info" variant="tonal">状态变化和原因将写入审计记录。</v-alert>
          <v-alert v-if="actionError" class="mt-3" type="error" variant="tonal">{{ actionError }}</v-alert>
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn variant="text" @click="transitionDialog = false">取消</v-btn><v-btn color="primary" :disabled="!nextStatus || !resolution.trim()" :loading="saving" @click="transition">确认</v-btn></v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import type { AdminReport, AdminReportEvent } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminAuditTrail from '~/components/shared/admin/AdminAuditTrail.vue'
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
const nextStatus = ref('reviewing')
const resolution = ref('')
const breadcrumbs = [{ title: '举报处理', to: '/apps/governance/reports' }, { title: `#${route.params.id}`, disabled: true }]
const statusItems = [
  { title: '待处理', value: 'open' }, { title: '处理中', value: 'reviewing' }, { title: '已解决', value: 'resolved' },
  { title: '已驳回', value: 'rejected' }, { title: '已关闭', value: 'closed' },
]
const auditItems = computed(() => (report.value?.events ?? []).map((event: AdminReportEvent) => ({
  id: event.id, action: event.event_type, actor: event.actor_id, createdAt: event.created_at,
  detail: typeof event.payload.note === 'string' ? event.payload.note : null,
})))

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
    transitionDialog.value = false; resolution.value = ''; notifier.success('举报状态已更新')
  } catch (cause) { actionError.value = isApiError(cause) ? cause.message : '处理举报失败' }
  finally { saving.value = false }
}
onMounted(load)
</script>
