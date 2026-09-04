<template>
  <AdminDangerActionDialog
    v-model="thisDialogState"
    action-label="删除资源"
    confirm-text="删除"
    :error="actionError"
    impact="删除后，该资源将无法继续在管理列表和前台使用；能否恢复取决于该资源的删除策略。"
    :loading="deleting"
    :resource-label="resourceLabel"
    title="确认删除"
    @confirm="submitDelete"
  />
</template>

<script setup lang="ts">
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'

const props = defineProps<{
  deleteFun?: (id: number) => unknown
}>()

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
