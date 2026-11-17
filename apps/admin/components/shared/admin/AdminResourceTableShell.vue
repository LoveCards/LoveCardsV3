<template>
  <v-card class="admin-resource-table-shell">
    <v-card-text v-if="$slots.search || $slots.actions || $slots.toolbar || preferenceKey || resultCount !== null" class="pa-4">
      <v-defaults-provider :defaults="filterDefaults">
        <div v-if="$slots.toolbar" class="mb-3"><slot name="toolbar" /></div>
        <v-row align="center" dense>
          <v-col v-if="$slots.search" cols="12" md="5"><slot name="search" /></v-col>
          <v-col cols="12" :md="$slots.search ? 7 : 12" class="d-flex flex-wrap align-center justify-end ga-2">
            <slot name="actions" />
            <span v-if="resultCount !== null" class="text-body-2 text-medium-emphasis" aria-live="polite">{{ resultState === 'ready' ? '共' : resultState === 'loading' ? '更新中 · 上次结果共' : '上次结果共' }} {{ resultCount }} 条</span>
            <AdminTableViewControls
              v-if="preferenceKey"
              v-model:visible-columns="visibleColumns"
              :columns="columns"
              :default-columns="defaultColumns"
              :show-saved-views="showSavedViews"
              :view-query-keys="viewQueryKeys"
              @apply="$emit('apply-view', $event)"
              :storage-key="preferenceKey"
              @reset="$emit('reset-view')"
            />
          </v-col>
        </v-row>
        <div v-if="$slots.selection" class="mt-4 pt-4 border-t"><slot name="selection" /></div>
      </v-defaults-provider>
    </v-card-text>
    <v-divider v-if="$slots.search || $slots.actions || $slots.toolbar || preferenceKey || resultCount !== null" />

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
  resultState?: 'ready' | 'loading' | 'stale'
  showSavedViews?: boolean
  viewQueryKeys?: string[]
}>(), {
  preferenceKey: '',
  columns: () => [],
  defaultColumns: () => [],
  resultCount: null,
  resultState: 'ready',
  showSavedViews: true,
  viewQueryKeys: () => [],
})

defineEmits<{ 'reset-view': []; 'apply-view': [query: Record<string, string | null | (string | null)[]>] }>()
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

@media (max-width: 599.98px) {
  :deep(.v-data-table table) { min-width: 0; width: 100%; }
  /* 无服务端排序的表格不显示 Vuetify 移动端的空排序表头。 */
  :deep(.admin-table-no-sort thead) { display: none; }
}

/* 空态仍在表格单元格中，但窄屏不应要求横向滚动才能阅读和操作。 */
:deep(.v-data-table-rows-no-data .v-empty-state) {
  position: sticky;
  left: 0;
  max-width: calc(100cqw - 32px);
}
</style>
