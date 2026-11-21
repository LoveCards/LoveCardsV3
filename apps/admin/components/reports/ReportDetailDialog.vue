<template>
  <v-dialog @after-leave="restoreDialogFocus" :model-value="open" aria-label="举报详情" :fullscreen="xs" max-width="1040" @update:model-value="onDialogChange">
    <v-card>
      <v-card-title class="d-flex align-center ga-3">
        <v-icon icon="mdi-flag-outline" size="small" />
        <span>举报 #{{ reportId }}</span>
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" aria-label="关闭" @click="close" />
      </v-card-title>
      <v-divider />
      <v-card-text class="admin-report-detail-body pa-0">
        <ReportDetailContent
          v-if="open && reportId"
          :key="reportId + '-' + initialMode"
          ref="content"
          :report-id="reportId"
          :initial-mode="initialMode"
          @loaded="report = $event"
          @updated="$emit('updated')"
        />
      </v-card-text>
      <v-divider v-if="report" />
      <v-card-actions v-if="report" class="pa-4">

        <v-btn v-if="report" variant="text" @click="openFullPage">打开完整页面</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
import type { AdminReport } from '@lovecards/sdk'
import { useDisplay } from 'vuetify'
import ReportDetailContent from '~/components/reports/ReportDetailContent.vue'

withDefaults(defineProps<{ reportId: number | null; initialMode?: 'view' | 'process' | 'note' }>(), { initialMode: 'view' })
defineEmits<{ updated: [] }>()
const open = defineModel<boolean>({ default: false })
const { xs } = useDisplay()
const route = useRoute()
const router = useRouter()
const report = ref<AdminReport | null>(null)
const content = ref<InstanceType<typeof ReportDetailContent> | null>(null)
async function close() {
  if (await content.value?.requestClose() ?? true) open.value = false
}
async function openFullPage() {
  if (!report.value || !(await content.value?.requestClose() ?? true)) return
  const id = report.value.id
  open.value = false
  await router.push({ path: '/apps/community/reports/' + id, state: { listSource: route.fullPath } })
}
function onDialogChange(value: boolean) {
  if (value) open.value = true
  else void close()
}
watch(open, (value) => { if (!value) report.value = null })
const restoreDialogFocus = useAdminDialogFocusReturn(open)
</script>

<style scoped>
.admin-report-detail-body { max-height: min(75vh, 720px); overflow-y: auto; }
@media (max-width: 599.98px) {
  .admin-report-detail-body { max-height: none; flex: 1 1 auto; }
}
</style>
