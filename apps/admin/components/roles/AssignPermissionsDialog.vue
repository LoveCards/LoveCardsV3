<template>
  <v-dialog v-model="thisDialogState" :aria-label="dialogTitle" max-width="900" :persistent="saving" scrollable>
    <v-card prepend-icon="mdi-shield-key-outline" :title="dialogTitle">
      <v-card-text class="pt-4">
        <v-alert class="mb-4" type="info" variant="tonal">
          权限调整会影响该角色可见的页面、数据范围和可执行操作，请在保存前核对选择范围。
        </v-alert>
        <v-alert
          v-if="isSystemRole"
          class="mb-4"
          :type="canManageTarget ? 'warning' : 'error'"
          variant="tonal"
          title="系统角色权限"
          :text="canManageTarget
            ? '这会改变系统角色的管理边界，请确认所有权限范围后再保存。'
            : '只有超级管理员可以调整系统角色权限。'"
        />
        <v-alert
          v-else-if="!isRoleClassificationKnown"
          class="mb-4"
          type="error"
          variant="tonal"
          title="无法确认角色类型"
          text="为避免越权授权，当前角色已锁定。请关闭对话框并刷新列表后重试。"
        />
        <v-alert v-if="saveError" class="mb-4" role="alert" type="error" variant="tonal">
          {{ saveError }}
        </v-alert>

        <AdminAsyncState
          :empty="allPermissions.length === 0"
          empty-description="当前系统没有可分配的权限项。"
          empty-icon="mdi-shield-off-outline"
          empty-title="暂无权限项"
          :error="loadError"
          error-title="权限加载失败"
          :loading="loading"
          skeleton-type="list-item-two-line@6"
          @retry="loadPermissions"
        >
          <div class="mb-3 d-flex flex-wrap align-center justify-space-between ga-3">
            <p class="mb-0 text-body-2 text-medium-emphasis">
              已选择 {{ selectedGrantKeys.length }} / {{ allPermissions.length }} 项
            </p>
            <v-btn
              color="primary"
              :disabled="saving || !canManageTarget"
              prepend-icon="mdi-select-all"
              size="small"
              variant="tonal"
              @click="toggleSelectAll"
            >
              {{ isAllSelected ? '取消全选' : '全选' }}
            </v-btn>
          </div>
          <v-divider class="mb-3" />

          <v-list bg-color="transparent" lines="two">
            <template v-for="(group, resource) in groupedPermissions" :key="resource">
              <v-list-subheader class="font-weight-bold text-primary">{{ resource }}</v-list-subheader>
              <v-list-item v-for="permission in group" :key="grantKey(permission)" class="px-0">
                <v-checkbox
                  v-model="selectedGrantKeys"
                  color="primary"
                  :disabled="saving || !canManageTarget"
                  density="comfortable"
                  hide-details
                  :label="permission.description || permission.capability"
                  :value="grantKey(permission)"
                />
                <template #subtitle>
                  <code>{{ permission.capability }}:{{ permission.scope }}</code>
                </template>
              </v-list-item>
              <v-divider />
            </template>
          </v-list>
        </AdminAsyncState>

      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="saving" variant="text" @click="thisDialogState = false">取消</v-btn>
        <v-btn
          color="primary"
          :disabled="loading || Boolean(loadError) || !canManageTarget"
          :loading="saving"
          variant="flat"
          @click="submit"
        >
          保存权限
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { CapabilityItem, Grant, Role } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import { isApiError, useApi } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'

const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const props = defineProps<{ getTableData?: () => unknown }>()
const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const RoleData = defineModel<Role | null>('RoleData')
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const saveError = ref('')
const allPermissions = ref<CapabilityItem[]>([])
const selectedGrantKeys = ref<string[]>([])
const grantKey = (grant: Pick<Grant, 'capability' | 'scope'>) => `${grant.capability}:${grant.scope}`
const isSystemRole = computed(() => Number(RoleData.value?.is_system) === 1)
const isRoleClassificationKnown = computed(() => [0, 1].includes(Number(RoleData.value?.is_system)))
const isRootActor = computed(() => userStore.roles.some(role => role.slug === 'root'))
const canManageTarget = computed(() => {
  return isRoleClassificationKnown.value
    && userStore.hasGrant('roles.assign', 'global')
    && userStore.hasGrant('permissions.read', 'global')
    && (!isSystemRole.value || isRootActor.value)
})
const dialogTitle = computed(() => RoleData.value?.name ? `分配权限 · ${RoleData.value.name}` : '分配权限')

const groupedPermissions = computed(() => {
  const groups: Record<string, CapabilityItem[]> = {}
  for (const permission of allPermissions.value) {
    const prefix = permission.capability.split('.')[0] || '其他'
    if (!groups[prefix]) groups[prefix] = []
    groups[prefix].push(permission)
  }
  return groups
})
const isAllSelected = computed(() => {
  return allPermissions.value.length > 0
    && allPermissions.value.every(permission => selectedGrantKeys.value.includes(grantKey(permission)))
})

const toggleSelectAll = () => {
  selectedGrantKeys.value = isAllSelected.value ? [] : allPermissions.value.map(grantKey)
}
const errorMessage = (cause: unknown, fallback: string) => {
  return isApiError(cause) ? cause.message : fallback
}

const loadPermissions = async () => {
  const roleId = RoleData.value?.id
  if (!roleId) return
  if (!canManageTarget.value) {
    loadError.value = !isRoleClassificationKnown.value
      ? '无法确认目标角色类型，请刷新列表后重试。'
      : isSystemRole.value
      ? '只有超级管理员可以调整系统角色权限。'
      : '当前账号无权分配角色权限。'
    return
  }
  loading.value = true
  loadError.value = ''
  saveError.value = ''
  selectedGrantKeys.value = []
  allPermissions.value = []
  try {
    const [roleGrants, allPermissionsResult] = await Promise.all([
      client.roles.getGrants(roleId),
      client.permissions.all(),
    ])
    allPermissions.value = Array.isArray(allPermissionsResult)
      ? allPermissionsResult
      : Object.values(allPermissionsResult)
    const grants = (Array.isArray(roleGrants) ? roleGrants : Object.values(roleGrants)) as Grant[]
    selectedGrantKeys.value = grants.map(grantKey)
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载权限数据，请稍后重试。')
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  const roleId = RoleData.value?.id
  if (!roleId || saving.value) return
  if (!canManageTarget.value) {
    saveError.value = !isRoleClassificationKnown.value
      ? '无法确认目标角色类型，请刷新列表后重试。'
      : isSystemRole.value
      ? '只有超级管理员可以调整系统角色权限。'
      : '当前账号无权分配角色权限。'
    return
  }
  saving.value = true
  saveError.value = ''
  const grants: Grant[] = selectedGrantKeys.value.map((value) => {
    const separator = value.lastIndexOf(':')
    return {
      capability: value.slice(0, separator),
      scope: value.slice(separator + 1) as Grant['scope'],
    }
  })
  try {
    await client.roles.assignGrants(roleId, { grants })
    thisDialogState.value = false
    notifier.success('权限分配成功')
    await props.getTableData?.()
  } catch (cause) {
    saveError.value = errorMessage(cause, '权限保存失败，请稍后重试。')
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (open) {
    void loadPermissions()
  }
})
</script>
