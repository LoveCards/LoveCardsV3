<template>
  <NuxtLink
    v-if="target"
    :to="target"
    class="admin-resource-link"
  >
    <AdminTableSearchHighlight :text="displayLabel" :query="highlightQuery" />
    <span aria-hidden="true" class="admin-resource-link__direction">↗</span>
  </NuxtLink>
  <span v-else class="text-medium-emphasis"><AdminTableSearchHighlight :text="displayLabel" :query="highlightQuery" /></span>
</template>

<script setup lang="ts">
import { useUserStore } from '~/stores/userStore'

const props = withDefaults(defineProps<{
  resourceType: string
  resourceId?: string | number | null
  label?: string
  highlightQuery?: string
}>(), {
  resourceId: null,
  label: '',
})

const resourceMap: Record<string, { target: (id: string) => string; label: string }> = {
  card: { target: (id) => `/apps/content/cards/${encodeURIComponent(id)}`, label: '卡片' },
  comment: { target: (id) => `/apps/content/comments/${encodeURIComponent(id)}`, label: '评论' },
  tag: { target: (id) => `/apps/content/tags/${encodeURIComponent(id)}`, label: '标签' },
  user: { target: (id) => `/apps/users/${encodeURIComponent(id)}`, label: '用户' },
  file: { target: (id) => `/apps/settings/storage/files/${encodeURIComponent(id)}`, label: '文件' },
  media: { target: (id) => `/apps/settings/storage/files/${encodeURIComponent(id)}`, label: '媒体' },
  report: { target: (id) => `/apps/community/reports/${encodeURIComponent(id)}`, label: '举报' },
  role: { target: (id) => `/apps/access/roles/${encodeURIComponent(id)}`, label: '角色' },
  event_delivery: { target: (id) => `/apps/operations/deliveries/${encodeURIComponent(id)}`, label: '投递' },
}

const userStore = useUserStore()
const normalizedType = computed(() => props.resourceType.toLowerCase())
const definition = computed(() => resourceMap[normalizedType.value])
const target = computed(() => {
  if (!definition.value || props.resourceId === null || props.resourceId === undefined || props.resourceId === '') return null
  const path = definition.value.target(String(props.resourceId))
  return userStore.canAccessPath(path) ? path : null
})
const displayLabel = computed(() => props.label || `${definition.value?.label ?? props.resourceType} #${props.resourceId ?? '-'}`)
</script>

<style scoped>
.admin-resource-link {
  color: rgb(var(--v-theme-primary));
  display: inline-flex;
  align-items: baseline;
  gap: 0.2em;
  font-weight: 500;
  text-decoration: underline;
  text-underline-offset: 3px;
  text-transform: none;
}
.admin-resource-link:hover {
  text-decoration-thickness: 2px;
}
.admin-resource-link:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 3px;
  border-radius: 2px;
}
.admin-resource-link__direction {
  font-size: 0.8em;
  text-decoration: none;
}
</style>
