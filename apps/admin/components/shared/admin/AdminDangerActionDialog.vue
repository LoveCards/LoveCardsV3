<template>
  <v-dialog
    v-model="dialogOpen"
    :persistent="loading"
    max-width="560"
    @after-leave="reset"
  >
    <v-card>
      <v-card-title class="d-flex align-center ga-2 text-h6">
        <v-icon color="error">mdi-alert-outline</v-icon>
        {{ title }}
      </v-card-title>
      <v-divider />
      <v-card-text class="pt-5">
        <p class="mb-3">{{ impact }}</p>
        <dl v-if="resourceLabel" class="admin-danger-dialog__summary mb-4">
          <dt>目标资源</dt>
          <dd>{{ resourceLabel }}</dd>
        </dl>
        <v-alert
          :text="auditHint"
          class="mb-4"
          density="compact"
          type="info"
          variant="tonal"
        />
        <v-textarea
          v-if="requireReason"
          v-model="reason"
          :disabled="loading"
          :error-messages="reasonTouched && !normalizedReason ? ['请输入操作原因'] : []"
          autofocus
          label="操作原因"
          maxlength="500"
          rows="3"
          variant="outlined"
          @blur="reasonTouched = true"
        />
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
          color="error"
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
  auditHint?: string
  requireReason?: boolean
  confirmText?: string
  loading?: boolean
  error?: string
}>(), {
  actionLabel: '确认执行',
  resourceLabel: '',
  auditHint: '本次操作将记录操作者、原因、目标资源和请求标识。',
  requireReason: true,
  confirmText: '',
  loading: false,
  error: '',
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: [payload: { reason: string }]
}>()

const reason = ref('')
const confirmation = ref('')
const reasonTouched = ref(false)

const dialogOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
const normalizedReason = computed(() => reason.value.trim())
const canConfirm = computed(() => {
  if (props.loading) return false
  if (props.requireReason && !normalizedReason.value) return false
  if (props.confirmText && confirmation.value !== props.confirmText) return false
  return true
})

const confirm = () => {
  reasonTouched.value = true
  if (!canConfirm.value) return
  emit('confirm', { reason: normalizedReason.value })
}

const reset = () => {
  reason.value = ''
  confirmation.value = ''
  reasonTouched.value = false
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
