<template>
  <AdminAsyncState
    :loading="loading"
    :error="error"
    :empty="!user"
    empty-icon="mdi-account-question-outline"
    empty-title="用户不存在"
    empty-description="该用户可能已删除，或当前账号无权读取。"
    @retry="load"
  >
    <template v-if="user">
      <AdminDetailSection title="用户资料">
        <div class="d-flex flex-wrap align-center ga-3 mb-4">
          <v-avatar color="surface-variant" size="48">
            <v-img v-if="mode === 'profile' ? draft.avatar : user.avatar" :src="String(mode === 'profile' ? draft.avatar : user.avatar)" :alt="`${user.username} 的头像`" />
            <v-icon v-else icon="mdi-account" aria-label="默认头像" />
          </v-avatar>
          <div class="user-detail__identity">
            <v-text-field v-if="mode === 'profile'" v-model="draft.username" label="用户名" required :disabled="saving" variant="outlined" hide-details class="mb-1" />
            <div v-else class="text-subtitle-1 font-weight-medium">{{ user.username }}</div>
            <div class="text-body-2 text-medium-emphasis">用户 #{{ user.id }} · 账号 {{ user.number }}</div>
          </div>

        </div>

        <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">{{ formError }}</v-alert>
        <template v-if="mode !== 'profile'">
          <v-row dense>
            <v-col cols="12" sm="6"><AdminSensitiveField label="邮箱" :value="user.email" /></v-col>
            <v-col cols="12" sm="6"><AdminSensitiveField label="手机号" :value="user.phone" /></v-col>
          </v-row>
          <div v-if="canEditTarget" class="d-flex justify-end mt-3">
            <v-btn prepend-icon="mdi-pencil" variant="tonal" @click="startEditing('profile')">编辑资料</v-btn>
          </div>
        </template>
        <template v-else>
          <v-row dense>
            <v-col cols="12" sm="6"><v-text-field v-model="draft.email" label="邮箱" type="email" :disabled="saving" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="draft.phone" label="手机号" type="tel" :disabled="saving" variant="outlined" /></v-col>
            <v-col cols="12">
              <v-file-input
                v-if="canUploadFiles"
                :model-value="selectedAvatar"
                accept="image/*"
                label="更换头像"
                prepend-icon="mdi-camera-outline"
                :disabled="saving || avatarUploading"
                :loading="avatarUploading"
                :error-messages="avatarUploadError ? [avatarUploadError] : []"
                show-size
                variant="outlined"
                @update:model-value="uploadAvatar"
              />
              <p v-if="canUploadFiles" class="text-caption text-medium-emphasis">头像文件选择后立即上传，取消编辑不会自动删除已上传文件。</p>
            </v-col>
          </v-row>
          <div class="d-flex justify-end ga-2 mt-3">
            <v-btn variant="text" :disabled="saving || avatarUploading" @click="cancelEditing">取消</v-btn>
            <v-btn color="primary" variant="flat" :loading="saving" :disabled="avatarUploading || !profileDirty" @click="saveProfile">保存资料</v-btn>
          </div>
        </template>
      <v-divider class="my-5" />
        <v-row dense align="center">
          <v-col v-if="canEditTarget" cols="12" sm="6">
            <v-select
              :model-value="Number(user.status)"
              :items="accountStatusOptions"
              :disabled="saving || rolesSaving || statusSaving || avatarUploading"
              label="账号状态"
              item-title="title"
              item-value="value"
              variant="outlined"
              @update:model-value="requestStatusCommand"
            />
          </v-col>
          <v-col v-else cols="12" sm="6"><AdminDetailField label="账号状态" :value="accountStatus.label" /></v-col>
        </v-row>
        <p v-if="dirty && canEditTarget" class="text-caption text-medium-emphasis mt-2">账号状态单独提交；当前未保存的字段修改仍需另行保存。</p>
      <v-divider class="my-5" />
        <div class="text-body-2 text-medium-emphasis mb-2">角色</div>
        <div v-if="mode !== 'roles' && roleLabels.length" class="d-flex flex-wrap ga-2">
          <v-chip v-for="role in roleLabels" :key="role.id" :color="role.slug === 'root' ? 'error' : undefined" variant="tonal">{{ role.label }}</v-chip>
        </div>
        <span v-else-if="mode !== 'roles'" class="text-body-2 text-medium-emphasis">未分配角色</span>
        <template v-if="mode === 'roles' && canManageRoles && canEditTarget">
          <v-select v-model="roleDraft" class="mt-4" :items="roleOptions" item-title="title" item-value="value" label="权限组" multiple chips clearable :loading="rolesLoading" :error-messages="rolesError ? [rolesError] : []" :disabled="rolesSaving" variant="outlined" />
          <div class="d-flex justify-end ga-2">
            <v-btn variant="text" :disabled="rolesSaving" @click="cancelEditing">取消</v-btn>
            <v-btn color="primary" variant="flat" :loading="rolesSaving" :disabled="!rolesDirty || rolesLoading" @click="saveRoles">保存角色分配</v-btn>
          </div>
        </template>
        <div v-else-if="mode !== 'roles' && canManageRoles && canEditTarget" class="d-flex justify-end mt-3">
          <v-btn variant="outlined" @click="startEditing('roles')">调整角色</v-btn>
        </div>
      </AdminDetailSection>


    </template>
  </AdminAsyncState>
  <AdminUnsavedChangesDialog :model-value="confirmDiscard" subject="用户尚未保存的修改" @stay="stayEditing" @discard="discardChanges" />
  <AdminDangerActionDialog
    v-model="statusConfirm"
    :title="statusActionLabel"
    :action-label="statusActionLabel"
    :resource-label="user ? user.username + '（用户 #' + user.id + '）' : ''"
    :impact="'账号状态将从“' + accountStatus.label + '”变为“' + statusTargetLabel + '”。此操作会影响账号访问或内容可见性。'"
    :color="statusDraft === 0 ? 'primary' : 'error'"
    :loading="statusSaving"
    :error="statusError"
    @confirm="saveStatus"
  />
