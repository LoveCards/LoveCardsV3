<template>
  <div class="admin-detail-field">
    <div class="admin-detail-field__label">{{ label }}</div>
    <div class="admin-detail-field__value"><slot>{{ value === undefined || value === null || value === '' ? '—' : value }}</slot></div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ label: string; value?: string | number | null }>()
</script>

<style scoped>
.admin-detail-field {
  min-width: 0;
}

.admin-detail-field__label {
  margin-bottom: 4px;
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.875rem;
  line-height: 1.4;
}

.admin-detail-field__value {
  overflow-wrap: anywhere;
  white-space: pre-wrap;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  line-height: 1.5;
}
</style>
