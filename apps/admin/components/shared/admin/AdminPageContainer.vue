<template>
  <v-container
    :class="[
      'admin-page-container',
      { 'admin-page-container--flush': flush },
    ]"
    :fluid="fluid"
    :max-width="resolvedMaxWidth"
    :tag="tag"
  >
    <slot />
  </v-container>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  /** 内容最大宽度；`fluid` 页面默认使用应用壳提供的完整可用宽度。 */
  maxWidth?: string | number
  fluid?: boolean
  /** 兼容旧调用方；页面横向 gutter 统一由应用壳负责。 */
  flush?: boolean
  tag?: string
}>(), {
  fluid: false,
  flush: false,
  tag: 'div',
})

const resolvedMaxWidth = computed(() => (
  props.maxWidth ?? (props.fluid ? undefined : 1280)
))
</script>

<style scoped>
.admin-page-container {
  width: 100%;
  padding: 0 0 40px;
}

.admin-page-container.admin-page-container--flush {
  padding-inline: 0;
}

@media (max-width: 599.98px) {
  .admin-page-container {
    padding-bottom: 32px;
  }
}
</style>
