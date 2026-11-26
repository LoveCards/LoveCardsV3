<template>
  <v-dialog :model-value="modelValue" :fullscreen="xs" max-width="720" scrollable aria-label="查看站内通知" @update:model-value="emit('update:modelValue', $event)" @after-leave="restoreDialogFocus">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-3">
        <span>通知 #{{ notificationId }}</span>
        <v-btn aria-label="关闭" icon="mdi-close" variant="text" @click="emit('update:modelValue', false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0"><NotificationDetailContent v-if="modelValue && notificationId" :notification-id="notificationId" /></v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useDisplay } from 'vuetify'
import NotificationDetailContent from './NotificationDetailContent.vue'
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
const props = defineProps<{ modelValue: boolean; notificationId: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { xs } = useDisplay()
const restoreDialogFocus = useAdminDialogFocusReturn(() => props.modelValue)
</script>
