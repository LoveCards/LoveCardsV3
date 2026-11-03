<template>
  <AdminDangerActionDialog
    v-model="thisDialogState"
    :action-label="actionLabel"
    :error="actionError"
    :impact="impact"
    :loading="deleting"
    :resource-label="resourceLabel"
    :title="title"
    @confirm="submitDelete"
  />
</template>

<script setup lang="ts">
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'

// 规范 D-1：删除确认不再要求输入文字；D-2：可恢复性不同的删除由调用方传入区分明确的 title/impact。
const props = withDefaults(defineProps<{
  deleteFun?: (id: number) => unknown
  impact?: string
  title?: string
  actionLabel?: string
}>(), {
  impact: '删除后，该资源将无法继续在管理列表和前台使用；能否恢复取决于该资源的删除策略。',
  title: '确认删除',
  actionLabel: '删除资源',
})

const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const deleteData = defineModel<{ id: number; display?: string }>('deleteData', {
  default: { id: 0 },
})

const deleting = ref(false)
const actionError = ref('')
const resourceLabel = computed(() => {
  const display = deleteData.value.display?.trim()
  return display ? `${display}（#${deleteData.value.id}）` : `资源 #${deleteData.value.id}`
})

watch(thisDialogState, (open) => {
  if (open) actionError.value = ''
})

const submitDelete = async () => {
  if (!props.deleteFun || deleting.value) return
  deleting.value = true
  actionError.value = ''
  try {
    await props.deleteFun(deleteData.value.id)
    thisDialogState.value = false
  } catch (cause) {
    actionError.value = cause instanceof Error ? cause.message : '删除失败，请稍后重试。'
  } finally {
    deleting.value = false
  }
}
</script>
