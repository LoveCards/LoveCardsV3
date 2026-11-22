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
        <template #actions>
          <v-btn v-if="mode === 'view' && canEditTarget" prepend-icon="mdi-pencil-outline" variant="tonal" @click="startEditing">编辑</v-btn>
        </template>
        <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">{{ formError }}</v-alert>
        <div class="user-detail__identity d-flex flex-wrap align-center ga-4 mb-4">
          <v-avatar color="surface-variant" size="64">
            <v-img v-if="mode === 'edit' ? draft.avatar : user.avatar" :src="String(mode === 'edit' ? draft.avatar : user.avatar)" :alt="`${user.username} 的头像`" />
            <v-icon v-else icon="mdi-account" aria-label="默认头像" />
          </v-avatar>
          <div class="user-detail__primary min-w-0">
            <v-text-field v-if="mode === 'edit'" v-model="draft.username" label="用户名" required :disabled="saving" variant="outlined" hide-details />
            <AdminDetailField v-else label="用户名" :value="user.username" />
            <div class="text-body-2 text-medium-emphasis mt-1">用户 #{{ user.id }} · 账号 {{ user.number }}</div>
          </div>
          <div v-if="mode === 'edit' && canUploadFiles" class="user-detail__avatar-editor min-w-0">
            <v-file-input
              :model-value="selectedAvatar"
              accept="image/*"
              label="更换头像"
              prepend-icon=""
              :disabled="saving || avatarUploading"
              :loading="avatarUploading"
              :error-messages="avatarUploadError ? [avatarUploadError] : []"
              :hide-details="!avatarUploadError"
              show-size
              variant="outlined"
              @update:model-value="uploadAvatar"
            />
            <p class="text-caption text-medium-emphasis mt-1 mb-0">头像选择后立即上传；取消编辑不会删除已上传文件。</p>
          </div>
        </div>
        <v-row class="align-start">
          <v-col cols="12" sm="6">
            <v-text-field v-if="mode === 'edit'" v-model="draft.email" label="邮箱" type="email" :disabled="saving" variant="outlined" hide-details />
            <AdminSensitiveField v-else label="邮箱" :value="user.email" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-if="mode === 'edit'" v-model="draft.phone" label="手机号" type="tel" :disabled="saving" variant="outlined" hide-details />
            <AdminSensitiveField v-else label="手机号" :value="user.phone" />
          </v-col>
          <v-col cols="12"><v-divider class="my-1" /></v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-if="canEditTarget"
              :model-value="Number(user.status)"
              :items="accountStatusOptions"
              :disabled="saving || statusSaving || avatarUploading"
              label="账号状态"
              item-title="title"
              item-value="value"
              variant="outlined"
              hide-details
              @update:model-value="requestStatusCommand"
            />
            <AdminDetailField v-else label="账号状态" :value="accountStatus.label" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-if="mode === 'edit' && canManageRoles && canEditTarget && !rolesError"
              v-model="roleDraft"
              :items="roleOptions"
              item-title="title"
              item-value="value"
              label="角色"
              multiple
              chips
              clearable
              :loading="rolesLoading"
              :disabled="saving"
              variant="outlined"
            />
            <div v-else class="user-detail__roles-field">
              <div class="user-detail__field-label">角色</div>
              <div class="d-flex flex-wrap align-center ga-2">
                <v-chip v-for="role in roleLabels" :key="role.id" :color="role.slug === 'root' ? 'error' : undefined" variant="tonal">{{ role.label }}</v-chip>
                <span v-if="!roleLabels.length" class="text-body-2 text-medium-emphasis">未分配角色</span>
              </div>
            </div>
            <p v-if="mode === 'edit' && canManageRoles && rolesError" class="text-caption text-error mt-2 mb-0">{{ rolesError }}</p>
          </v-col>
        </v-row>
        <p v-if="dirty && canEditTarget" class="text-caption text-medium-emphasis mt-3 mb-0">账号状态单独提交；当前未保存的字段修改仍需另行保存。</p>
        <div v-if="mode === 'edit'" class="d-flex justify-end ga-2 mt-5">
          <v-btn variant="text" :disabled="saving || avatarUploading" @click="cancelEditing">取消</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" :disabled="avatarUploading || !dirty || (canManageRoles && rolesDirty && (rolesLoading || !!rolesError))" @click="saveChanges">保存修改</v-btn>
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
const emit = defineEmits<{ loaded: [user: User | null]; updated: []; saved: [] }>()
const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const user = ref<User | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const mode = ref<'view' | 'edit'>('view')
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
  if (value === null || !canEditTarget.value || saving.value || statusSaving.value || avatarUploading.value) return
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
const dirty = computed(() => mode.value === 'edit' && (profileDirty.value || (canManageRoles.value && rolesDirty.value)))

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
    if (props.initialMode === 'edit' && userStore.hasGrant('roles.read', 'global') && !roleCatalogLoaded.value) await loadRoles()
    mode.value = props.initialMode === 'edit' && canEditTarget.value ? 'edit' : 'view'
  } catch (cause) {
    if (requestId === latestLoad) error.value = isApiError(cause) ? cause.message : '无法加载用户详情。'
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}
function startEditing() {
  if (!canEditTarget.value || mode.value === 'edit') return
  if (canManageRoles.value && !roleCatalogLoaded.value && !rolesLoading.value) void loadRoles()
  resetDraft()
  mode.value = 'edit'
}
async function requestClose(): Promise<boolean> {
  if (saving.value || statusSaving.value || avatarUploading.value) return false
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
async function saveChanges() {
  if (!user.value || !canEditTarget.value || !dirty.value || saving.value || avatarUploading.value) return
  if (canManageRoles.value && rolesDirty.value && (rolesLoading.value || rolesError.value)) return
  saving.value = true
  formError.value = ''
  try {
    const changes: Record<string, unknown> = {}
    for (const key of ['username', 'email', 'phone', 'avatar'] as const) {
      if (JSON.stringify(draft.value[key]) !== JSON.stringify(user.value[key])) changes[key] = draft.value[key]
    }
    if (canManageRoles.value && rolesDirty.value) changes.roles_id = roleDraft.value
    await client.users.updateManaged(user.value.id, changes as Parameters<typeof client.users.updateManaged>[1])
    if (await refreshAfterSave()) { notifier.success('用户修改已保存'); emit('saved') }
  } catch (cause) {
    formError.value = isApiError(cause) ? cause.message : '用户修改保存失败，请检查字段后重试。'
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
const beforeUnload = (event: BeforeUnloadEvent) => {
  if (!dirty.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => { window.addEventListener('beforeunload', beforeUnload); if (props.initialMode !== 'edit') void loadRoles() })
onBeforeUnmount(() => { latestLoad += 1; window.removeEventListener('beforeunload', beforeUnload); resolveDiscard?.(false) })
onBeforeRouteLeave(requestClose)
onBeforeRouteUpdate((to, from) => JSON.stringify(to.params) !== JSON.stringify(from.params) ? requestClose() : true)
watch(() => props.userId, () => { void load() }, { immediate: true })
defineExpose({ requestClose })
</script>

<style scoped>
.user-detail__primary { flex: 1 1 260px; }
.user-detail__avatar-editor { flex: 0 1 320px; }
.user-detail__roles-field { min-height: 56px; }
@media (max-width: 600px) {
  .user-detail__primary { flex-basis: calc(100% - 80px); }
  .user-detail__avatar-editor { flex: 1 1 100%; }
}
.user-detail__field-label { margin-bottom: 4px; color: rgb(var(--v-theme-on-surface-variant)); font-size: 0.875rem; line-height: 1.4; }
</style>
