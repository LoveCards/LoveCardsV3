<template>
  <header class="admin-page-header">
    <div class="admin-page-header__copy">
      <v-breadcrumbs
        v-if="breadcrumbs.length"
        :items="breadcrumbs"
        class="admin-page-header__breadcrumbs pa-0 mb-2"
        density="compact"
      />
      <h1 class="admin-page-header__title text-primary">{{ title }}</h1>
      <p v-if="description" class="admin-page-header__description text-medium-emphasis">
        {{ description }}
      </p>
    </div>
    <div v-if="$slots.actions" class="admin-page-header__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup lang="ts">
export interface AdminBreadcrumb {
  title: string
  to?: string
  disabled?: boolean
}

withDefaults(defineProps<{
  title: string
  description?: string
  breadcrumbs?: AdminBreadcrumb[]
}>(), {
  description: '',
  breadcrumbs: () => [],
})
</script>

<style scoped>
.admin-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  padding-block: 16px 24px;
}

.admin-page-header__copy {
  min-width: 0;
}

.admin-page-header__breadcrumbs {
  min-height: 32px;
}

.admin-page-header__title {
  margin: 0;
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.25;
  text-wrap: balance;
}

.admin-page-header__description {
  max-width: 72ch;
  margin: 8px 0 0;
  line-height: 1.5;
  text-wrap: pretty;
}

.admin-page-header__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 4px;
}

@media (max-width: 700px) {
  .admin-page-header {
    flex-direction: column;
    gap: 16px;
  }

  .admin-page-header__actions {
    width: 100%;
    justify-content: flex-start;
    padding-top: 0;
  }
}
</style>
