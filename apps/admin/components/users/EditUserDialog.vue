<template>
  <v-dialog v-model="thisDialogState" aria-label="编辑用户" max-width="720" :persistent="saving || avatarUploading">
    <v-card prepend-icon="mdi-account-edit-outline" title="编辑用户">
      <v-card-text class="pt-4">
        <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ formError }}
        </v-alert>

        <v-row dense>
          <v-col cols="12" md="4">
            <v-sheet class="d-flex flex-column align-center pa-4" color="surface-variant" rounded="lg">
              <v-avatar color="surface" size="112">
                <v-img v-if="editUserData.edit.avatar" :src="editUserData.edit.avatar" cover />
                <v-icon v-else color="medium-emphasis" size="48">mdi-account</v-icon>
              </v-avatar>
              <p class="mb-0 mt-3 text-body-2 text-medium-emphasis">当前头像</p>
            </v-sheet>
          </v-col>

          <v-col cols="12" md="8">
            <v-file-input
              v-if="canUploadFiles"
              accept="image/*"
              :disabled="saving"
              density="comfortable"
              :error-messages="avatarUploadError ? [avatarUploadError] : []"
              label="更换头像"
              :loading="avatarUploading"
              :model-value="selectedAvatar"
              prepend-icon="mdi-camera-outline"
              show-size
              variant="outlined"
              @update:model-value="uploadAvatar"
            />
            <v-alert v-else class="mb-4" density="compact" type="info" variant="tonal">
              当前账号没有上传文件权限，用户头像将保持不变。
            </v-alert>
            <v-select
              v-model="editUserData.edit.status"
              :disabled="saving"
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="accountStatusOptions"
              label="账号状态"
              variant="outlined"
            />
            <v-select
              v-if="canManageRoles"
              v-model="rolesId"
              chips
              clearable
              :disabled="saving"
              density="comfortable"
              :error-messages="rolesError ? [rolesError] : []"
              item-title="title"
              item-value="value"
              :items="userRolesOptions"
              label="权限组"
              :loading="rolesLoading"
              multiple
              variant="outlined"
            />
            <v-alert v-else density="compact" type="info" variant="tonal">
              当前账号缺少角色读取或分配权限，用户现有角色将保持不变。
            </v-alert>
          </v-col>

          <v-col cols="12" sm="6">
            <v-text-field
              :model-value="editUserData.edit.number ?? '—'"
              density="comfortable"
              label="账号"
              readonly
              variant="plain"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="editUserData.edit.username"
              :disabled="saving"
              density="comfortable"
              label="用户名"
              required
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="editUserData.edit.email"
              :disabled="saving"
              density="comfortable"
              label="邮箱"
              type="email"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="editUserData.edit.phone"
              :disabled="saving"
              density="comfortable"
              label="手机号"
              type="tel"
              variant="outlined"
            />
          </v-col>
        </v-row>

      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="saving || avatarUploading" variant="text" @click="thisDialogState = false">
          取消
        </v-btn>
        <v-btn
          color="primary"
          :disabled="avatarUploading || rolesLoading"
          :loading="saving"
          variant="flat"
          @click="submitEditUser"
        >
          保存修改
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { UploadResult, User } from '@lovecards/sdk'
import { isApiError, useApi } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'

const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()

const props = defineProps<{
  ACCOUNT_STATUS?: unknown[]
  getTableData?: () => unknown
}>()

interface EditUserData {
  edit: Omit<Partial<User>, 'roles_id'> & { roles_id?: number[] | string }
  origin: Omit<Partial<User>, 'roles_id'> & { roles_id?: number[] | string }
}

const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const editUserData = defineModel<EditUserData>('editUserData', {
  default: { edit: {}, origin: {} },
})
const accountStatusOptions = computed(() => props.ACCOUNT_STATUS ?? [])
const userRolesOptions = ref<Array<{ title: string; value: number }>>([])
const rolesLoading = ref(false)
const rolesError = ref('')
const saving = ref(false)
const formError = ref('')
const avatarUploading = ref(false)
const avatarUploadError = ref('')
const selectedAvatar = ref<File | File[] | null>(null)
const canAssignRoles = computed(() => userStore.hasGrant('users.roles.assign', 'global'))
const canReadRoles = computed(() => userStore.hasGrant('roles.read', 'global'))
const canManageRoles = computed(() => canAssignRoles.value && canReadRoles.value)
const canUploadFiles = computed(() => userStore.hasGrant('files.upload', 'global'))

