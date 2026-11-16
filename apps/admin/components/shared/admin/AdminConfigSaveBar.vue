<template>
  <div class="admin-config-save-bar d-flex flex-wrap align-center justify-space-between ga-3">
    <span class="text-body-2 text-medium-emphasis" role="status">
      {{ dirty ? `${label}有未保存修改` : '' }}
    </span>
    <div class="d-flex flex-wrap justify-end ga-2"><slot /></div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ dirty: boolean; label: string }>()
</script>

<style scoped>
.admin-config-save-bar {
  position: sticky;
  z-index: 2;
  bottom: 0;
  min-height: 64px;
  padding: 12px 16px;
  border-top: 1px solid rgb(var(--v-theme-outline));
  background: rgb(var(--v-theme-surface));
}

@media (max-width: 959.98px) {
  .admin-config-save-bar {
    position: static;
    padding-inline: 8px;
  }
}
</style>
