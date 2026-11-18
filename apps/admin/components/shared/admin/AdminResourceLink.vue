<template>
  <NuxtLink
    v-if="target"
    :to="{ path: target, state: { adminSource: route.fullPath } }"
    class="admin-resource-link"
  >
    <AdminTableSearchHighlight :text="displayLabel" :query="highlightQuery" />
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

const resourceMap: Record<string, { target: (id: string) => string; label: string; icon: string }> = {
  card: { target: (id) => `/apps/content/cards/${encodeURIComponent(id)}`, label: '卡片', icon: 'mdi-card-text-outline' },
  comment: { target: (id) => `/apps/content/comments/${encodeURIComponent(id)}`, label: '评论', icon: 'mdi-comment-text-outline' },
  tag: { target: (id) => `/apps/content/tags?inspect=${encodeURIComponent(id)}`, label: '标签', icon: 'mdi-tag-outline' },
  user: { target: (id) => `/apps/users/${encodeURIComponent(id)}`, label: '用户', icon: 'mdi-account-outline' },
  file: { target: (id) => `/apps/settings/storage/files/${encodeURIComponent(id)}/usage`, label: '文件', icon: 'mdi-file-outline' },
  media: { target: (id) => `/apps/settings/storage/files/${encodeURIComponent(id)}/usage`, label: '媒体', icon: 'mdi-image-outline' },
  report: { target: (id) => `/apps/community/reports/${encodeURIComponent(id)}`, label: '举报', icon: 'mdi-flag-outline' },
  role: { target: (id) => `/apps/access/roles/${encodeURIComponent(id)}`, label: '角色', icon: 'mdi-account-key-outline' },
  event_delivery: { target: (id) => `/apps/operations/deliveries/${encodeURIComponent(id)}`, label: '投递', icon: 'mdi-send-check-outline' },
}

const userStore = useUserStore()
const route = useRoute()
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
  text-decoration: underline;
  text-decoration-thickness: 1px;
  text-underline-offset: 3px;
}
.admin-resource-link:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}
</style>
