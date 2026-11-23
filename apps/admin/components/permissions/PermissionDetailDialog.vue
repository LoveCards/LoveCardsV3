<template>
  <v-dialog @after-leave="restoreDialogFocus"
    :model-value="modelValue"
    :fullscreen="xs"
    aria-label="查看权限"
    max-width="720"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-3">
        <span>权限 · {{ capability }}</span>
        <v-btn aria-label="关闭" icon="mdi-close" variant="text" @click="emit('update:modelValue', false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0">
        <PermissionDetailContent v-if="modelValue && capability && scope" :capability="capability" :scope="scope" />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
import { useDisplay } from 'vuetify'
import PermissionDetailContent from '~/components/permissions/PermissionDetailContent.vue'

const props = defineProps<{ modelValue: boolean; capability: string | null; scope: string | null; sourcePath: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { xs } = useDisplay()


const restoreDialogFocus = useAdminDialogFocusReturn(() => props.modelValue)
</script>
