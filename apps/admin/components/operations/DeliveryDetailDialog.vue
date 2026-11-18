<template>
  <v-dialog
    :model-value="modelValue"
    :fullscreen="smAndDown"
    :persistent="busy"
    aria-label="查看处理任务"
    max-width="1040"
    scrollable
    @update:model-value="requestClose"
  >
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-3">
        <span>处理任务 #{{ deliveryId }}</span>
        <v-btn aria-label="关闭处理任务" :disabled="busy" icon="mdi-close" variant="text" @click="requestClose(false)" />
      </v-card-title>
      <v-divider />
      <v-card-text>
        <DeliveryDetailContent v-if="modelValue && deliveryId" :delivery-id="deliveryId" @busy-change="busy = $event" @updated="emit('updated')" />
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="busy" variant="text" @click="requestClose(false)">关闭</v-btn>
        <v-btn v-if="deliveryId" :disabled="busy" variant="outlined" @click="openFullPage">打开完整页面</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useDisplay } from 'vuetify'
import DeliveryDetailContent from '~/components/operations/DeliveryDetailContent.vue'

const props = defineProps<{ modelValue: boolean; deliveryId: number | null; sourcePath: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; updated: [] }>()
const { smAndDown } = useDisplay()
const router = useRouter()
const busy = ref(false)
function requestClose(open: boolean) {
  if (!open && !busy.value) emit('update:modelValue', false)
}

async function openFullPage() {
  if (!props.deliveryId || busy.value) return
  const path = `/apps/operations/deliveries/${props.deliveryId}`
  emit('update:modelValue', false)
  await nextTick()
  await router.push({ path, state: { listSource: props.sourcePath } })
}
watch(() => props.modelValue, open => { if (!open) busy.value = false })
</script>
