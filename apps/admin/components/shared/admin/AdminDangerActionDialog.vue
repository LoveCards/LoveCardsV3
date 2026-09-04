<template>
  <v-dialog
    v-model="dialogOpen"
    :aria-label="title"
    :persistent="loading"
    max-width="560"
    @after-leave="reset"
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
        <v-text-field
          v-if="confirmText"
          v-model="confirmation"
          :disabled="loading"
          :hint="`请输入“${confirmText}”继续`"
          label="确认文字"
          persistent-hint
          variant="outlined"
        />
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
  confirmText?: string
  loading?: boolean
  error?: string
  color?: string
}>(), {
  actionLabel: '确认执行',
  resourceLabel: '',
  confirmText: '',
  loading: false,
  error: '',
  color: 'error',
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
}>()

const confirmation = ref('')

const dialogOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
const canConfirm = computed(() => {
  if (props.loading) return false
  if (props.confirmText && confirmation.value !== props.confirmText) return false
  return true
})

const confirm = () => {
  if (!canConfirm.value) return
  emit('confirm')
}

const reset = () => {
  confirmation.value = ''
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
