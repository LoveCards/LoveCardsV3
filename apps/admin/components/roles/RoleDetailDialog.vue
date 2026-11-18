<template>
  <v-dialog :model-value="modelValue" :fullscreen="smAndDown" :persistent="busy" aria-label="角色详情" max-width="900" scrollable @update:model-value="requestClose">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between ga-2">
        <span>角色 #{{ roleId }}</span>
        <v-btn aria-label="关闭角色详情" icon="mdi-close" variant="text" @click="requestClose(false)" />
      </v-card-title>
      <v-divider />
      <v-card-text class="pa-0">
        <RoleDetailContent v-if="modelValue && roleId" :key="`${roleId}:${initialMode}`" :role-id="roleId" :initial-mode="initialMode" @dirty-change="dirty = $event" @busy-change="busy = $event" @saved="emit('saved')" />
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-btn v-if="roleId && !dirty" :to="{ path: `/apps/access/roles/${roleId}`, state: { listSource: sourcePath } }" variant="text">打开完整页面</v-btn>
        <v-spacer />
        <v-btn variant="outlined" :disabled="busy" @click="requestClose(false)">关闭</v-btn>
      </v-card-actions>
      <v-alert v-if="confirmClose" class="ma-4" type="warning" variant="tonal">
        角色有未保存修改，确定关闭并放弃吗？
        <template #append><v-btn variant="text" @click="confirmClose = false">继续编辑</v-btn><v-btn color="warning" variant="flat" @click="discardAndClose">放弃修改</v-btn></template>
      </v-alert>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useDisplay } from 'vuetify'
import RoleDetailContent from '~/components/roles/RoleDetailContent.vue'

const props = withDefaults(defineProps<{ modelValue: boolean; roleId: number | null; sourcePath: string; initialMode?: 'view' | 'edit' | 'assign' }>(), { initialMode: 'view' })
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { smAndDown } = useDisplay()
const dirty = ref(false)
const busy = ref(false)
const confirmClose = ref(false)
function requestClose(open: boolean) {
  if (open || busy.value) return
  if (dirty.value) { confirmClose.value = true; return }
  emit('update:modelValue', false)
}
function discardAndClose() { confirmClose.value = false; dirty.value = false; emit('update:modelValue', false) }
watch(() => props.modelValue, open => { if (!open) { dirty.value = false; busy.value = false; confirmClose.value = false } })
</script>
