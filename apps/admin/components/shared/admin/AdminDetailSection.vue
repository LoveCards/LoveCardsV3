<template>
  <section :aria-labelledby="headingId">
    <v-sheet border rounded="lg" class="admin-detail-section pa-4">
      <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-4">
        <div>
          <h2 :id="headingId" class="text-h6">{{ title }}</h2>
          <p v-if="description" class="text-body-2 text-medium-emphasis mt-1 mb-0">{{ description }}</p>
        </div>
        <slot name="actions" />
      </div>
      <slot />
    </v-sheet>
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
}
</style>
