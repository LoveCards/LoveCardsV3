<template>
  <section :aria-labelledby="headingId" class="admin-config-section">
    <v-sheet border rounded="lg" overflow-hidden>
      <div class="admin-config-section__header">
        <div class="d-flex align-start ga-3 min-w-0">
          <v-icon v-if="icon" :color="risk === 'high' ? 'warning' : 'primary'" :icon="icon" class="mt-1" />
          <div class="min-w-0">
            <div class="d-flex flex-wrap align-center ga-2">
              <h2 :id="headingId" class="text-h6">{{ title }}</h2>
              <v-chip v-if="dirty" color="warning" size="small" variant="tonal">未保存</v-chip>
              <v-chip v-if="risk === 'high'" color="warning" size="small" variant="outlined">高风险</v-chip>
            </div>
            <p v-if="description" class="mt-1 mb-0 text-body-2 text-medium-emphasis">
              {{ description }}
            </p>
          </div>
        </div>
        <div v-if="$slots.actions" class="admin-config-section__actions">
          <slot name="actions" />
        </div>
      </div>
      <v-divider />
      <div class="admin-config-section__body">
        <slot />
      </div>
      <template v-if="$slots.footer">
        <v-divider />
        <div class="admin-config-section__footer">
          <slot name="footer" />
        </div>
      </template>
    </v-sheet>
  </section>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  title: string
  description?: string
  icon?: string
  dirty?: boolean
  risk?: 'normal' | 'high'
}>(), {
  description: '',
  icon: '',
  dirty: false,
  risk: 'normal',
})

const headingId = computed(() => `admin-config-${props.title.toLocaleLowerCase().replace(/[^\p{L}\p{N}]+/gu, '-')}`)
</script>

<style scoped>
.admin-config-section__header,
.admin-config-section__footer {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding: 20px;
}

.admin-config-section__body {
  padding: 20px;
}

.admin-config-section__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.admin-config-section__actions :deep(.v-btn),
.admin-config-section__footer :deep(.v-btn) {
  min-height: 44px;
}

@media (max-width: 700px) {
  .admin-config-section__header,
  .admin-config-section__footer {
    flex-direction: column;
  }

  .admin-config-section__actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
