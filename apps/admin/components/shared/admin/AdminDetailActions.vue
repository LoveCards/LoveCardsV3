<template>
  <div class="admin-detail-actions" :class="{ 'admin-detail-actions--inset': inset }" role="group" aria-label="记录操作">
    <v-divider />
    <v-card-actions class="admin-detail-actions__row">
      <div v-if="$slots.secondary" class="admin-detail-actions__secondary"><slot name="secondary" /></div>
      <div v-if="$slots.default" class="admin-detail-actions__primary"><slot /></div>
    </v-card-actions>
  </div>
</template>

<script setup lang="ts">
// 只统一动作位置与响应式间距；权限、草稿和提交由资源主体负责。
withDefaults(defineProps<{ inset?: boolean }>(), { inset: false })
</script>

<style scoped>
.admin-detail-actions { min-width: 0; }
.admin-detail-actions--inset { margin-top: 20px; }
.admin-detail-actions__row { flex-wrap: wrap; gap: 12px; padding: 24px; }
.admin-detail-actions--inset .admin-detail-actions__row { padding: 16px 0 0; }
.admin-detail-actions__secondary,
.admin-detail-actions__primary { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; min-width: 0; }
.admin-detail-actions__primary { justify-content: flex-end; margin-inline-start: auto; }
.admin-detail-actions :deep(.v-btn) { margin-inline-start: 0; }
@media (max-width: 599.98px) {
  .admin-detail-actions__row { padding: 16px; }
  .admin-detail-actions__secondary { flex-basis: 100%; }
  .admin-detail-actions :deep(.v-btn) { min-height: 44px; }
}
</style>
