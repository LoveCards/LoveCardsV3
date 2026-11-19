<template>
  <v-dialog :model-value="modelValue" :fullscreen="xs" :persistent="busy" aria-label="卡片详情" max-width="1040" scrollable @update:model-value="requestClose">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-2">
        <span>卡片 #{{ cardId }}</span>
        <v-btn aria-label="关闭" icon="mdi-close" variant="text" @click="requestClose(false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0">
        <CardDetailContent
          v-if="modelValue && cardId"
          :key="`${cardId}:${initialMode}`"
          :card-id="cardId"
          embedded
          :initial-mode="initialMode"
          @dirty-change="dirty = $event"
          @busy-change="busy = $event"
          @saved="emit('saved')"
        />
      </v-card-text>
      <v-divider v-if="cardId && !dirty" />
      <v-card-actions v-if="cardId && !dirty" class="pa-4">
        <v-btn v-if="cardId && !dirty" :to="{ path: `/apps/content/cards/${cardId}`, state: { listSource: sourcePath } }" variant="text">打开完整页面</v-btn>
      </v-card-actions>
      <v-alert v-if="confirmClose" class="ma-4" type="warning" variant="tonal">
        卡片有未保存修改，确定关闭并放弃吗？
        <template #append>
          <v-btn variant="text" @click="confirmClose = false">继续编辑</v-btn>
          <v-btn color="warning" variant="flat" @click="discardAndClose">放弃修改</v-btn>
        </template>
      </v-alert>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useDisplay } from 'vuetify'
import CardDetailContent from '~/components/cards/CardDetailContent.vue'

const props = withDefaults(defineProps<{
  modelValue: boolean
  cardId: number | null
  sourcePath: string
  initialMode?: 'view' | 'edit'
}>(), { initialMode: 'view' })
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { xs } = useDisplay()
const dirty = ref(false)
const busy = ref(false)
const confirmClose = ref(false)

function requestClose(open: boolean) {
  if (open) return
  if (busy.value) return
  if (dirty.value) { confirmClose.value = true; return }
  emit('update:modelValue', false)
}
function discardAndClose() {
  confirmClose.value = false
  dirty.value = false
  emit('update:modelValue', false)
}
watch(() => props.modelValue, open => { if (!open) { dirty.value = false; busy.value = false; confirmClose.value = false } })
</script>
