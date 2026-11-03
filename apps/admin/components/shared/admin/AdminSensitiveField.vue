<template>
  <div class="admin-sensitive-field">
    <div class="min-w-0">
      <div class="text-caption text-medium-emphasis">{{ label }}</div>
      <div class="admin-sensitive-field__value">{{ visible ? displayValue : maskedValue }}</div>
    </div>
    <v-btn
      v-if="revealable && value"
      :aria-label="visible ? `隐藏${label}` : `显示${label}`"
      :icon="visible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
      :title="visible ? `隐藏${label}` : `显示${label}`"
      density="comfortable"
      variant="text"
      @click="visible = !visible"
    />
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  label: string
  value?: string | number | null
  revealable?: boolean
}>(), {
  value: null,
  revealable: true,
})

const visible = ref(false)
const displayValue = computed(() => String(props.value ?? '未设置'))
const maskedValue = computed(() => {
  const value = String(props.value ?? '')
  if (!value) return '未设置'
  if (value.length <= 4) return '••••'
  return `${value.slice(0, 2)}${'•'.repeat(Math.min(value.length - 4, 8))}${value.slice(-2)}`
})
</script>

<style scoped>
.admin-sensitive-field {
  display: flex;
  min-height: 56px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-block: 8px;
}

.admin-sensitive-field__value {
  margin-top: 2px;
  overflow-wrap: anywhere;
}
</style>
