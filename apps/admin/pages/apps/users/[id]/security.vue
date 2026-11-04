<template>
  <AdminPageContainer>
    <AdminPageHeader :title="security ? `账号安全 · ${security.user.username}` : '账号安全'" description="查看账号安全版本与 Session，并执行最小范围的撤销。" :breadcrumbs="breadcrumbs">
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn>
        <AdminActionGuard v-if="mayRevokeSessions" capability="users.sessions.revoke">
          <v-btn color="error" prepend-icon="mdi-logout-variant" variant="outlined" :disabled="!activeSessions.length" @click="openRevokeAll">撤销全部 Session</v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!security" @retry="load">
      <template v-if="security">
        <v-alert
          v-if="protectionNotice"
          class="mb-4"
          type="info"
          variant="tonal"
          title="目标账号受保护"
          :text="protectionNotice"
        />
        <v-sheet border rounded="lg" class="pa-5">
          <v-row>
            <v-col cols="12" md="4"><div class="text-caption text-medium-emphasis">用户编号</div><div>#{{ security.user.id }} · {{ security.user.number }}</div></v-col>
            <v-col cols="12" md="4"><AdminSensitiveField label="邮箱" :value="security.user.email" /></v-col>
            <v-col cols="12" md="4"><AdminSensitiveField label="手机号" :value="security.user.phone" /></v-col>
            <v-col cols="12" md="4"><div class="text-caption text-medium-emphasis">账号状态</div><AdminStatusChip :status="accountStatus.status" :label="accountStatus.label" class="mt-1" /></v-col>
            <v-col cols="12" md="4"><div class="text-caption text-medium-emphasis">安全版本</div><div>{{ security.user.security_version }}</div></v-col>
            <v-col cols="12" md="4"><div class="text-caption text-medium-emphasis">恢复挑战</div><div>{{ security.recovery_challenges }} 个</div></v-col>
          </v-row>
        </v-sheet>

        <h2 class="text-h6 mt-8 mb-3">Session</h2>
        <v-sheet border rounded="lg">
          <v-data-table :headers="headers" :items="sessions" item-value="id" items-per-page-text="每页行数">
            <template #item.state="{ item }"><AdminStatusChip :status="item.active ? 'active' : 'closed'" :label="item.active ? '有效' : '已撤销或过期'" /></template>
            <template #item.action="{ item }">
              <AdminActionGuard v-if="mayRevokeSessions" capability="users.sessions.revoke">
                <v-tooltip v-if="item.active" text="撤销 Session" location="top">
                  <template #activator="{ props }">
                    <v-btn v-bind="props" aria-label="撤销 Session" color="error" icon="mdi-logout-variant" variant="text" @click="openRevoke(item.id)" />
                  </template>
                </v-tooltip>
              </AdminActionGuard>
              <v-tooltip v-else-if="item.active && hasRevocationGrant" :text="protectionNotice" location="top">
                <template #activator="{ props }">
                  <v-icon v-bind="props" aria-label="该 Session 受目标账号保护" color="warning" icon="mdi-lock-alert-outline" size="small" />
                </template>
              </v-tooltip>
            </template>
          </v-data-table>
        </v-sheet>
      </template>
    </AdminAsyncState>

    <AdminDangerActionDialog v-model="revokeDialog" title="撤销 Session" action-label="撤销" :resource-label="`Session #${selectedSession}`" impact="该设备的登录态将立即失效，用户需要重新登录。" :loading="revoking" :error="actionError" @confirm="handleRevoke" />
    <AdminDangerActionDialog v-model="revokeAllDialog" title="撤销全部 Session" action-label="全部撤销" :resource-label="security?.user.username || ''" impact="该用户所有设备（含正在使用的设备）的登录态将立即失效，此操作不可撤销，用户需要在各设备重新登录。" :loading="revoking" :error="actionError" @confirm="handleRevokeAll" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { UserSecurityOverview, UserSession } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminSensitiveField from '~/components/shared/admin/AdminSensitiveField.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'

