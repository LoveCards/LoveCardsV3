<template>
  <v-dialog @after-leave="restoreDialogFocus" :model-value="modelValue" :fullscreen="xs" :persistent="busy" aria-label="文件详情" max-width="1040" scrollable @update:model-value="requestClose">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-2">
        <span>文件 #{{ fileId }}</span>
        <v-btn aria-label="关闭" icon="mdi-close" variant="text" :disabled="busy" @click="requestClose(false)" />
      </v-card-title>
      <v-divider />
      <v-card-text>
        <FileDetailContent v-if="modelValue && fileId" :key="fileId" :file-id="fileId" @changed="emit('changed')" @removed="handleRemoved" @busy-change="busy = $event" />
      </v-card-text>
      <v-divider v-if="fileId" />
      <v-card-actions v-if="fileId" class="pa-4">
        <v-btn v-if="fileId" :to="`/apps/settings/storage/files/${fileId}`" variant="text">打开完整页面</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
import { useDisplay } from 'vuetify'
import FileDetailContent from '~/components/storage/FileDetailContent.vue'

const props = defineProps<{ modelValue: boolean; fileId: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; changed: [] }>()
const { xs } = useDisplay()
const busy = ref(false)
function requestClose(open: boolean) {
  if (!open && !busy.value) emit('update:modelValue', false)
}
function handleRemoved() {
  emit('changed')
  emit('update:modelValue', false)
}
const restoreDialogFocus = useAdminDialogFocusReturn(() => props.modelValue)
</script>
