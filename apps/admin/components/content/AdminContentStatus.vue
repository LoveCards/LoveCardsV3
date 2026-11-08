<template>
  <div class="d-flex flex-column align-start ga-2 py-2">
    <span v-if="Number(status) === 0" class="text-body-2 text-medium-emphasis"><AdminTableSearchHighlight text="正常" :query="highlightQuery" /></span>
    <v-chip v-else :color="presentation.color" size="small" variant="tonal"><AdminTableSearchHighlight :text="presentation.label" :query="highlightQuery" /></v-chip>
    <span v-if="pinned" class="text-caption text-medium-emphasis">置顶</span>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ status: number; pinned?: boolean; highlightQuery?: string }>()
const presentation = computed(() => {
  switch (Number(props.status)) {
    case 1: return { label: '封禁', color: 'error' }
    case 2: return { label: '隐藏', color: undefined }
    case 3: return { label: '待审核', color: 'warning' }
    default: return { label: `未知状态 (${props.status})`, color: 'warning' }
  }
})
</script>
