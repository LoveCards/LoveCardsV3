<template>
  <v-dialog @after-leave="restoreDialogFocus"
    :model-value="modelValue"
    :fullscreen="xs"
    aria-label="查看审计"
    max-width="1040"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-3">
        <span>审计记录 #{{ auditId }}</span>
        <v-btn aria-label="关闭" icon="mdi-close" variant="text" @click="emit('update:modelValue', false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0">
        <AuditDetailContent v-if="modelValue && auditId" :audit-id="auditId" />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
import { useDisplay } from 'vuetify'
import AuditDetailContent from '~/components/operations/AuditDetailContent.vue'

const props = defineProps<{ modelValue: boolean; auditId: number | null; sourcePath: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { xs } = useDisplay()


const restoreDialogFocus = useAdminDialogFocusReturn(() => props.modelValue)
</script>
