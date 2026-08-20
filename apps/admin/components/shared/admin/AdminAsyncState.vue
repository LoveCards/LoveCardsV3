<template>
  <div class="admin-async-state" aria-live="polite">
    <template v-if="loading">
      <v-skeleton-loader :type="skeletonType" class="admin-async-state__skeleton" />
      <span class="sr-only">正在加载</span>
    </template>
    <v-alert v-else-if="error" role="alert" type="error" variant="tonal">
      <div class="d-flex flex-wrap align-center justify-space-between ga-3">
        <div>
          <div class="font-weight-medium">{{ errorTitle }}</div>
          <div class="text-body-2">{{ error }}</div>
        </div>
        <v-btn v-if="retryable" min-height="44" variant="outlined" @click="$emit('retry')">重试</v-btn>
      </div>
    </v-alert>
    <v-sheet v-else-if="empty" class="admin-async-state__empty" color="surface-variant" rounded="lg">
      <v-icon class="mb-3" color="medium-emphasis" size="40">{{ emptyIcon }}</v-icon>
      <h2 class="text-subtitle-1 font-weight-medium">{{ emptyTitle }}</h2>
      <p class="mb-0 mt-1 text-body-2 text-medium-emphasis">{{ emptyDescription }}</p>
      <div v-if="$slots.emptyAction" class="mt-4"><slot name="emptyAction" /></div>
    </v-sheet>
    <slot v-else />
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  loading?: boolean
  error?: string | null
  empty?: boolean
  retryable?: boolean
  errorTitle?: string
  emptyTitle?: string
  emptyDescription?: string
  emptyIcon?: string
  skeletonType?: string
}>(), {
  loading: false,
  error: null,
  empty: false,
  retryable: true,
  errorTitle: '加载失败',
  emptyTitle: '暂无数据',
  emptyDescription: '当前条件下没有可显示的记录。',
  emptyIcon: 'mdi-inbox-outline',
  skeletonType: 'table-heading, list-item-three-line@4',
})

defineEmits<{
  retry: []
}>()
</script>

<style scoped>
.admin-async-state__skeleton {
  border-radius: 8px;
}

.admin-async-state__empty {
  display: flex;
  min-height: 220px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 24px;
  text-align: center;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
