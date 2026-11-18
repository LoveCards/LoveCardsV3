<template>
  <time v-if="formatted"><AdminTableSearchHighlight :text="formatted" :query="query" /></time>
  <span v-else-if="raw"><AdminTableSearchHighlight :text="raw" :query="query" /> <span class="text-caption text-medium-emphasis">（时间格式未知）</span></span>
  <span v-else>—</span>
</template>

<script setup lang="ts">
import AdminTableSearchHighlight from './AdminTableSearchHighlight.vue'

const props = withDefaults(defineProps<{
  value?: string | null
  seconds?: boolean
  query?: string
}>(), { value: null, seconds: false, query: '' })

const raw = computed(() => props.value?.trim() ?? '')
const formatted = computed(() => {
  const match = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?$/.exec(raw.value)
  if (!match) return ''
  const [, year, month, day, hour, minute, second] = match
  const date = new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)))
  if (date.getUTCFullYear() !== Number(year) || date.getUTCMonth() + 1 !== Number(month)
    || date.getUTCDate() !== Number(day) || Number(hour) > 23 || Number(minute) > 59
    || (second !== undefined && Number(second) > 59)) return ''
  return `${year}-${month}-${day} ${hour}:${minute}${props.seconds && second !== undefined ? `:${second}` : ''}`
})
</script>