class RoleSaveError extends Error {
  constructor(message: string, readonly profileSaved: boolean) {
    super(message)
    this.name = 'RoleSaveError'
  }
}

const parseRoleIds = (raw: unknown): number[] => {
  let value = raw
  if (typeof value === 'string') {
    if (value.trim() === '') return []
    try {
      value = JSON.parse(value)
    } catch {
      return []
    }
  }
  if (typeof value === 'number') value = [value]
  if (!Array.isArray(value)) return []
  return value.filter((item): item is number => typeof item === 'number' && Number.isSafeInteger(item) && item > 0)
}

const rolesId = computed<number[]>({
  get: () => parseRoleIds(editUserData.value.edit.roles_id),
  set: (value) => {
    editUserData.value.edit.roles_id = value
  },
})

const errorMessage = (cause: unknown, fallback: string) => {
  return isApiError(cause) ? cause.message : fallback
}

const loadRoles = async () => {
  if (!canManageRoles.value) return
  rolesLoading.value = true
  rolesError.value = ''
  try {
    const result = await client.roles.list({ list_rows: 100 })
    userRolesOptions.value = (result.data || []).map((role) => ({
      title: `#${role.id} ${role.name}`,
      value: role.id,
    }))
  } catch (cause) {
    rolesError.value = errorMessage(cause, '无法加载权限组，请稍后重试。')
  } finally {
    rolesLoading.value = false
  }
}

const uploadAvatar = async (value: File | File[] | null) => {
  if (!canUploadFiles.value) return
  selectedAvatar.value = value
  const file = Array.isArray(value) ? value[0] : value
  if (!file) return

  avatarUploading.value = true
  avatarUploadError.value = ''
  try {
    const formData = new FormData()
    formData.append('file', file)
    const result: UploadResult = await client.files.upload(formData)
    editUserData.value.edit.avatar = result.url
    selectedAvatar.value = null
  } catch (cause) {
    avatarUploadError.value = errorMessage(cause, '头像上传失败，请重新选择文件。')
  } finally {
    avatarUploading.value = false
  }
}

const patchUser = async (data: EditUserData) => {
  const params = CommonUtils.removeCommonProperties(data.edit, data.origin)
  // 账号编号是服务端归属字段，管理端始终只读且绝不进入通用更新参数。
  delete params.number
  const roleIds = params.roles_id
  delete params.roles_id
  const profileChanged = Object.keys(params).length > 0
  const rolesChanged = roleIds !== undefined && canManageRoles.value
  if (!profileChanged && !rolesChanged) {
    notifier.warning('请修改后再提交')
    return false
  }
  let profileSaved = false
  if (profileChanged) {
    await client.users.updateManaged(
      data.origin.id!,
      params as Parameters<typeof client.users.updateManaged>[1],
    )
    Object.assign(data.origin, CommonUtils.deepClone(params))
    profileSaved = true
  }

  if (rolesChanged) {
    const parsedRoles = parseRoleIds(roleIds)
    try {
      await client.users.assignRoles(data.origin.id!, { roles_id: parsedRoles })
      data.origin.roles_id = CommonUtils.deepClone(parsedRoles)
    } catch (cause) {
      const detail = errorMessage(cause, '请稍后重试。')
      const message = profileSaved
        ? `用户资料已保存，但角色未保存：${detail}`
        : `角色未保存：${detail}`
      throw new RoleSaveError(message, profileSaved)
    }
  }

  return true
}

const submitEditUser = async () => {
  if (saving.value) return
  saving.value = true
  formError.value = ''
  try {
    const changed = await patchUser(editUserData.value)
    if (!changed) return
    thisDialogState.value = false
    await props.getTableData?.()
  } catch (cause) {
    if (cause instanceof RoleSaveError) {
      formError.value = cause.message
      if (cause.profileSaved) {
        try {
          await props.getTableData?.()
        } catch {
          formError.value += ' 列表刷新失败，请手动刷新后核对资料。'
        }
      }
    } else {
      formError.value = errorMessage(cause, '用户保存失败，请检查字段后重试。')
    }
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (open) {
    formError.value = ''
    avatarUploadError.value = ''
    selectedAvatar.value = null
    if (canManageRoles.value) void loadRoles()
  }
})
</script>
