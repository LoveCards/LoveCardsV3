<template>
  <div class="pa-4">
    <AdminAsyncState :loading="loading" :error="loadError" :empty="!role" empty-title="角色不存在" @retry="load">
      <template v-if="role">
        <v-alert v-if="actionError" class="mb-4" type="error" variant="tonal">{{ actionError }}</v-alert>
        <AdminDetailSection title="角色信息">
          <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-4">
            <div class="d-flex flex-wrap align-center ga-2">
              <strong>{{ role.name }}</strong>
              <v-chip size="small" variant="tonal" :color="classification === 'system' ? 'warning' : classification === 'unknown' ? 'error' : undefined">
                {{ classification === 'system' ? '系统角色' : classification === 'custom' ? '自定义角色' : '类型未知' }}
              </v-chip>
            </div>
            <v-btn v-if="mode === 'view' && canEdit" variant="tonal" prepend-icon="mdi-pencil-outline" @click="startEdit">编辑角色</v-btn>
          </div>
          <v-alert v-if="classification === 'unknown'" class="mb-4" type="warning" variant="tonal">无法确认角色类型，编辑与分配权限已锁定。</v-alert>
          <v-alert v-else-if="classification === 'system' && mode === 'edit'" class="mb-4" type="info" variant="tonal">系统角色的标识由服务端固定，只允许超级管理员修改名称和说明。</v-alert>
          <v-row v-if="mode !== 'edit'" dense>
            <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">稳定标识</div><div>{{ role.slug }}</div></v-col>
            <v-col cols="12" sm="6"><div class="text-caption text-medium-emphasis">编号</div><div>#{{ role.id }}</div></v-col>
            <v-col cols="12"><div class="text-caption text-medium-emphasis">职责说明</div><div class="text-pre-wrap">{{ role.description || '未填写职责说明' }}</div></v-col>
          </v-row>
          <template v-else>
            <v-row dense>
              <v-col cols="12" sm="6"><v-text-field v-model="draftName" label="角色名称" maxlength="50" required variant="outlined" :disabled="busy" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-if="classification === 'custom'" v-model="draftSlug" label="角色标识（Slug）" maxlength="50" required variant="outlined" :disabled="busy" /><div v-else><div class="text-caption text-medium-emphasis">稳定标识</div>{{ role.slug }}</div></v-col>
              <v-col cols="12"><v-textarea v-model="draftDescription" label="角色描述" maxlength="255" rows="3" auto-grow variant="outlined" :disabled="busy" /></v-col>
            </v-row>
            <div class="d-flex justify-end ga-2 mt-3">
              <v-btn variant="text" :disabled="busy" @click="cancelMode">取消编辑</v-btn>
              <v-btn color="primary" variant="flat" :disabled="!canSaveBasics" :loading="busy" @click="saveBasics">保存修改</v-btn>
            </div>
          </template>
        </AdminDetailSection>

        <AdminDetailSection class="mt-5" title="已授予能力" description="权限分配独立保存，会影响角色可见数据和可执行操作。">
          <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
            <v-chip size="small" variant="outlined">{{ grants.length }} 项</v-chip>
            <v-btn v-if="mode === 'view' && canAssign" variant="outlined" prepend-icon="mdi-shield-key-outline" @click="startAssign">分配权限</v-btn>
          </div>
          <template v-if="mode === 'assign'">
            <v-alert class="mb-3" type="info" variant="tonal">权限调整会影响该角色可见的页面、数据范围和可执行操作，请在保存前核对选择范围。</v-alert>
            <AdminAsyncState :loading="permissionsLoading" :error="permissionsError" :empty="!permissions.length" empty-title="暂无权限项" @retry="loadPermissions">
              <v-alert v-if="classification === 'system'" class="mb-3" type="warning" variant="tonal">修改系统角色授权会改变管理边界，请核对范围后保存。</v-alert>
              <div class="d-flex align-center justify-space-between ga-2 mb-2">
                <span class="text-body-2">已选择 {{ selectedGrantKeys.length }} / {{ permissions.length }} 项</span>
                <v-btn size="small" variant="tonal" :disabled="busy" @click="toggleAll">{{ allSelected ? '取消全选' : '全选' }}</v-btn>
              </div>
              <v-list density="comfortable">
                <v-list-item v-for="permission in permissions" :key="grantKey(permission)">
                  <v-checkbox v-model="selectedGrantKeys" :label="permission.description || permission.capability" :value="grantKey(permission)" :disabled="busy" hide-details />
                  <template #subtitle>{{ permission.capability }} · {{ scopeLabel(permission.scope) }}</template>
                </v-list-item>
              </v-list>
            </AdminAsyncState>
            <div class="d-flex justify-end ga-2 mt-3">
              <v-btn variant="text" :disabled="busy" @click="cancelMode">取消分配</v-btn>
              <v-btn color="primary" variant="flat" :disabled="!canSaveGrants" :loading="busy" @click="saveGrants">保存权限</v-btn>
            </div>
          </template>
          <v-list v-else-if="grants.length" density="compact">
            <v-list-item v-for="grant in grants" :key="grantKey(grant)" prepend-icon="mdi-key-outline" :title="grant.capability" :subtitle="scopeLabel(grant.scope)" :to="permissionPath(grant)" />
          </v-list>
          <p v-else class="text-body-2 text-medium-emphasis">该角色没有单独授予的能力。</p>
        </AdminDetailSection>
        <v-alert v-if="confirmCancel" class="mt-4" type="warning" variant="tonal">
          当前修改尚未保存，确定放弃吗？
          <template #append><v-btn variant="text" @click="confirmCancel = false">继续编辑</v-btn><v-btn color="warning" variant="flat" @click="discardMode">放弃修改</v-btn></template>
        </v-alert>
      </template>
    </AdminAsyncState>
  </div>
