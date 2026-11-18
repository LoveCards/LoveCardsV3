<template>
  <NuxtLink v-if="to" :to="to" class="admin-table-row-action"><slot /></NuxtLink>
  <v-btn
    v-else
    variant="text"
    class="admin-table-row-action"
    :disabled="disabled"
    :loading="loading"
    @click="emit('click', $event)"
  >
    <slot />
  </v-btn>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

defineProps<{
  to?: RouteLocationRaw
  disabled?: boolean
  loading?: boolean
}>()

const emit = defineEmits<{ click: [event: MouseEvent] }>()
</script>

<style scoped>
.admin-table-row-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  min-height: 32px;
  padding: 4px 6px;
  border: 0;
  border-radius: 4px;
  background: transparent;
  color: rgb(var(--v-theme-primary));
  font: inherit;
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.25;
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
}

.admin-table-row-action.v-btn {
  min-width: 0;
  height: 32px;
  padding-inline: 6px;
  letter-spacing: normal;
  text-transform: none;
}

.admin-table-row-action:hover {
  text-decoration: underline;
  text-underline-offset: 3px;
}

.admin-table-row-action:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.admin-table-row-action:disabled,
.admin-table-row-action.v-btn--disabled {
  color: rgba(var(--v-theme-on-surface), 0.38);
  cursor: not-allowed;
  text-decoration: none;
}

:global(.admin-table-row-more.v-btn) {
  color: rgba(var(--v-theme-on-surface), 0.65);
}

@media (max-width: 599.98px) {
  .admin-table-row-action {
    min-width: 44px;
    min-height: 44px;
  }

  .admin-table-row-action.v-btn {
    height: 44px;
    min-width: 44px;
  }

  :global(.admin-table-row-more.v-btn) {
    min-width: 44px;
    min-height: 44px;
  }
}
</style>
