<template>
  <section :aria-labelledby="headingId" class="admin-detail-section">
    <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-4">
      <div>
        <h2 :id="headingId" class="text-h6">{{ title }}</h2>
        <p v-if="description" class="text-body-2 text-medium-emphasis mt-1 mb-0">{{ description }}</p>
      </div>
      <slot name="actions" />
    </div>
    <slot />
  </section>
</template>

<script setup lang="ts">
import { useId } from 'vue'

defineProps<{ title: string; description?: string }>()
const headingId = useId()
</script>

<style scoped>
.admin-detail-section {
  min-width: 0;
  padding: 24px;
  background: rgb(var(--v-theme-surface));
  border-inline: 1px solid rgb(var(--v-theme-outline));
}

.admin-detail-section:first-of-type {
  border-top: 1px solid rgb(var(--v-theme-outline));
  border-radius: 8px 8px 0 0;
}

.admin-detail-section:not(:first-of-type) {
  border-top: 1px solid rgb(var(--v-theme-outline));
}

.admin-detail-section:last-of-type {
  border-bottom: 1px solid rgb(var(--v-theme-outline));
  border-radius: 0 0 8px 8px;
}

.admin-detail-section:only-of-type {
  border-radius: 8px;
}

:global(.v-dialog) .admin-detail-section {
  border: 0;
  border-radius: 0;
}

:global(.v-dialog) .admin-detail-section + .admin-detail-section {
  border-top: 1px solid rgb(var(--v-theme-outline));
}

@media (max-width: 600px) {
  .admin-detail-section {
    padding: 16px;
  }
}
</style>
