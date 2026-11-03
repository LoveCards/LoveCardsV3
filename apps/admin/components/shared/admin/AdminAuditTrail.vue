<template>
  <section aria-labelledby="audit-trail-title">
    <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
      <h2 id="audit-trail-title" class="text-h6">审计记录</h2>
      <v-chip size="small" variant="outlined">{{ items.length }} 条</v-chip>
    </div>
    <AdminStateTimeline :items="timelineItems" />
  </section>
</template>

<script setup lang="ts">
import AdminStateTimeline, { type AdminTimelineItem } from './AdminStateTimeline.vue'

export interface AdminAuditTrailItem {
  id?: string | number
  action: string
  actor?: string | number | null
  createdAt?: string | null
  detail?: string | null
  status?: string | null
}

const props = defineProps<{ items: AdminAuditTrailItem[] }>()

const timelineItems = computed<AdminTimelineItem[]>(() => props.items.map((item) => ({
  id: item.id,
  title: item.action,
  actor: item.actor === null || item.actor === undefined ? '系统' : `操作者 #${item.actor}`,
  timestamp: item.createdAt,
  description: item.detail,
  status: item.status,
})))
</script>
