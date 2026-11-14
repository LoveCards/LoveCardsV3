<template>
  <v-btn
    v-if="target"
    :to="target"
    :prepend-icon="icon"
    class="admin-resource-link px-2"
    min-height="44"
    variant="text"
  >
    <AdminTableSearchHighlight :text="displayLabel" :query="highlightQuery" />
  </v-btn>
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
  card: { target: (id) => `/apps/content/cards?inspect=${encodeURIComponent(id)}`, label: '卡片', icon: 'mdi-card-text-outline' },
  comment: { target: (id) => `/apps/content/comments?inspect=${encodeURIComponent(id)}`, label: '评论', icon: 'mdi-comment-text-outline' },
  tag: { target: (id) => `/apps/content/tags?inspect=${encodeURIComponent(id)}`, label: '标签', icon: 'mdi-tag-outline' },
  user: { target: (id) => `/apps/users/${encodeURIComponent(id)}`, label: '用户', icon: 'mdi-account-outline' },
  file: { target: (id) => `/apps/settings/storage/files/${encodeURIComponent(id)}/usage`, label: '文件', icon: 'mdi-file-outline' },
  media: { target: (id) => `/apps/settings/storage/files/${encodeURIComponent(id)}/usage`, label: '媒体', icon: 'mdi-image-outline' },
  report: { target: (id) => `/apps/community/reports/${encodeURIComponent(id)}`, label: '举报', icon: 'mdi-flag-outline' },
  role: { target: (id) => `/apps/access/roles/${encodeURIComponent(id)}`, label: '角色', icon: 'mdi-account-key-outline' },
  event_delivery: { target: (id) => `/apps/operations/deliveries/${encodeURIComponent(id)}`, label: '投递', icon: 'mdi-send-check-outline' },
}

const userStore = useUserStore()
const normalizedType = computed(() => props.resourceType.toLowerCase())
const definition = computed(() => resourceMap[normalizedType.value])
const icon = computed(() => definition.value?.icon ?? 'mdi-link-variant')
const target = computed(() => {
  if (!definition.value || props.resourceId === null || props.resourceId === undefined || props.resourceId === '') return null
  const path = definition.value.target(String(props.resourceId))
  return userStore.canAccessPath(path) ? path : null
})
const displayLabel = computed(() => props.label || `${definition.value?.label ?? props.resourceType} #${props.resourceId ?? '-'}`)
</script>

<style scoped>
.admin-resource-link {
  text-transform: none;
}
</style>
