<template>
  <v-dialog v-model="thisDialogState" aria-label="编辑角色" max-width="720" :persistent="saving">
    <v-card prepend-icon="mdi-shield-edit-outline" title="编辑角色">
      <v-card-text class="pt-4">
        <v-alert
          v-if="isSystemRole"
          class="mb-4"
          type="info"
          variant="tonal"
          title="系统角色受保护"
          text="可以调整名称和描述，但角色标识由系统固定，不能修改。"
        />
        <v-alert
          v-else-if="!isRoleClassificationKnown"
          class="mb-4"
          type="error"
          variant="tonal"
          title="无法确认角色类型"
          text="为避免越权修改，当前角色已锁定。请关闭对话框并刷新列表后重试。"
        />
        <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ formError }}
        </v-alert>
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="editData.edit.name"
              :disabled="saving || !canMutateTarget"
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
              v-model="editData.edit.slug"
              :disabled="saving || !canMutateTarget"
              density="comfortable"
              :hint="isSystemRole ? '系统角色标识由服务端固定' : '唯一标识，仅支持字母、数字、下划线和连字符'"
              label="角色标识（Slug）"
              maxlength="50"
              persistent-hint
              :readonly="isSystemRole"
              required
              variant="outlined"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea
              v-model="editData.edit.description"
              auto-grow
              :disabled="saving || !canMutateTarget"
              density="comfortable"
              label="角色描述"
              maxlength="255"
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
          保存修改
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { Role } from '@lovecards/sdk'
import { isApiError, useApi } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'

const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const props = defineProps<{ getTableData?: () => unknown }>()
const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const RoleData = defineModel<Role | null>('RoleData')
const emptyRole = {} as Role
const editData = ref<{ edit: Role; origin: Role }>({ edit: emptyRole, origin: emptyRole })
const saving = ref(false)
const formError = ref('')
const isSystemRole = computed(() => Number(editData.value.edit.is_system) === 1)
const isRoleClassificationKnown = computed(() => [0, 1].includes(Number(editData.value.edit.is_system)))
const isRootActor = computed(() => userStore.roles.some(role => role.slug === 'root'))
const canMutateTarget = computed(() => {
  return isRoleClassificationKnown.value
    && userStore.hasGrant('roles.update', 'global')
    && (!isSystemRole.value || isRootActor.value)
})
const canSubmit = computed(() => {
  return canMutateTarget.value
    && !saving.value
    && Boolean(editData.value.edit.name?.trim())
    && (isSystemRole.value || Boolean(editData.value.edit.slug?.trim()))
})

const submit = async () => {
  if (!editData.value.edit.id || saving.value) return
  if (!canMutateTarget.value) {
    formError.value = !isRoleClassificationKnown.value
      ? '无法确认目标角色类型，请刷新列表后重试。'
      : isSystemRole.value
      ? '只有超级管理员可以编辑系统角色。'
      : '当前账号无权编辑角色。'
    return
  }
  if (!canSubmit.value) return
  const current = editData.value
  const editInput = {
    name: current.edit.name.trim(),
    ...(isSystemRole.value ? {} : { slug: current.edit.slug.trim() }),
    description: current.edit.description?.trim() || undefined,
  }
  const originInput = {
    name: current.origin.name,
    ...(isSystemRole.value ? {} : { slug: current.origin.slug }),
    description: current.origin.description ?? undefined,
  }
  const params = CommonUtils.removeCommonProperties(editInput, originInput)
  if (Object.keys(params).length === 0) {
    notifier.warning('请修改后再提交')
    return
  }
  saving.value = true
  formError.value = ''
  try {
    await client.roles.update(current.edit.id, params)
    thisDialogState.value = false
    await props.getTableData?.()
  } catch (cause) {
    formError.value = isApiError(cause) ? cause.message : '角色保存失败，请检查字段后重试。'
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (!open || !RoleData.value) return
  formError.value = ''
  editData.value = {
    origin: CommonUtils.deepClone(RoleData.value),
    edit: CommonUtils.deepClone(RoleData.value),
  }
})
</script>
