<template>
  <v-dialog @after-leave="restoreDialogFocus" :model-value="modelValue" :fullscreen="xs" :persistent="busy" aria-label="标签详情" max-width="720" scrollable @update:model-value="requestClose">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-2">
        <span>标签 #{{ tagId }}</span>
        <v-btn aria-label="关闭" icon="mdi-close" variant="text" @click="requestClose(false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0">
        <TagDetailContent v-if="modelValue && tagId" :key="`${tagId}:${initialMode}`" :tag-id="tagId" :initial-mode="initialMode" @dirty-change="dirty = $event" @busy-change="busy = $event" @saved="emit('saved')" />
      </v-card-text>
      <v-divider v-if="tagId && !dirty" />
      <v-card-actions v-if="tagId && !dirty" class="pa-4">
        <v-btn v-if="tagId && !dirty" :to="{ path: `/apps/content/tags/${tagId}`, state: { listSource: sourcePath } }" variant="text">打开完整页面</v-btn>
      </v-card-actions>
      <v-alert v-if="confirmClose" class="ma-4" type="warning" variant="tonal">
        标签名称有未保存修改，确定关闭并放弃吗？
        <template #append>
          <v-btn variant="text" @click="confirmClose = false">继续编辑</v-btn>
          <v-btn color="warning" variant="flat" @click="discardAndClose">放弃修改</v-btn>
        </template>
      </v-alert>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useAdminDialogFocusReturn } from '~/composables/useAdminDialogFocusReturn'
import { useDisplay } from 'vuetify'
import TagDetailContent from '~/components/tags/TagDetailContent.vue'

const props = withDefaults(defineProps<{ modelValue: boolean; tagId: number | null; sourcePath: string; initialMode?: 'view' | 'edit' }>(), { initialMode: 'view' })
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { xs } = useDisplay()
const dirty = ref(false)
const busy = ref(false)
const confirmClose = ref(false)
function requestClose(open: boolean) {
  if (open || busy.value) return
  if (dirty.value) { confirmClose.value = true; return }
  emit('update:modelValue', false)
}
function discardAndClose() { dirty.value = false; confirmClose.value = false; emit('update:modelValue', false) }
watch(() => props.modelValue, open => { if (!open) { dirty.value = false; busy.value = false; confirmClose.value = false } })
const restoreDialogFocus = useAdminDialogFocusReturn(() => props.modelValue)
</script>
