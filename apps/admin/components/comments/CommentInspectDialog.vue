<template>
  <v-dialog @after-leave="restoreDialogFocus" :model-value="modelValue" :fullscreen="xs" :persistent="busy" aria-label="评论详情" max-width="880" scrollable @update:model-value="requestClose">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-2"><span>评论 #{{ commentId }}</span><v-btn aria-label="关闭" icon="mdi-close" variant="text" @click="requestClose(false)" /></v-card-title>
      <v-divider />
      <v-card-text class="pa-0"><CommentDetailContent v-if="modelValue && commentId" :key="`${commentId}:${initialMode}`" :comment-id="commentId" :initial-mode="initialMode" embedded @dirty-change="dirty = $event" @busy-change="busy = $event" @saved="onSaved" @command-applied="emit('saved')" /></v-card-text>
    </v-card>
  </v-dialog>
  <AdminUnsavedChangesDialog :model-value="unsavedChangesDialog" @stay="continueEditing" @discard="discardChanges" />
</template>

<script setup lang="ts">
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
import { useAdminUnsavedChanges } from '~/composables/useAdminUnsavedChanges'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useDisplay } from 'vuetify'
import CommentDetailContent from '~/components/comments/CommentDetailContent.vue'
const props = withDefaults(defineProps<{ modelValue: boolean; commentId: number | null; sourcePath: string; initialMode?: 'view' | 'edit' }>(), { initialMode: 'view' })
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { xs } = useDisplay()
const dirty = ref(false)
const busy = ref(false)
const { unsavedChangesDialog, continueEditing, discardChanges, confirmUnsavedChanges } = useAdminUnsavedChanges(dirty, { guardQuery: true })
async function requestClose(open: boolean) {
  if (open || busy.value || !(await confirmUnsavedChanges())) return
  emit('update:modelValue', false)
}
function onSaved() {
  dirty.value = false
  emit('saved')
  if (props.initialMode === 'edit') emit('update:modelValue', false)
}
watch(() => props.modelValue, open => {
  if (!open) { dirty.value = false; busy.value = false }
})
const restoreDialogFocus = useAdminDialogFocusReturn(() => props.modelValue)
</script>
