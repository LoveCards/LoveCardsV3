<template>
  <v-sheet aria-live="polite" color="transparent">
    <template v-if="loading">
      <v-skeleton-loader :type="skeletonType" aria-label="正在加载" role="status" />
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
    <v-empty-state
      v-else-if="empty"
      :icon="emptyIcon"
      :text="emptyDescription"
      :title="emptyTitle"
      class="py-10"
    >
      <template v-if="$slots.emptyAction" #actions>
        <slot name="emptyAction" />
      </template>
    </v-empty-state>
    <slot v-else />
  </v-sheet>
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
