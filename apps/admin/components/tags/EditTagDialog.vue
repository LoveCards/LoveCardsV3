<template>
  <v-dialog v-model="thisDialogState" :aria-label="panelTitle" max-width="680" :persistent="saving">
    <v-card prepend-icon="mdi-tag-edit-outline" :title="panelTitle">
      <v-card-text class="pt-4">
        <v-progress-linear v-if="loading" class="mb-4" indeterminate />
        <v-alert v-if="loadError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ loadError }}
          <template #append><v-btn variant="outlined" @click="loadTag">重试</v-btn></template>
        </v-alert>
        <v-alert v-else-if="!canUpdateTag" class="mb-4" type="info" variant="tonal">
          当前账号只有查看权限，标签名称和状态保持只读。
        </v-alert>
        <v-alert v-if="formError && !loadError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ formError }}
        </v-alert>

        <v-list bg-color="surface-variant" class="mb-4" density="compact" rounded="lg">
          <v-row dense>
            <v-col cols="12" sm="4">
              <v-list-item prepend-icon="mdi-account-outline" subtitle="创建用户 ID" :title="displayValue(editTagData.edit.user_id)" />
            </v-col>
            <v-col cols="12" sm="4">
              <v-list-item prepend-icon="mdi-application-outline" subtitle="应用 ID" :title="displayValue(editTagData.edit.aid)" />
            </v-col>
            <v-col cols="12" sm="4">
              <v-list-item prepend-icon="mdi-clock-outline" subtitle="创建时间" :title="displayValue(editTagData.edit.created_at)" />
            </v-col>
          </v-row>
        </v-list>

        <v-row v-if="!loading && !loadError" dense>
          <v-col cols="12" sm="7">
            <v-text-field
              v-model="editTagData.edit.name"
              :disabled="saving || !canUpdateTag"
              density="comfortable"
              label="标签名称"
              maxlength="50"
              required
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="5">
            <v-select
              v-model="editTagData.edit.status"
              :disabled="saving || !canUpdateTag"
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="tagStatusItems"
              label="标签状态"
              variant="outlined"
            />
          </v-col>
        </v-row>

      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="saving" variant="text" @click="thisDialogState = false">{{ canUpdateTag ? '取消' : '关闭' }}</v-btn>
        <v-btn v-if="canUpdateTag" color="primary" :disabled="!canSubmit || loading || Boolean(loadError)" :loading="saving" variant="flat" @click="submit">
          保存修改
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { Tag } from '@lovecards/sdk'
import { isApiError, useApi } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import SelectUtils from '~/utils/select'

type UpdateTagInput = { name?: string; status?: number }

const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const canUpdateTag = computed(() => userStore.hasGrant('tags.update', 'all'))
const tagStatusItems = SelectUtils.Common.Status.map((option) => ({
  ...option,
  title: option.title.replace(/^#\d+\s*/, ''),
}))
const panelTitle = computed(() => canUpdateTag.value ? '编辑标签' : '查看标签')
const props = defineProps<{ getTableData?: () => unknown }>()
const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const TagData = defineModel<Tag | null>('TagData')
const emptyTag = {} as Tag
const editTagData = ref<{ edit: Tag; origin: Tag }>({ edit: emptyTag, origin: emptyTag })
const saving = ref(false)
const loading = ref(false)
const loadError = ref('')
const formError = ref('')
const canSubmit = computed(() => !saving.value && Boolean(editTagData.value.edit.name?.trim()))
const displayValue = (value: unknown) => value === undefined || value === null || value === '' ? '—' : String(value)

const loadTag = async () => {
  const id = Number(TagData.value?.id)
  if (!Number.isInteger(id) || id < 1) {
    loadError.value = '标签 ID 无效。'
    return
  }
  loading.value = true
  loadError.value = ''
  formError.value = ''
  try {
    const tag = await client.tags.getManaged(id)
    editTagData.value = {
      origin: CommonUtils.deepClone(tag),
      edit: CommonUtils.deepClone(tag),
    }
  } catch (cause) {
    loadError.value = isApiError(cause) ? cause.message : '无法加载标签详情，请稍后重试。'
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  if (!canSubmit.value || !editTagData.value.edit.id) return
  const editInput: UpdateTagInput = {
    name: editTagData.value.edit.name.trim(),
    status: editTagData.value.edit.status,
  }
  const originInput: UpdateTagInput = {
    name: editTagData.value.origin.name,
    status: editTagData.value.origin.status,
  }
  const params = CommonUtils.removeCommonProperties(editInput, originInput)
  if (Object.keys(params).length === 0) {
    notifier.warning('请修改后再提交')
    return
  }
  saving.value = true
  formError.value = ''
  try {
    await client.tags.updateManaged(editTagData.value.edit.id, params)
    thisDialogState.value = false
    await props.getTableData?.()
  } catch (cause) {
    formError.value = isApiError(cause) ? cause.message : '标签保存失败，请检查字段后重试。'
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (open) void loadTag()
})
</script>
