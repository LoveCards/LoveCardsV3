<template>
  <v-dialog v-model="thisDialogState" aria-label="创建角色" max-width="720" :persistent="saving">
    <v-card prepend-icon="mdi-shield-plus-outline" title="创建角色">
      <v-card-text class="pt-4">
        <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ formError }}
        </v-alert>
        <v-alert class="mb-4" type="info" variant="tonal">
          创建角色后还需要分配权限；新角色在获得明确权限前不应承担管理职责。
        </v-alert>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="createData.name"
              :disabled="saving"
              density="comfortable"
              hint="1–50 个字符，支持中文、字母、数字、下划线和连字符"
              label="角色名称"
              maxlength="50"
              persistent-hint
              required
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="createData.slug"
              :disabled="saving"
              density="comfortable"
              hint="唯一标识，仅支持字母、数字、下划线和连字符"
              label="角色标识（Slug）"
              maxlength="50"
              persistent-hint
              required
              variant="outlined"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea
              v-model="createData.description"
              auto-grow
              :disabled="saving"
              density="comfortable"
              hint="说明该角色的职责范围，最多 255 个字符"
              label="角色描述"
              maxlength="255"
              persistent-hint
              rows="3"
              variant="outlined"
            />
          </v-col>
        </v-row>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="saving" variant="text" @click="thisDialogState = false">取消</v-btn>
        <v-btn color="primary" :disabled="!canSubmit" :loading="saving" variant="flat" @click="submit">
          创建角色
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
const createData = ref({ name: '', slug: '', description: '' })
const saving = ref(false)
const formError = ref('')
const canSubmit = computed(() => {
  return !saving.value && Boolean(createData.value.name.trim()) && Boolean(createData.value.slug.trim())
})

const submit = async () => {
  if (!canSubmit.value) return
  saving.value = true
  formError.value = ''
  try {
    await client.roles.create({
      name: createData.value.name.trim(),
      slug: createData.value.slug.trim(),
      description: createData.value.description.trim(),
    })
    thisDialogState.value = false
    createData.value = { name: '', slug: '', description: '' }
    await props.getTableData?.()
  } catch (cause) {
    formError.value = isApiError(cause) ? cause.message : '角色创建失败，请检查名称和标识后重试。'
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (open) {
    formError.value = ''
  }
})
</script>
