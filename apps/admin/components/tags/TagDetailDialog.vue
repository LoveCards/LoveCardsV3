<template>
  <v-dialog @after-leave="restoreDialogFocus" :model-value="modelValue" :fullscreen="xs" :persistent="busy" aria-label="标签详情" max-width="720" scrollable @update:model-value="requestClose">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-2">
        <span>标签 #{{ tagId }}</span>
        <v-btn aria-label="关闭" icon="mdi-close" variant="text" @click="requestClose(false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0">
        <TagDetailContent v-if="modelValue && tagId" :key="`${tagId}:${initialMode}`" :tag-id="tagId" :initial-mode="initialMode" @dirty-change="dirty = $event" @busy-change="busy = $event" @saved="onSaved" @command-applied="emit('saved')" />
      </v-card-text>
    </v-card>
  </v-dialog>
  <AdminUnsavedChangesDialog :model-value="unsavedChangesDialog" @stay="continueEditing" @discard="discardChanges" />
</template>

<script setup lang="ts">
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
import { useAdminUnsavedChanges } from '~/composables/useAdminUnsavedChanges'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useDisplay } from 'vuetify'
import TagDetailContent from '~/components/tags/TagDetailContent.vue'

const props = withDefaults(defineProps<{ modelValue: boolean; tagId: number | null; sourcePath: string; initialMode?: 'view' | 'edit' }>(), { initialMode: 'view' })
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { xs } = useDisplay()
const dirty = ref(false)
const busy = ref(false)
const { unsavedChangesDialog, continueEditing, discardChanges, confirmUnsavedChanges } = useAdminUnsavedChanges(dirty, { guardQuery: true })
async function requestClose(open: boolean) {
  if (open || busy.value || !(await confirmUnsavedChanges())) return
  emit('update:modelValue', false)
}
function onSaved() { dirty.value = false; emit('saved'); if (props.initialMode !== 'view') emit('update:modelValue', false) }
watch(() => props.modelValue, open => { if (!open) { dirty.value = false; busy.value = false } })
const restoreDialogFocus = useAdminDialogFocusReturn(() => props.modelValue)
</script>
