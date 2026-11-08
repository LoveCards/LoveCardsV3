<template>
  <div class="admin-content-summary py-4">
    <p class="admin-content-summary__body mb-2"><AdminTableSearchHighlight :text="excerpt" :query="highlightQuery" /></p>
    <div class="text-caption text-medium-emphasis d-flex flex-wrap align-center ga-2">
      <span>{{ author || `用户 #${userId}` }}</span>
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ content: string; author?: string; userId: number; highlightQuery?: string }>()
const excerpt = computed(() => props.content?.replace(/\s+/gu, ' ').trim() || '无文字内容')
</script>

<style scoped>
.admin-content-summary {
  min-width: 280px;
  max-width: 68ch;
}

.admin-content-summary__body {
  display: -webkit-box;
  overflow: hidden;
  overflow-wrap: anywhere;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  font-size: 0.875rem;
  line-height: 1.6;
}
</style>