</template>

<script setup lang="ts">
import type { AuthorizationScope, CapabilityItem, Grant, Role } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

type Mode = 'view' | 'edit' | 'assign'
const props = withDefaults(defineProps<{ roleId: number; initialMode?: Mode }>(), { initialMode: 'view' })
const emit = defineEmits<{ saved: []; loaded: [role: Role | null]; 'dirty-change': [value: boolean]; 'busy-change': [value: boolean] }>()
const client = useApi()
const userStore = useUserStore()
const role = ref<Role | null>(null)
const grants = ref<Grant[]>([])
const permissions = ref<CapabilityItem[]>([])
const selectedGrantKeys = ref<string[]>([])
const draftName = ref('')
const draftSlug = ref('')
const draftDescription = ref('')
const mode = ref<Mode>('view')
const loading = ref(false)
const permissionsLoading = ref(false)
const busy = ref(false)
const loadError = ref<string | null>(null)
const permissionsError = ref<string | null>(null)
const actionError = ref<string | null>(null)
const confirmCancel = ref(false)
let latestLoad = 0
let latestPermissionsLoad = 0
let initialModePending = true

const classification = computed(() => Number(role.value?.is_system) === 1 ? 'system' : Number(role.value?.is_system) === 0 ? 'custom' : 'unknown')
const isRootActor = computed(() => userStore.roles.some(item => item.slug === 'root'))
const canEdit = computed(() => Boolean(role.value) && classification.value !== 'unknown' && userStore.hasGrant('roles.update', 'global') && (classification.value !== 'system' || isRootActor.value))
const canAssign = computed(() => Boolean(role.value) && classification.value !== 'unknown' && userStore.hasGrant('roles.assign', 'global') && userStore.hasGrant('permissions.read', 'global') && (classification.value !== 'system' || isRootActor.value))
const grantKey = (grant: Pick<Grant, 'capability' | 'scope'>) => `${grant.capability}:${grant.scope}`
const scopeLabel = (scope: AuthorizationScope) => ({ own: '仅自己的资源', all: '全部资源', global: '全局能力' })[scope]
const permissionPath = (grant: Grant) => `/apps/access/permissions/${encodeURIComponent(grant.capability)}/${encodeURIComponent(grant.scope)}`
const originalGrantKeys = computed(() => grants.value.map(grantKey).sort())
const basicDirty = computed(() => {
  const current = role.value
  return mode.value === 'edit' && current !== null && (
    draftName.value !== current.name
    || (classification.value === 'custom' && draftSlug.value !== current.slug)
    || draftDescription.value !== (current.description ?? '')
  )
})
const grantsDirty = computed(() => mode.value === 'assign' && JSON.stringify([...selectedGrantKeys.value].sort()) !== JSON.stringify(originalGrantKeys.value))
const dirty = computed(() => basicDirty.value || grantsDirty.value)
const canSaveBasics = computed(() => canEdit.value && basicDirty.value && Boolean(draftName.value.trim()) && (classification.value === 'system' || Boolean(draftSlug.value.trim())) && !busy.value)
const canSaveGrants = computed(() => canAssign.value && permissions.value.length > 0 && grantsDirty.value && !permissionsLoading.value && !permissionsError.value && !busy.value)
const allSelected = computed(() => permissions.value.length > 0 && permissions.value.every(permission => selectedGrantKeys.value.includes(grantKey(permission))))
watch(dirty, value => emit('dirty-change', value), { immediate: true })
watch(() => loading.value || permissionsLoading.value || busy.value, value => emit('busy-change', value), { immediate: true })

