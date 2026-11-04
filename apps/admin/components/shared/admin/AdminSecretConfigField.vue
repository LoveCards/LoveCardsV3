<template>
  <div>
    <v-text-field
      autocomplete="new-password"
      :disabled="disabled"
      density="comfortable"
      :hint="hint"
      :label="label"
      :model-value="replacement"
      persistent-hint
      :placeholder="configured ? '输入新值以替换现有密钥' : '输入密钥'"
      :type="showReplacement ? 'text' : 'password'"
      variant="outlined"
      @update:model-value="updateReplacement"
    >
      <template #append-inner>
        <v-tooltip :text="showReplacement ? '隐藏新密钥' : '显示新密钥'" location="top">
          <template #activator="{ props: tooltipProps }">
            <v-btn
              v-bind="tooltipProps"
              :aria-label="showReplacement ? '隐藏新密钥' : '显示新密钥'"
              :disabled="disabled || replacement.length === 0"
              :height="44"
              :icon="showReplacement ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
              :min-width="44"
              variant="text"
              @click="showReplacement = !showReplacement"
            />
          </template>
        </v-tooltip>
      </template>
    </v-text-field>

    <div class="d-flex flex-wrap align-center ga-2 mt-n2 mb-3">
      <v-chip :color="stateColor" :prepend-icon="stateIcon" size="small" variant="tonal">
        {{ stateLabel }}
      </v-chip>
      <v-spacer />
      <v-btn
        v-if="modelValue.$secret !== 'keep'"
        :disabled="disabled"
        :min-height="44"
        prepend-icon="mdi-undo-variant"
        size="small"
        variant="text"
        @click="resetChange"
      >
        撤销密钥更改
      </v-btn>
      <v-btn
        v-else-if="configured"
        color="error"
        :disabled="disabled"
        :min-height="44"
        prepend-icon="mdi-key-remove"
        size="small"
        variant="text"
        @click="clearDialog = true"
      >
        清除密钥
      </v-btn>
    </div>

    <v-dialog v-model="clearDialog" aria-label="清除敏感配置" max-width="480">
      <v-card prepend-icon="mdi-key-remove" :title="`清除${label}`">
        <v-card-text class="pt-4">
          <v-alert type="warning" variant="tonal">
            清除后，依赖此密钥的渠道可能立即不可用。本操作先加入未保存修改，只有点击页面的保存按钮后才会生效。
          </v-alert>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn :disabled="disabled" variant="text" @click="clearDialog = false">取消</v-btn>
          <v-btn color="error" :disabled="disabled" variant="flat" @click="confirmClear">
            标记为清除
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import type { SecretConfigEnvelope } from '~/utils/configSecrets'

const props = withDefaults(defineProps<{
  disabled?: boolean
  label: string
  modelValue: SecretConfigEnvelope
}>(), {
  disabled: false,
})
const emit = defineEmits<{ 'update:modelValue': [value: SecretConfigEnvelope] }>()

const clearDialog = ref(false)
const showReplacement = ref(false)
const configured = computed(() => props.modelValue.configured)
const replacement = computed(() => props.modelValue.$secret === 'replace' ? props.modelValue.value : '')
const stateLabel = computed(() => {
  if (props.modelValue.$secret === 'replace') return '等待替换'
  if (props.modelValue.$secret === 'clear') return '等待清除'
  return configured.value ? '已配置（原值不回显）' : '尚未配置'
})
const stateColor = computed(() => {
  if (props.modelValue.$secret === 'replace') return 'info'
  if (props.modelValue.$secret === 'clear') return 'error'
  return configured.value ? 'success' : undefined
})
const stateIcon = computed(() => {
  if (props.modelValue.$secret === 'replace') return 'mdi-key-change'
  if (props.modelValue.$secret === 'clear') return 'mdi-key-remove'
  return configured.value ? 'mdi-key' : 'mdi-key-outline'
})
const hint = computed(() => {
  if (props.modelValue.$secret === 'replace') return '保存后会替换服务端密钥；当前输入不会再次回显。'
  if (props.modelValue.$secret === 'clear') return '保存后会清除服务端密钥，可在保存前撤销。'
  return configured.value ? '服务端已有密钥。留空会保持原值，不会清除。' : '密钥不会在读取接口中返回。'
})

const updateReplacement = (value: unknown) => {
  const next = typeof value === 'string' ? value : ''
  if (next === '') {
    emit('update:modelValue', { '$secret': 'keep', configured: configured.value })
    showReplacement.value = false
    return
  }
  emit('update:modelValue', { '$secret': 'replace', value: next, configured: configured.value })
}
const resetChange = () => {
  emit('update:modelValue', { '$secret': 'keep', configured: configured.value })
  showReplacement.value = false
}
const confirmClear = () => {
  emit('update:modelValue', { '$secret': 'clear', configured: configured.value })
  showReplacement.value = false
  clearDialog.value = false
}

watch(() => props.modelValue.$secret, (operation) => {
  if (operation !== 'replace') showReplacement.value = false
})
</script>
