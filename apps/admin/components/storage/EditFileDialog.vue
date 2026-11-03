<template>
  <v-dialog v-model="thisDialogState" aria-label="管理文件" max-width="720" :persistent="saving">
    <v-card prepend-icon="mdi-file-cog-outline" title="管理文件">
      <v-card-text class="pt-4">
        <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ formError }}
        </v-alert>

        <v-list bg-color="surface-variant" class="mb-4" density="compact" rounded="lg">
          <v-list-item prepend-icon="mdi-file-outline" subtitle="文件名" :title="displayValue(FileData.edit.original_name)" />
          <v-divider />
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-database-outline" subtitle="文件大小" :title="formatSize(FileData.edit.file_size)" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-folder-outline" subtitle="使用场景" :title="displayValue(FileData.edit.scene)" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-cloud-outline" subtitle="存储渠道" :title="displayValue(FileData.edit.channel_slug)" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-list-item prepend-icon="mdi-account-outline" subtitle="上传者" :title="FileData.edit.user_id ? `#${FileData.edit.user_id}` : '—'" />
            </v-col>
            <v-col cols="12">
              <v-list-item prepend-icon="mdi-clock-outline" subtitle="创建时间" :title="displayValue(FileData.edit.created_at)" />
            </v-col>
          </v-row>
        </v-list>

        <v-alert class="mb-4" type="info" variant="tonal">
          状态变更会影响文件能否在前台访问，请在保存前确认可见性影响。
        </v-alert>

        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="editStatus"
              :disabled="saving"
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="SelectUtils.Files.status"
              label="审核状态"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="editIsPublic"
              :disabled="saving"
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="publicOptions"
              label="公开状态"
              variant="outlined"
            />
          </v-col>
        </v-row>

      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="saving" variant="text" @click="thisDialogState = false">取消</v-btn>
        <v-btn color="primary" :loading="saving" variant="flat" @click="submit">保存状态</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { LCFile } from '@lovecards/sdk'
import { isApiError, useApi } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import SelectUtils from '~/utils/select'

const client = useApi()
const notifier = useAdminNotifier()
const props = defineProps<{ getTableData?: () => unknown }>()
const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const FileData = defineModel<{ edit: LCFile; origin: LCFile }>('FileData', {
  default: { edit: {} as LCFile, origin: {} as LCFile },
})
const editStatus = ref(0)
const editIsPublic = ref(0)
const saving = ref(false)
const formError = ref('')

const publicOptions = [
  { title: '私有', value: 0 },
  { title: '公开', value: 1 },
]
const displayValue = (value: unknown) => value === undefined || value === null || value === '' ? '—' : String(value)
const formatSize = (bytes: number) => {
  if (!bytes) return '0 B'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
const errorMessage = (cause: unknown, fallback: string) => {
  return isApiError(cause) ? cause.message : fallback
}

const submit = async () => {
  if (saving.value) return
  const ids = [FileData.value.edit.id]
  const statusChanged = editStatus.value !== FileData.value.origin.status
  const publicChanged = editIsPublic.value !== FileData.value.origin.is_public
  if (!statusChanged && !publicChanged) {
    notifier.warning('请修改后再提交')
    return
  }
  saving.value = true
  formError.value = ''
  const completed: string[] = []
  try {
    // The API exposes these as two independent commands. Run them in a stable
    // order and report partial success truthfully instead of presenting the
    // pair as an atomic save.
    if (statusChanged) {
      await client.files.batchManaged({
        ids,
        method: editStatus.value === 1 ? 'ban' : 'approve',
      })
      FileData.value.origin.status = editStatus.value
      completed.push('审核状态')
    }
    if (publicChanged) {
      await client.files.batchManaged({ ids, method: 'toggle_public' })
      FileData.value.origin.is_public = editIsPublic.value
      completed.push('公开状态')
    }
    thisDialogState.value = false
    await props.getTableData?.()
    notifier.success('文件状态已保存')
  } catch (cause) {
    const detail = errorMessage(cause, '请求失败，请稍后重试。')
    formError.value = completed.length
      ? `${completed.join('、')}已保存，剩余修改未保存：${detail}`
      : `文件状态保存失败：${detail}`
    try { await props.getTableData?.() } catch { /* preserve the mutation error */ }
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (!open || !FileData.value?.edit) return
  formError.value = ''
  editStatus.value = FileData.value.edit.status
  editIsPublic.value = FileData.value.edit.is_public
})
</script>
