<template>
  <v-btn
    v-if="target"
    :to="target"
    :prepend-icon="icon"
    class="admin-resource-link px-2"
    min-height="44"
    variant="text"
  >
    {{ displayLabel }}
  </v-btn>
  <span v-else class="text-medium-emphasis">{{ displayLabel }}</span>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  resourceType: string
  resourceId?: string | number | null
  label?: string
}>(), {
  resourceId: null,
  label: '',
})

const resourceMap: Record<string, { target: (id: string) => string; label: string; icon: string }> = {
  card: { target: (id) => `/apps/cards?search_value=${encodeURIComponent(id)}`, label: '卡片', icon: 'mdi-card-text-outline' },
  comment: { target: (id) => `/apps/comments?search_value=${encodeURIComponent(id)}`, label: '评论', icon: 'mdi-comment-text-outline' },
  user: { target: (id) => `/apps/users/${encodeURIComponent(id)}/security`, label: '用户', icon: 'mdi-account-outline' },
  file: { target: (id) => `/apps/storage/files/${encodeURIComponent(id)}/usage`, label: '文件', icon: 'mdi-file-outline' },
  media: { target: (id) => `/apps/storage/files/${encodeURIComponent(id)}/usage`, label: '媒体', icon: 'mdi-image-outline' },
  report: { target: (id) => `/apps/governance/reports/${encodeURIComponent(id)}`, label: '举报', icon: 'mdi-flag-outline' },
}

const normalizedType = computed(() => props.resourceType.toLowerCase())
const definition = computed(() => resourceMap[normalizedType.value])
const icon = computed(() => definition.value?.icon ?? 'mdi-link-variant')
const target = computed(() => {
  if (!definition.value || props.resourceId === null || props.resourceId === undefined || props.resourceId === '') return null
  return definition.value.target(String(props.resourceId))
})
const displayLabel = computed(() => props.label || `${definition.value?.label ?? props.resourceType} #${props.resourceId ?? '-'}`)
</script>

<style scoped>
.admin-resource-link {
  text-transform: none;
}
</style>
