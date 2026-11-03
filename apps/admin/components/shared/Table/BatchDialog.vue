<template>
  <AdminDangerActionDialog
    v-model="thisDialogState"
    :action-label="actionLabel"
    :error="actionError"
    :impact="impact"
    :loading="processing"
    :resource-label="resourceLabel"
    :title="title"
    @confirm="submitBatch"
  />
</template>

<script setup lang="ts">
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'

const props = defineProps<{
  batchFun?: () => unknown
  impactDescription?: string
}>()

const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const batchData = defineModel<unknown[]>('batchData', { default: [] })
const batchOptions = defineModel<string>('batchOptions', { default: '' })

const processing = ref(false)
const actionError = ref('')
const actionName = computed(() => batchOptions.value.trim() || '处理')
const title = computed(() => `确认批量${actionName.value}`)
const actionLabel = computed(() => `确认${actionName.value}`)
const resourceLabel = computed(() => `已选择 ${batchData.value.length} 个资源`)
const impact = computed(() => {
  if (props.impactDescription) return props.impactDescription
  return `即将对 ${batchData.value.length} 个资源执行“${actionName.value}”。批量操作可能改变内容可见性、账号状态或资源可用性，请确认选择范围无误。`
})

watch(thisDialogState, (open) => {
  if (open) actionError.value = ''
})

const submitBatch = async () => {
  if (!props.batchFun || processing.value || batchData.value.length === 0) return
  processing.value = true
  actionError.value = ''
  try {
    await props.batchFun()
    thisDialogState.value = false
  } catch (cause) {
    actionError.value = cause instanceof Error ? cause.message : '批量操作失败，请稍后重试。'
  } finally {
    processing.value = false
  }
}
</script>
