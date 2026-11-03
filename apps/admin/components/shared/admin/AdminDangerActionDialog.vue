<template>
  <v-dialog
    v-model="dialogOpen"
    :aria-label="title"
    :persistent="loading"
    max-width="560"
  >
    <v-card>
      <v-card-title class="d-flex align-center ga-2 text-h6">
        <v-icon :color="color">mdi-alert-outline</v-icon>
        {{ title }}
      </v-card-title>
      <v-divider />
      <v-card-text class="pt-5">
        <p class="mb-3">{{ impact }}</p>
        <dl v-if="resourceLabel" class="admin-danger-dialog__summary mb-4">
          <dt>目标资源</dt>
          <dd>{{ resourceLabel }}</dd>
        </dl>
        <v-alert v-if="error" :text="error" class="mt-4" role="alert" type="error" variant="tonal" />
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="loading" min-height="44" variant="text" @click="dialogOpen = false">
          取消
        </v-btn>
        <v-btn
          :disabled="!canConfirm"
          :loading="loading"
          :color="color"
          data-testid="danger-confirm"
          min-height="44"
          variant="flat"
          @click="confirm"
        >
          {{ actionLabel }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  actionLabel?: string
  resourceLabel?: string
  impact: string
  loading?: boolean
  error?: string
  color?: string
}>(), {
  actionLabel: '确认执行',
  resourceLabel: '',
  loading: false,
  error: '',
  color: 'error',
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
}>()

const dialogOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
// 规范 D-1：危险确认统一为“影响说明 + 明确动作按钮”，不提供文字输入确认（防误触靠文案说清后果，见 D-2）。
const canConfirm = computed(() => !props.loading)

const confirm = () => {
  if (!canConfirm.value) return
  emit('confirm')
}
</script>

<style scoped>
.admin-danger-dialog__summary {
  display: grid;
  grid-template-columns: max-content minmax(0, 1fr);
  gap: 8px 16px;
  padding: 12px 16px;
  border-radius: 8px;
  background: rgb(var(--v-theme-surface-variant));
}

.admin-danger-dialog__summary dt {
  font-weight: 600;
}

.admin-danger-dialog__summary dd {
  margin: 0;
  overflow-wrap: anywhere;
}
</style>
