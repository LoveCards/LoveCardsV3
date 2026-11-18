<template>
  <v-dialog
    :model-value="modelValue"
    :fullscreen="smAndDown"
    aria-label="查看审计"
    max-width="1040"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-3">
        <span>审计记录 #{{ auditId }}</span>
        <v-btn aria-label="关闭审计记录" icon="mdi-close" variant="text" @click="emit('update:modelValue', false)" />
      </v-card-title>
      <v-divider />
      <v-card-text>
        <AuditDetailContent v-if="modelValue && auditId" :audit-id="auditId" />
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="emit('update:modelValue', false)">关闭</v-btn>
        <v-btn v-if="auditId" variant="outlined" @click="openFullPage">打开完整页面</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useDisplay } from 'vuetify'
import AuditDetailContent from '~/components/operations/AuditDetailContent.vue'

const props = defineProps<{ modelValue: boolean; auditId: number | null; sourcePath: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { smAndDown } = useDisplay()
const router = useRouter()

async function openFullPage() {
  if (!props.auditId) return
  const path = `/apps/operations/audit/${props.auditId}`
  emit('update:modelValue', false)
  await nextTick()
  await router.push({ path, state: { listSource: props.sourcePath } })
}
</script>