</template>

<script setup lang="ts">
import type { Role, UploadResult, User } from '@lovecards/sdk'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminSensitiveField from '~/components/shared/admin/AdminSensitiveField.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import { classifyUserTargetRole, parseUserRoleIds } from '~/utils/userTargetProtection'

const props = withDefaults(defineProps<{ userId: number; initialMode?: 'view' | 'edit' }>(), { initialMode: 'view' })
const emit = defineEmits<{ loaded: [user: User | null]; updated: []; saved: [scope: 'profile' | 'roles'] }>()
const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const user = ref<User | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
type EditScope = 'profile' | 'roles'
const mode = ref<'view' | EditScope>('view')
const draft = ref<Partial<User>>({})
const roleDraft = ref<number[]>([])
const statusDraft = ref(0)
const statusSaving = ref(false)
const statusConfirm = ref(false)
const statusError = ref('')
const roleCatalog = ref<Role[]>([])
const roleCatalogLoaded = ref(false)
const roleOptions = ref<Array<{ title: string; value: number }>>([])
const rolesLoading = ref(false)
const rolesError = ref('')
const rolesSaving = ref(false)
const saving = ref(false)
const formError = ref('')
const avatarUploading = ref(false)
const avatarUploadError = ref('')
const selectedAvatar = ref<File | File[] | null>(null)
const confirmDiscard = ref(false)
let resolveDiscard: ((allow: boolean) => void) | null = null
let latestLoad = 0

