<template>
  <v-dialog
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
      <v-card-text>
        <PermissionDetailContent v-if="modelValue && capability && scope" :capability="capability" :scope="scope" />
      </v-card-text>
      <v-divider v-if="capability && scope" />
      <v-card-actions v-if="capability && scope" class="pa-4">

        <v-btn v-if="capability && scope" variant="text" @click="openFullPage">打开完整页面</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { useDisplay } from 'vuetify'
import PermissionDetailContent from '~/components/permissions/PermissionDetailContent.vue'

const props = defineProps<{ modelValue: boolean; capability: string | null; scope: string | null; sourcePath: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { xs } = useDisplay()
const router = useRouter()

async function openFullPage() {
  if (!props.capability || !props.scope) return
  const path = `/apps/access/permissions/${encodeURIComponent(props.capability)}/${encodeURIComponent(props.scope)}`
  emit('update:modelValue', false)
  await nextTick()
  await router.push({ path, state: { listSource: props.sourcePath } })
}
</script>
