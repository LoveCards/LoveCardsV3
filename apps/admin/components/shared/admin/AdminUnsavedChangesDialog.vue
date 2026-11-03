<template>
  <v-dialog :model-value="modelValue" aria-labelledby="admin-unsaved-changes-title" max-width="520" persistent>
    <v-card>
      <v-card-title id="admin-unsaved-changes-title" class="d-flex align-center ga-2 text-h6">
        <v-icon color="warning">mdi-content-save-alert-outline</v-icon>
        有未保存的修改
      </v-card-title>
      <v-divider />
      <v-card-text class="pt-5">
        <p class="mb-2">离开后，本页尚未保存的配置将丢失。</p>
        <p class="text-body-2 text-medium-emphasis mb-0">你可以继续编辑并保存，或者确认放弃本次修改。</p>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn min-height="44" variant="text" @click="$emit('stay')">继续编辑</v-btn>
        <v-btn color="error" min-height="44" variant="flat" @click="$emit('discard')">放弃修改</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
defineProps<{ modelValue: boolean }>()

defineEmits<{
  stay: []
  discard: []
}>()
</script>
