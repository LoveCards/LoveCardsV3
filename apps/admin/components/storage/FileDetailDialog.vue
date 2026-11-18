<template>
  <v-dialog :model-value="modelValue" :fullscreen="smAndDown" :persistent="busy" aria-label="文件详情" max-width="1040" scrollable @update:model-value="requestClose">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-2">
        <span>文件 #{{ fileId }}</span>
        <v-btn aria-label="关闭文件详情" icon="mdi-close" variant="text" :disabled="busy" @click="requestClose(false)" />
      </v-card-title>
      <v-divider />
      <v-card-text>
        <FileDetailContent v-if="modelValue && fileId" :key="fileId" :file-id="fileId" @changed="emit('changed')" @removed="handleRemoved" @busy-change="busy = $event" />
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-btn v-if="fileId" :to="`/apps/settings/storage/files/${fileId}`" variant="text">打开完整页面</v-btn>
        <v-spacer />
        <v-btn variant="outlined" :disabled="busy" @click="requestClose(false)">关闭</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useDisplay } from 'vuetify'
import FileDetailContent from '~/components/storage/FileDetailContent.vue'

defineProps<{ modelValue: boolean; fileId: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; changed: [] }>()
const { smAndDown } = useDisplay()
const busy = ref(false)
function requestClose(open: boolean) {
  if (!open && !busy.value) emit('update:modelValue', false)
}
function handleRemoved() {
  emit('changed')
  emit('update:modelValue', false)
}
</script>
