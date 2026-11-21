<template>
  <v-dialog @after-leave="restoreDialogFocus" :model-value="modelValue" :fullscreen="xs" :persistent="busy" aria-label="角色详情" max-width="880" scrollable @update:model-value="requestClose">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-2">
        <span>角色 #{{ roleId }}</span>
        <v-btn aria-label="关闭" icon="mdi-close" variant="text" @click="requestClose(false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0">
        <RoleDetailContent v-if="modelValue && roleId" :key="`${roleId}:${initialMode}`" :role-id="roleId" :initial-mode="initialMode" @dirty-change="dirty = $event" @busy-change="busy = $event" @saved="onSaved" />
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
import RoleDetailContent from '~/components/roles/RoleDetailContent.vue'

const props = withDefaults(defineProps<{ modelValue: boolean; roleId: number | null; sourcePath: string; initialMode?: 'view' | 'edit' | 'assign' }>(), { initialMode: 'view' })
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