const route = useRoute()
const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const userId = computed(() => Number(route.params.id))
const security = ref<UserSecurityOverview | null>(null)
const sessions = ref<UserSession[]>([])
const loading = ref(false)
const revoking = ref(false)
const error = ref<string | null>(null)
const actionError = ref('')
const selectedSession = ref<number | null>(null)
const revokeDialog = ref(false)
const revokeAllDialog = ref(false)
const hasRevocationGrant = computed(() => userStore.hasGrant('users.sessions.revoke', 'global'))
const mayRevokeSessions = computed(() => hasRevocationGrant.value && security.value?.session_mutation_allowed === true)
const protectionNotice = computed(() => hasRevocationGrant.value && security.value?.session_mutation_allowed === false
  ? '服务端判定当前账号不能修改此用户的安全会话。目标可能拥有 root 或更高权限；撤销操作已关闭。'
  : '')
const activeSessions = computed(() => sessions.value.filter((item: UserSession) => item.active))
const accountStatus = computed(() => {
  const states: Record<number, { status: string; label: string }> = {
    0: { status: 'active', label: '正常' },
    1: { status: 'disabled', label: '已封禁' },
    2: { status: 'degraded', label: '已冻结' },
    3: { status: 'pending', label: '待审核' },
  }
  return states[security.value?.user.status ?? -1] ?? { status: 'unknown', label: '未知' }
})
const breadcrumbs = [
  { title: '用户与权限' },
  { title: '用户管理', to: '/apps/users' },
  { title: `用户 #${route.params.id}`, to: `/apps/users/${route.params.id}` },
  { title: '账号安全', disabled: true },
]
const headers = [
  { title: 'Session', key: 'id' }, { title: '状态', key: 'state' }, { title: '安全版本', key: 'security_version' },
  { title: 'Token 到期', key: 'token_expires_at' }, { title: '可续期至', key: 'renewable_until' }, { title: '操作', key: 'action', sortable: false },
]

let latestLoad = 0
async function load() {
  const requestId = ++latestLoad
  loading.value = false
  security.value = null
  sessions.value = []
  selectedSession.value = null
  revokeDialog.value = false
  revokeAllDialog.value = false
  actionError.value = ''
  if (!Number.isInteger(userId.value) || userId.value <= 0) {
    error.value = '用户 ID 无效'
    return
  }
  loading.value = true
  error.value = null
  try {
    const [nextSecurity, nextSessions] = await Promise.all([
      client.users.security(userId.value),
      client.users.sessions(userId.value),
    ])
    if (requestId !== latestLoad) return
    security.value = nextSecurity
    sessions.value = nextSessions
  } catch (cause) {
    if (requestId === latestLoad) error.value = isApiError(cause) ? cause.message : '无法加载账号安全信息'
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}
function revocationBlockReason(): string | null {
  if (!hasRevocationGrant.value) return '当前账号无权撤销 Session。'
  if (!security.value) return '账号安全信息尚未加载完成。'
  if (!mayRevokeSessions.value) return protectionNotice.value
  return null
}
function openRevoke(id: number) {
  const blocked = revocationBlockReason()
  if (blocked) { notifier.warning(blocked); return }
  selectedSession.value = id
  actionError.value = ''
  revokeDialog.value = true
}
function openRevokeAll() {
  const blocked = revocationBlockReason()
  if (blocked) { notifier.warning(blocked); return }
  actionError.value = ''
  revokeAllDialog.value = true
}
async function revoke() {
  if (!selectedSession.value) return
  const blocked = revocationBlockReason()
  if (blocked) { actionError.value = blocked; return }
  revoking.value = true; actionError.value = ''
  try { await client.users.revokeSession(userId.value, String(selectedSession.value)); revokeDialog.value = false; notifier.success('Session 已撤销'); await load() }
  catch (cause) { actionError.value = isApiError(cause) ? cause.message : '撤销失败' }
  finally { revoking.value = false }
}
async function revokeAll() {
  const blocked = revocationBlockReason()
  if (blocked) { actionError.value = blocked; return }
  revoking.value = true; actionError.value = ''
  try { await client.users.revokeSessions(userId.value); revokeAllDialog.value = false; notifier.success('全部 Session 已撤销'); await load() }
  catch (cause) { actionError.value = isApiError(cause) ? cause.message : '撤销失败' }
  finally { revoking.value = false }
}
function handleRevoke() { void revoke() }
function handleRevokeAll() { void revokeAll() }
watch(userId, () => { void load() }, { immediate: true })
</script>
