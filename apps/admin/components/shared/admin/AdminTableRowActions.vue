<template>
  <div class="admin-table-row-actions">
    <template v-if="visibleActions.length">
      <AdminTableRowAction
        v-for="action in visibleActions"
        :key="action.key"
        :to="action.to"
        :disabled="action.disabled"
        :loading="action.loading"
        @click="action.onClick?.()"
      >
        {{ action.label }}
      </AdminTableRowAction>
      <v-menu v-if="hiddenActions.length" location="bottom end">
        <template #activator="{ props: menuProps }">
          <v-btn
            v-bind="menuProps"
            class="admin-table-row-more"
            :aria-label="moreLabel"
            :title="moreLabel"
            icon="mdi-dots-horizontal"
            size="small"
            variant="text"
          />
        </template>
        <v-list density="compact">
          <v-list-item
            v-for="action in hiddenActions"
            :key="action.key"
            :title="action.label"
            :to="action.to"
            :disabled="action.disabled"
            @click="runHiddenAction(action, $event)"
          />
        </v-list>
      </v-menu>
    </template>
    <span v-else class="text-medium-emphasis" aria-label="无可用操作">—</span>
  </div>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import AdminTableRowAction from './AdminTableRowAction.vue'

type AdminRowAction = {
  key: string
  label: string
  to?: RouteLocationRaw
  onClick?: () => void
  disabled?: boolean
  loading?: boolean
}

const props = withDefaults(defineProps<{
  actions: AdminRowAction[]
  capacity?: number
  moreLabel?: string
}>(), {
  capacity: 1,
  moreLabel: '更多操作',
})

const visibleActions = computed(() => props.actions.slice(0, Math.max(1, props.capacity)))
const hiddenActions = computed(() => props.actions.slice(Math.max(1, props.capacity)))

function runHiddenAction(action: AdminRowAction, event: Event) {
  // 菜单项会随菜单关闭而卸载；先聚焦稳定的行尾入口供后续弹窗回焦。
  const menuId = (event.currentTarget as HTMLElement).closest<HTMLElement>('.v-menu')?.id
  if (menuId) {
    const activator = [...document.querySelectorAll<HTMLElement>('[aria-controls]')]
      .find(element => element.getAttribute('aria-controls') === menuId)
    activator?.focus()
  }
  action.onClick?.()
}
</script>

<style scoped>
.admin-table-row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
  white-space: nowrap;
}
</style>
