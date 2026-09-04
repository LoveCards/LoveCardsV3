<template>
  <nav
    v-if="items.length"
    class="admin-breadcrumbs"
    :aria-label="ariaLabel"
  >
    <v-breadcrumbs
      :items="normalizedItems"
      class="admin-breadcrumbs__list"
      density="compact"
      divider=""
    >
      <template #divider>
        <v-icon
          aria-hidden="true"
          class="admin-breadcrumbs__divider"
          icon="mdi-chevron-right"
          size="16"
        />
      </template>
      <template #item="{ item, index }">
        <v-breadcrumbs-item
          :active="getBreadcrumbItem(item, index).active"
          :aria-label="getBreadcrumbItem(item, index).ariaLabel || getBreadcrumbItem(item, index).title"
          :class="{ 'admin-breadcrumbs__current': getBreadcrumbItem(item, index).active }"
          :disabled="getBreadcrumbItem(item, index).disabled"
          :exact="getBreadcrumbItem(item, index).exact"
          :href="getBreadcrumbItem(item, index).href"
          :replace="getBreadcrumbItem(item, index).replace"
          rounded="sm"
          :title="getBreadcrumbItem(item, index).title"
          :to="getBreadcrumbItem(item, index).to"
        >
          <slot name="item" :index="index" :item="getBreadcrumbItem(item, index)">
            {{ getBreadcrumbItem(item, index).title }}
          </slot>
        </v-breadcrumbs-item>
      </template>
    </v-breadcrumbs>
  </nav>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

/** A route-aware item used by the shared admin page hierarchy. */
export interface AdminBreadcrumbItem {
  title: string
  to?: RouteLocationRaw
  href?: string
  disabled?: boolean
  active?: boolean
  exact?: boolean
  replace?: boolean
  ariaLabel?: string
}

const props = withDefaults(defineProps<{
  items?: AdminBreadcrumbItem[]
  ariaLabel?: string
}>(), {
  items: () => [],
  ariaLabel: '页面路径',
})

/**
 * The last crumb is the current page unless callers explicitly provide an
 * active/disabled value. Keeping it active gives it an aria-current="page"
 * marker even when it has no `to` route (the usual detail-page case).
 */
const normalizedItems = computed(() => props.items.map((item, index, all) => ({
  ...item,
  active: item.active ?? index === all.length - 1,
  disabled: item.disabled ?? index === all.length - 1,
})))

// Vuetify exposes its own internal breadcrumb item type to the `item` slot.
// The runtime object is the normalized item above, so keep the cast in one
// place instead of leaking framework-specific types into callers.
function getBreadcrumbItem(item: unknown, index: number): AdminBreadcrumbItem {
  return normalizedItems.value[index] ?? item as AdminBreadcrumbItem
}
</script>

<style scoped>
.admin-breadcrumbs {
  min-width: 0;
}

.admin-breadcrumbs__list {
  min-height: 28px;
  padding: 0;
  color: rgb(var(--v-theme-on-surface-variant));
}

.admin-breadcrumbs__divider {
  opacity: 0.72;
}

.admin-breadcrumbs :deep(.v-breadcrumbs-item) {
  min-height: 44px;
  padding: 2px 0;
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.875rem;
  line-height: 1.35;
}

.admin-breadcrumbs :deep(.v-breadcrumbs-item--link) {
  color: rgb(var(--v-theme-primary));
  text-decoration: none;
  transition: color 150ms ease, opacity 150ms ease;
}

.admin-breadcrumbs :deep(.v-breadcrumbs-item--link:hover) {
  color: rgb(var(--v-theme-primary-darken-1));
  text-decoration: underline;
  text-underline-offset: 2px;
}

.admin-breadcrumbs :deep(.v-breadcrumbs-item--link:focus-visible) {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 3px;
}

.admin-breadcrumbs :deep(.v-breadcrumbs-item--disabled) {
  color: rgb(var(--v-theme-on-surface)) !important;
  opacity: 0.72;
}

.admin-breadcrumbs__current {
  font-weight: 500;
}

@media (prefers-reduced-motion: reduce) {
  .admin-breadcrumbs :deep(.v-breadcrumbs-item--link) {
    transition: none;
  }
}
</style>
