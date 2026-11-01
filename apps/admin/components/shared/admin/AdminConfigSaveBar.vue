<template>
  <div class="d-flex flex-wrap align-center justify-space-between ga-3">
    <span class="text-body-2 text-medium-emphasis" role="status">
      {{ dirty ? `${label}有未保存修改` : '' }}
    </span>
    <div class="d-flex flex-wrap justify-end ga-2"><slot /></div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ dirty: boolean; label: string }>()
</script>
