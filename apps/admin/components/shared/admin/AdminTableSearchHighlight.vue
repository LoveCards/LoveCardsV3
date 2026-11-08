<template>
  <template v-for="(part, index) in parts" :key="index">
    <mark v-if="part.match" class="admin-search-highlight">{{ part.text }}</mark><template v-else>{{ part.text }}</template>
  </template>
</template>

<script setup lang="ts">
const props = defineProps<{ text: string | number | null | undefined; query?: string }>()
const parts = computed(() => {
  const value = String(props.text ?? '—')
  const term = props.query?.trim()
  if (!term) return [{ text: value, match: false }]
  const lower = value.toLocaleLowerCase()
  const needle = term.toLocaleLowerCase()
  const result: { text: string; match: boolean }[] = []
  let start = 0
  while (start < value.length) {
    const index = lower.indexOf(needle, start)
    if (index < 0) break
    if (index > start) result.push({ text: value.slice(start, index), match: false })
    result.push({ text: value.slice(index, index + term.length), match: true })
    start = index + term.length
  }
  if (start < value.length) result.push({ text: value.slice(start), match: false })
  return result.length ? result : [{ text: value, match: false }]
})
</script>

<style scoped>
.admin-search-highlight {
  color: inherit;
  background-color: rgba(var(--v-theme-primary), 0.18);
  border-radius: 2px;
}
</style>
