<template>
  <v-timeline v-if="items.length" align="start" density="compact" side="end" truncate-line="both">
    <v-timeline-item
      v-for="(item, index) in items"
      :key="item.id ?? `${item.title}-${index}`"
      :dot-color="dotColor(item.status)"
      size="small"
    >
      <div class="admin-timeline-item">
        <div class="d-flex flex-wrap align-center ga-2">
          <strong>{{ item.title }}</strong>
          <AdminStatusChip v-if="item.status" :status="item.status" />
        </div>
        <p v-if="item.description" class="mb-1 mt-1 text-body-2 text-medium-emphasis">
          {{ item.description }}
        </p>
        <div class="text-caption text-medium-emphasis">
          <span v-if="item.actor">{{ item.actor }}</span>
          <span v-if="item.actor && item.timestamp"> · </span>
          <time v-if="item.timestamp">{{ item.timestamp }}</time>
        </div>
      </div>
    </v-timeline-item>
  </v-timeline>
  <div v-else class="py-4 text-body-2 text-medium-emphasis">暂无状态记录</div>
</template>

<script setup lang="ts">
import AdminStatusChip from './AdminStatusChip.vue'

export interface AdminTimelineItem {
  id?: string | number
  title: string
  description?: string | null
  timestamp?: string | null
  actor?: string | null
  status?: string | null
}

defineProps<{
  items: AdminTimelineItem[]
}>()

const dotColor = (status?: string | null) => {
  const value = String(status ?? '').toLowerCase()
  if (['failed', 'error', 'dead', 'dead_letter', 'rejected'].includes(value)) return 'error'
  if (['pending', 'retry', 'degraded'].includes(value)) return 'warning'
  if (['complete', 'completed', 'delivered', 'resolved', 'approved'].includes(value)) return 'success'
  return 'primary'
}
</script>

<style scoped>
.admin-timeline-item {
  max-width: 72ch;
  padding-bottom: 8px;
}
</style>