function resetDraft() {
  if (!role.value) return
  draftName.value = role.value.name
  draftSlug.value = role.value.slug
  draftDescription.value = role.value.description ?? ''
  selectedGrantKeys.value = grants.value.map(grantKey)
}
function message(cause: unknown, fallback: string) { return isApiError(cause) ? cause.message : fallback }
async function load() {
  if (dirty.value) { actionError.value = '请先保存或放弃当前修改，再重新加载角色。'; return }
  const id = props.roleId
  const request = ++latestLoad
  role.value = null
  grants.value = []
  permissions.value = []
  permissionsLoading.value = false
  permissionsError.value = null
  emit('loaded', null)
  loadError.value = null
  actionError.value = null
  ++latestPermissionsLoad
  if (!Number.isSafeInteger(id) || id < 1) { loadError.value = '角色 ID 无效。'; return }
  loading.value = true
  try {
    const [nextRole, nextGrants] = await Promise.all([client.roles.get(id), client.roles.getGrants(id)])
    if (request !== latestLoad || props.roleId !== id) return
    role.value = nextRole
    grants.value = [...nextGrants].sort((a, b) => grantKey(a).localeCompare(grantKey(b)))
    resetDraft()
    emit('loaded', nextRole)
    const initial = initialModePending ? props.initialMode : 'view'
    initialModePending = false
    if (initial === 'edit' && canEdit.value) startEdit()
    else if (initial === 'assign' && canAssign.value) startAssign()
    else mode.value = 'view'
  } catch (cause) {
    if (request !== latestLoad || props.roleId !== id) return
    loadError.value = message(cause, '无法加载角色详情，请稍后重试。')
  } finally { if (request === latestLoad) loading.value = false }
}
async function loadPermissions() {
  if (!canAssign.value) return
  const id = props.roleId
  const request = ++latestPermissionsLoad
  permissionsLoading.value = true
  permissionsError.value = null
  try {
    const result = await client.permissions.all()
    if (request !== latestPermissionsLoad || props.roleId !== id || mode.value !== 'assign') return
    permissions.value = [...result].sort((a, b) => grantKey(a).localeCompare(grantKey(b)))
  } catch (cause) { if (request === latestPermissionsLoad && props.roleId === id) permissionsError.value = message(cause, '无法加载权限目录，请稍后重试。') }
  finally { if (request === latestPermissionsLoad) permissionsLoading.value = false }
}
function startEdit() { if (!canEdit.value || dirty.value) return; resetDraft(); mode.value = 'edit'; actionError.value = null }
function startAssign() { if (!canAssign.value || dirty.value) return; resetDraft(); mode.value = 'assign'; actionError.value = null; void loadPermissions() }
function cancelMode() { if (dirty.value) { confirmCancel.value = true; return }; discardMode() }
function discardMode() { resetDraft(); confirmCancel.value = false; mode.value = 'view'; actionError.value = null }
function toggleAll() { selectedGrantKeys.value = allSelected.value ? [] : permissions.value.map(grantKey) }
async function reloadAfterWrite(id: number, failure: string) {
  const request = ++latestLoad
  try {
    const [nextRole, nextGrants] = await Promise.all([client.roles.get(id), client.roles.getGrants(id)])
    if (request !== latestLoad || props.roleId !== id) return
    role.value = nextRole
    grants.value = nextGrants
    resetDraft()
    emit('loaded', nextRole)
  } catch { if (request === latestLoad && props.roleId === id) loadError.value = failure }
}
async function saveBasics() {
  const current = role.value
  if (!current || !canSaveBasics.value) return
  busy.value = true; actionError.value = null
  const params = {
    ...(draftName.value.trim() !== current.name ? { name: draftName.value.trim() } : {}),
    ...(classification.value === 'custom' && draftSlug.value.trim() !== current.slug ? { slug: draftSlug.value.trim() } : {}),
    ...(draftDescription.value.trim() !== (current.description ?? '') ? { description: draftDescription.value.trim() } : {}),
  }
  try {
    await client.roles.update(current.id, params)
    if (props.roleId !== current.id) return
    role.value = { ...current, ...params }
    mode.value = 'view'; resetDraft(); emit('saved')
    await reloadAfterWrite(current.id, '修改已保存，但最新角色详情读取失败，请重试确认。')
  } catch (cause) { actionError.value = message(cause, '角色修改失败，请稍后重试。') }
  finally { busy.value = false }
}
async function saveGrants() {
  const current = role.value
  if (!current || !canSaveGrants.value) return
  busy.value = true; actionError.value = null
  const nextGrants: Grant[] = selectedGrantKeys.value.map(key => {
    const separator = key.lastIndexOf(':')
    return { capability: key.slice(0, separator), scope: key.slice(separator + 1) as AuthorizationScope }
  })
  try {
    await client.roles.assignGrants(current.id, { grants: nextGrants })
    if (props.roleId !== current.id) return
    grants.value = nextGrants
    mode.value = 'view'; resetDraft(); emit('saved')
    await reloadAfterWrite(current.id, '权限已保存，但最新角色详情读取失败，请重试确认。')
  } catch (cause) { actionError.value = message(cause, '角色权限保存失败，请稍后重试。') }
  finally { busy.value = false }
}
watch(() => props.roleId, () => { initialModePending = true; mode.value = 'view'; void load() }, { immediate: true })
</script>
