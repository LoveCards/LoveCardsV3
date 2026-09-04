<template>
  <v-card variant="outlined" rounded="lg">
    <AdminFilterToolbar v-if="$slots.toolbar">
      <slot name="toolbar" />
    </AdminFilterToolbar>

    <v-divider v-if="$slots.toolbar" />

    <AdminTableViewControls
      v-if="preferenceKey"
      v-model:visible-columns="visibleColumns"
      :columns="columns"
      :default-columns="defaultColumns"
      :result-count="resultCount"
      :storage-key="preferenceKey"
      @reset="$emit('reset-view')"
    />

    <v-divider v-if="preferenceKey" />

    <slot />
  </v-card>
</template>

<script setup lang="ts">
import AdminFilterToolbar from './AdminFilterToolbar.vue'
import AdminTableViewControls, { type AdminTableColumnOption } from './AdminTableViewControls.vue'

withDefaults(defineProps<{
  preferenceKey?: string
  columns?: AdminTableColumnOption[]
  defaultColumns?: string[]
  resultCount?: number | null
}>(), {
  preferenceKey: '',
  columns: () => [],
  defaultColumns: () => [],
  resultCount: null,
})

defineEmits<{ 'reset-view': [] }>()
const visibleColumns = defineModel<string[]>('visibleColumns', { default: () => [] })
</script>
