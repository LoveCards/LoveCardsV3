<template>
  <v-dialog v-model="thisDialogState" aria-label="创建标签" max-width="560" :persistent="saving">
    <v-card prepend-icon="mdi-tag-plus-outline" title="创建标签">
      <v-card-text class="pt-4">
        <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ formError }}
        </v-alert>
        <v-text-field
          v-model="tagName"
          autofocus
          :disabled="saving"
          density="comfortable"
          hint="标签会创建在当前应用中，名称应简短且能准确概括内容。"
          label="标签名称"
          maxlength="50"
          persistent-hint
          required
          variant="outlined"
          @keyup.enter="submit"
        />
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="saving" variant="text" @click="thisDialogState = false">取消</v-btn>
        <v-btn color="primary" :disabled="!canSubmit" :loading="saving" variant="flat" @click="submit">
          创建标签
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { isApiError, useApi } from '~/lib/api'

const client = useApi()
const props = defineProps<{ getTableData?: () => unknown }>()
const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const tagName = ref('')
const saving = ref(false)
const formError = ref('')
const canSubmit = computed(() => !saving.value && Boolean(tagName.value.trim()))

const submit = async () => {
  if (!canSubmit.value) return
  saving.value = true
  formError.value = ''
  try {
    await client.tags.create({ name: tagName.value.trim() })
    thisDialogState.value = false
    tagName.value = ''
    await props.getTableData?.()
  } catch (cause) {
    formError.value = isApiError(cause) ? cause.message : '标签创建失败，请检查名称后重试。'
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (open) formError.value = ''
})
</script>
