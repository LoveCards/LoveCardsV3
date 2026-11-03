<template>
  <slot v-if="allowed" />
  <slot v-else name="fallback" />
</template>

<script setup lang="ts">
import type { Grant } from '@lovecards/sdk'
import { useUserStore } from '~/stores/userStore'

export interface AdminGrantRequirement {
  capability: string
  scope: Grant['scope']
}

const props = withDefaults(defineProps<{
  capability?: string
  scope?: Grant['scope']
  allOf?: AdminGrantRequirement[]
}>(), {
  capability: '',
  scope: 'global',
  allOf: () => [],
})

const userStore = useUserStore()
const allowed = computed(() => {
  const requirements = props.allOf.length
    ? props.allOf
    : props.capability
      ? [{ capability: props.capability, scope: props.scope }]
      : []
  return requirements.length === 0 || requirements.every((item) => userStore.hasGrant(item.capability, item.scope))
})
</script>
