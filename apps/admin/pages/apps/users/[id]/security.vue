<template>
  <div>
    <AdminPageHeader :title="security ? `${security.user.username} 的账号安全` : '账号安全'" description="查看账号安全版本与 Session，并执行最小范围的撤销。" :breadcrumbs="breadcrumbs">
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn>
        <AdminActionGuard capability="users.sessions.revoke">
          <v-btn color="error" prepend-icon="mdi-logout-variant" variant="outlined" :disabled="!activeSessions.length" @click="revokeAllDialog = true">撤销全部 Session</v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading" :error="error" :empty="!security" @retry="load">
      <template v-if="security">
        <v-sheet border rounded="lg" class="pa-5">
          <v-row>
            <v-col cols="12" md="4"><div class="text-caption text-medium-emphasis">用户编号</div><div>#{{ security.user.id }} · {{ security.user.number }}</div></v-col>
            <v-col cols="12" md="4"><AdminSensitiveField label="邮箱" :value="security.user.email" /></v-col>
            <v-col cols="12" md="4"><AdminSensitiveField label="手机号" :value="security.user.phone" /></v-col>
            <v-col cols="12" md="4"><div class="text-caption text-medium-emphasis">账号状态</div><AdminStatusChip :status="security.user.status === 1 ? 'active' : 'disabled'" class="mt-1" /></v-col>
            <v-col cols="12" md="4"><div class="text-caption text-medium-emphasis">安全版本</div><div>{{ security.user.security_version }}</div></v-col>
            <v-col cols="12" md="4"><div class="text-caption text-medium-emphasis">恢复挑战</div><div>{{ security.recovery_challenges }} 个</div></v-col>
          </v-row>
        </v-sheet>

        <h2 class="text-h6 mt-8 mb-3">Session</h2>
        <v-sheet border rounded="lg">
          <v-data-table :headers="headers" :items="sessions" item-value="id">
            <template #item.state="{ item }"><AdminStatusChip :status="item.active ? 'active' : 'closed'" :label="item.active ? '有效' : '已撤销或过期'" /></template>
            <template #item.action="{ item }">
              <AdminActionGuard capability="users.sessions.revoke">
                <v-btn v-if="item.active" color="error" icon="mdi-logout-variant" title="撤销 Session" variant="text" @click="openRevoke(item.id)" />
              </AdminActionGuard>
            </template>
          </v-data-table>
        </v-sheet>
      </template>
    </AdminAsyncState>

    <AdminDangerActionDialog v-model="revokeDialog" title="撤销 Session" action-label="撤销" :resource-label="`Session #${selectedSession}`" impact="该设备的登录态将立即失效，用户需要重新登录。" :loading="revoking" :error="actionError" @confirm="handleRevoke" />
    <AdminDangerActionDialog v-model="revokeAllDialog" title="撤销全部 Session" action-label="全部撤销" :resource-label="security?.user.username || ''" impact="该用户所有设备的登录态将立即失效。" confirm-text="撤销全部" :loading="revoking" :error="actionError" @confirm="handleRevokeAll" />
  </div>
</template>

<script setup lang="ts">
import type { UserSecurityOverview, UserSession } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminSensitiveField from '~/components/shared/admin/AdminSensitiveField.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'

const route = useRoute()
const client = useApi()
const notifier = useAdminNotifier()
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
const activeSessions = computed(() => sessions.value.filter((item: UserSession) => item.active))
const breadcrumbs = [{ title: '用户管理', to: '/apps/users' }, { title: `用户 #${route.params.id}`, disabled: true }]
const headers = [
  { title: 'Session', key: 'id' }, { title: '状态', key: 'state' }, { title: '安全版本', key: 'security_version' },
  { title: 'Token 到期', key: 'token_expires_at' }, { title: '可续期至', key: 'renewable_until' }, { title: '操作', key: 'action', sortable: false },
]

async function load() {
  loading.value = true; error.value = null
  try { [security.value, sessions.value] = await Promise.all([client.users.security(userId.value), client.users.sessions(userId.value)]) }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载账号安全信息' }
  finally { loading.value = false }
}
function openRevoke(id: number) { selectedSession.value = id; actionError.value = ''; revokeDialog.value = true }
async function revoke(reason: string) {
  if (!selectedSession.value) return
  revoking.value = true; actionError.value = ''
  try { await client.users.revokeSession(userId.value, String(selectedSession.value), { reason }); revokeDialog.value = false; notifier.success('Session 已撤销'); await load() }
  catch (cause) { actionError.value = isApiError(cause) ? cause.message : '撤销失败' }
  finally { revoking.value = false }
}
async function revokeAll(reason: string) {
  revoking.value = true; actionError.value = ''
  try { await client.users.revokeSessions(userId.value, { reason }); revokeAllDialog.value = false; notifier.success('全部 Session 已撤销'); await load() }
  catch (cause) { actionError.value = isApiError(cause) ? cause.message : '撤销失败' }
  finally { revoking.value = false }
}
function handleRevoke(payload: { reason: string }) { void revoke(payload.reason) }
function handleRevokeAll(payload: { reason: string }) { void revokeAll(payload.reason) }
onMounted(load)
</script>