const accountStatusOptions = [
  { title: '正常', value: 0 }, { title: '封禁', value: 1 },
  { title: '冻结', value: 2 }, { title: '待审核', value: 3 },
]
const accountStatus = computed(() => {
  const labels: Record<number, { status: string; label: string }> = {
    0: { status: 'active', label: '正常' }, 1: { status: 'disabled', label: '已封禁' },
    2: { status: 'degraded', label: '已冻结' }, 3: { status: 'pending', label: '待审核' },
  }
  return labels[Number(user.value?.status)] ?? { status: 'unknown', label: '未知状态' }
})
function requestStatusCommand(value: number | null) {
  if (value === null || !canEditTarget.value || saving.value || rolesSaving.value || statusSaving.value || avatarUploading.value) return
  statusDraft.value = value
  statusError.value = ''
  statusConfirm.value = true
}
const roleIds = computed(() => parseUserRoleIds(user.value?.roles_id) ?? [])
const roleLabels = computed(() => {
  if (!user.value) return []
  const embedded = new Map((user.value.roles ?? []).map((role) => [role.id, role]))
  return roleIds.value.map((id) => {
    const role = roleCatalog.value.find((item) => item.id === id) ?? embedded.get(id)
    return { id, slug: role?.slug ?? '', label: role?.name ? `${role.name} #${id}` : `角色 #${id}` }
  })
})
const isRootActor = computed(() => userStore.roles.some((role) => role.slug === 'root'))
const canEditTarget = computed(() => {
  if (!user.value || !userStore.hasGrant('users.update', 'all')) return false
  const state = classifyUserTargetRole(user.value, roleCatalog.value, roleCatalogLoaded.value)
  return state !== 'unknown' && (state !== 'root' || isRootActor.value)
})
const canManageRoles = computed(() => userStore.hasGrant('users.roles.assign', 'global') && userStore.hasGrant('roles.read', 'global'))
const canUploadFiles = computed(() => userStore.hasGrant('files.upload', 'global'))
const profileDirty = computed(() => !!user.value && ['username', 'email', 'phone', 'avatar'].some((key) => JSON.stringify(draft.value[key as keyof User]) !== JSON.stringify(user.value?.[key as keyof User])))
const rolesDirty = computed(() => JSON.stringify([...roleDraft.value].sort()) !== JSON.stringify([...roleIds.value].sort()))
const statusDirty = computed(() => !!user.value && statusDraft.value !== Number(user.value.status))
const statusTargetLabel = computed(() => accountStatusOptions.find((item) => item.value === statusDraft.value)?.title ?? '未知状态')
const statusActionLabel = computed(() => statusDraft.value === 0 ? '恢复账号' : statusDraft.value === 1 ? '封禁用户' : statusDraft.value === 2 ? '冻结用户' : '提交账号审核')
const dirty = computed(() => mode.value === 'profile' ? profileDirty.value : mode.value === 'roles' ? rolesDirty.value : false)

