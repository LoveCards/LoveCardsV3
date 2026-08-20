<template>
  <v-chip
    :color="resolved.color"
    :prepend-icon="resolved.icon"
    :variant="resolved.variant"
    size="small"
    label
  >
    {{ label || resolved.label }}
  </v-chip>
</template>

<script setup lang="ts">
type StatusTone = 'success' | 'warning' | 'error' | 'info' | 'neutral'

const props = withDefaults(defineProps<{
  status?: string | number | null
  label?: string
  tone?: StatusTone
}>(), {
  status: 'unknown',
  label: '',
  tone: undefined,
})

const statusMap: Record<string, { label: string; color: string; icon: string; variant: 'tonal' | 'outlined' }> = {
  active: { label: '运行中', color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  available: { label: '可用', color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  complete: { label: '已完成', color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  completed: { label: '已完成', color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  delivered: { label: '已投递', color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  resolved: { label: '已解决', color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  approved: { label: '已通过', color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  pending: { label: '待处理', color: 'warning', icon: 'mdi-clock-outline', variant: 'tonal' },
  open: { label: '待处理', color: 'warning', icon: 'mdi-clock-outline', variant: 'tonal' },
  reviewing: { label: '处理中', color: 'info', icon: 'mdi-progress-clock', variant: 'tonal' },
  processing: { label: '处理中', color: 'info', icon: 'mdi-progress-clock', variant: 'tonal' },
  running: { label: '执行中', color: 'info', icon: 'mdi-progress-clock', variant: 'tonal' },
  retry: { label: '等待重试', color: 'warning', icon: 'mdi-refresh', variant: 'tonal' },
  degraded: { label: '已降级', color: 'warning', icon: 'mdi-alert-outline', variant: 'tonal' },
  failed: { label: '失败', color: 'error', icon: 'mdi-alert-circle-outline', variant: 'tonal' },
  error: { label: '错误', color: 'error', icon: 'mdi-alert-circle-outline', variant: 'tonal' },
  dead_letter: { label: '死信', color: 'error', icon: 'mdi-email-alert-outline', variant: 'tonal' },
  dead: { label: '死信', color: 'error', icon: 'mdi-email-alert-outline', variant: 'tonal' },
  rejected: { label: '已驳回', color: 'error', icon: 'mdi-close-circle-outline', variant: 'tonal' },
  disabled: { label: '未启用', color: 'grey-darken-1', icon: 'mdi-minus-circle-outline', variant: 'outlined' },
  unavailable: { label: '不可用', color: 'grey-darken-1', icon: 'mdi-minus-circle-outline', variant: 'outlined' },
  closed: { label: '已关闭', color: 'grey-darken-1', icon: 'mdi-lock-outline', variant: 'outlined' },
  expired: { label: '已过期', color: 'grey-darken-1', icon: 'mdi-clock-remove-outline', variant: 'outlined' },
  consumed: { label: '已使用', color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  unknown: { label: '未知', color: 'grey-darken-1', icon: 'mdi-help-circle-outline', variant: 'outlined' },
}

const toneMap: Record<StatusTone, { color: string; icon: string; variant: 'tonal' | 'outlined' }> = {
  success: { color: 'success', icon: 'mdi-check-circle-outline', variant: 'tonal' },
  warning: { color: 'warning', icon: 'mdi-alert-outline', variant: 'tonal' },
  error: { color: 'error', icon: 'mdi-alert-circle-outline', variant: 'tonal' },
  info: { color: 'info', icon: 'mdi-information-outline', variant: 'tonal' },
  neutral: { color: 'grey-darken-1', icon: 'mdi-circle-outline', variant: 'outlined' },
}

const resolved = computed(() => {
  const key = String(props.status ?? 'unknown').toLowerCase().replaceAll('-', '_')
  const mapped = statusMap[key] ?? {
    ...statusMap.unknown!,
    label: String(props.status ?? '未知'),
  }
  if (!props.tone) return mapped
  return { ...mapped, ...toneMap[props.tone] }
})
</script>
