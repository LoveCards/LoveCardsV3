<template>
  <v-card class="admin-resource-table-shell">
    <v-card-text v-if="$slots.search || $slots.actions || $slots.toolbar || preferenceKey" class="pa-4">
      <v-defaults-provider :defaults="filterDefaults">
        <div v-if="$slots.toolbar" class="mb-3"><slot name="toolbar" /></div>
        <v-row align="center" dense>
          <v-col v-if="$slots.search" cols="12" md="5"><slot name="search" /></v-col>
          <v-col cols="12" :md="$slots.search ? 7 : 12" class="d-flex flex-wrap align-center justify-end ga-2">
            <slot name="actions" />
            <AdminTableViewControls
              v-if="preferenceKey"
              v-model:visible-columns="visibleColumns"
              :columns="columns"
              :default-columns="defaultColumns"
              :result-count="resultCount"
              :storage-key="preferenceKey"
              @reset="$emit('reset-view')"
            />
          </v-col>
        </v-row>
        <div v-if="$slots.selection" class="mt-4 pt-4 border-t"><slot name="selection" /></div>
      </v-defaults-provider>
    </v-card-text>
    <v-divider v-if="$slots.search || $slots.actions || $slots.toolbar || preferenceKey" />

    <slot />
  </v-card>
</template>

<script setup lang="ts">
import AdminTableViewControls, { type AdminTableColumnOption } from './AdminTableViewControls.vue'

const filterDefaults = {
  VTextField: { density: 'compact', hideDetails: true },
  VSelect: { density: 'compact', hideDetails: true },
  VAutocomplete: { density: 'compact', hideDetails: true },
}

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

<style scoped>
.admin-resource-table-shell {
  container-type: inline-size;
}

:deep(.v-table__wrapper) {
  overflow-x: auto;
}

:deep(.v-table table) {
  min-width: 720px;
}

/* 空态仍在表格单元格中，但窄屏不应要求横向滚动才能阅读和操作。 */
:deep(.v-data-table-rows-no-data .v-empty-state) {
  position: sticky;
  left: 0;
  max-width: calc(100cqw - 32px);
}
</style>