function resetDraft() {
  if (!user.value) return
  draft.value = { username: user.value.username, email: user.value.email, phone: user.value.phone, avatar: user.value.avatar }
  statusDraft.value = Number(user.value.status)
  roleDraft.value = [...roleIds.value]
  formError.value = ''
  avatarUploadError.value = ''
  selectedAvatar.value = null
}
async function loadRoles() {
  if (!userStore.hasGrant('roles.read', 'global')) return
  rolesLoading.value = true
  rolesError.value = ''
  try {
    const roles: Role[] = []
    let page = 1
    let totalPages = 1
    do {
      const result = await client.roles.list({ page, list_rows: 100 })
      roles.push(...result.data)
      totalPages = result.pagination?.totalPages ?? 1
      page += 1
    } while (page <= totalPages)
    roleCatalog.value = roles
    roleCatalogLoaded.value = true
    roleOptions.value = roles.map((role) => ({ title: `${role.name} #${role.id}`, value: role.id }))
  } catch (cause) {
    roleCatalogLoaded.value = false
    rolesError.value = isApiError(cause) ? cause.message : '无法加载角色目录。'
  } finally {
    rolesLoading.value = false
  }
}
async function load() {
  const requestId = ++latestLoad
  loading.value = true
  error.value = null
  user.value = null
  emit('loaded', null)
  try {
    const next = await client.users.getManaged(props.userId)
    if (requestId !== latestLoad) return
    user.value = next
    emit('loaded', next)
    resetDraft()
    if (props.initialMode === 'edit' && !canEditTarget.value && userStore.hasGrant('roles.read', 'global') && !rolesLoading.value && !roleCatalogLoaded.value) await loadRoles()
    mode.value = props.initialMode === 'edit' && canEditTarget.value ? 'profile' : 'view'
  } catch (cause) {
    if (requestId === latestLoad) error.value = isApiError(cause) ? cause.message : '无法加载用户详情。'
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}
async function startEditing(scope: EditScope) {
  if (!canEditTarget.value || mode.value === scope || (scope === 'roles' && !canManageRoles.value)) return
  if (mode.value !== 'view' && !(await requestClose())) return
  resetDraft()
  mode.value = scope
}
async function requestClose(): Promise<boolean> {
  if (saving.value || rolesSaving.value || statusSaving.value || avatarUploading.value) return false
  if (!dirty.value) return true
  confirmDiscard.value = true
  return new Promise<boolean>((resolve) => { resolveDiscard = resolve })
}
function stayEditing() {
  confirmDiscard.value = false
  resolveDiscard?.(false)
  resolveDiscard = null
}
function discardChanges() {
  confirmDiscard.value = false
  resetDraft()
  mode.value = 'view'
  resolveDiscard?.(true)
  resolveDiscard = null
}
async function cancelEditing() {
  if (!dirty.value) { mode.value = 'view'; return }
  await requestClose()
}
async function uploadAvatar(value: File | File[] | null) {
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
    draft.value.avatar = result.url
    selectedAvatar.value = null
  } catch (cause) {
    avatarUploadError.value = isApiError(cause) ? cause.message : '头像上传失败，请重新选择文件。'
  } finally {
    avatarUploading.value = false
  }
}
async function refreshAfterSave(): Promise<boolean> {
  if (!user.value) return false
  try {
    const next = await client.users.getManaged(user.value.id)
    user.value = next
    emit('loaded', next)
    resetDraft()
    mode.value = 'view'
    emit('updated')
    return true
  } catch {
    formError.value = '修改已提交，但无法读取最新用户资料；请刷新后核对结果。'
    emit('updated')
    return false
  }
}
async function saveProfile() {
  if (!user.value || !canEditTarget.value || !profileDirty.value || saving.value) return
  saving.value = true
  formError.value = ''
  try {
    const changes: Record<string, unknown> = {}
    for (const key of ['username', 'email', 'phone', 'avatar'] as const) {
      if (JSON.stringify(draft.value[key]) !== JSON.stringify(user.value[key])) changes[key] = draft.value[key]
    }
    await client.users.updateManaged(user.value.id, changes as Parameters<typeof client.users.updateManaged>[1])
    if (await refreshAfterSave()) { notifier.success('用户资料已保存'); emit('saved', 'profile') }
  } catch (cause) {
    formError.value = isApiError(cause) ? cause.message : '用户资料保存失败，请检查字段后重试。'
  } finally {
    saving.value = false
  }
}
async function saveStatus() {
  if (!user.value || !canEditTarget.value || !statusDirty.value || statusSaving.value) return
  statusSaving.value = true
  statusError.value = ''
  try {
    await client.users.updateManaged(user.value.id, { status: statusDraft.value })
    statusConfirm.value = false
    try {
      const refreshed = await client.users.getManaged(user.value.id)
      if (user.value?.id !== refreshed.id) return
      user.value.status = refreshed.status
      statusDraft.value = Number(refreshed.status)
      emit('updated')
      notifier.success('账号状态已更新')
    } catch {
      formError.value = '账号状态已提交，但无法读取最新状态；请刷新后核对结果。'
      emit('updated')
    }
  } catch (cause) {
    statusError.value = isApiError(cause) ? cause.message : '账号状态更新失败，请稍后重试。'
  } finally {
    statusSaving.value = false
  }
}
async function saveRoles() {
  if (!user.value || !canEditTarget.value || !canManageRoles.value || !rolesDirty.value || rolesSaving.value) return
  rolesSaving.value = true
  rolesError.value = ''
  try {
    await client.users.assignRoles(user.value.id, { roles_id: roleDraft.value })
    if (await refreshAfterSave()) { notifier.success('角色分配已保存'); emit('saved', 'roles') }
  } catch (cause) {
    rolesError.value = isApiError(cause) ? cause.message : '角色分配失败，请稍后重试。'
  } finally {
    rolesSaving.value = false
  }
}
const beforeUnload = (event: BeforeUnloadEvent) => {
  if (!dirty.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => { window.addEventListener('beforeunload', beforeUnload); void loadRoles() })
onBeforeUnmount(() => { latestLoad += 1; window.removeEventListener('beforeunload', beforeUnload); resolveDiscard?.(false) })
onBeforeRouteLeave(requestClose)
onBeforeRouteUpdate((to, from) => JSON.stringify(to.params) !== JSON.stringify(from.params) ? requestClose() : true)
watch(() => props.userId, () => { void load() }, { immediate: true })
defineExpose({ requestClose })
</script>

<style scoped>
.user-detail__identity { flex: 1 1 280px; max-width: 420px; min-width: min(280px, 100%); }
</style>
