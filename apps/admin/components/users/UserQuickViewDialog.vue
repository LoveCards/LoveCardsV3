<template>
  <v-dialog v-model="open" aria-label="查看用户" :fullscreen="xs" max-width="680">
    <v-card>
      <v-card-title class="d-flex align-center ga-3">
        <v-icon icon="mdi-account-outline" size="small" />
        <span>查看用户</span>
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" aria-label="关闭查看用户" @click="open = false" />
      </v-card-title>
      <v-divider />
      <v-card-text class="admin-quick-view-body">
        <v-progress-linear v-if="loading" indeterminate color="primary" aria-label="正在读取用户" />
        <v-alert v-else-if="error" type="error" variant="tonal" :text="error">
          <template #append><v-btn variant="text" @click="load">重试</v-btn></template>
        </v-alert>
        <template v-else-if="user">
          <div class="d-flex align-center ga-3 mb-5">
            <v-avatar color="surface-variant" size="48">
              <v-img v-if="user.avatar" :src="user.avatar" :alt="`${user.username}的头像`" />
              <v-icon v-else icon="mdi-account" />
            </v-avatar>
            <div>
              <div class="text-subtitle-1 font-weight-medium">{{ user.username }}</div>
              <div class="text-body-2 text-medium-emphasis">用户 #{{ user.id }} · 账号 {{ user.number }}</div>
            </div>
            <v-spacer />
            <AdminStatusChip :status="status.status" :label="status.label" />
          </div>
          <v-list lines="two" density="comfortable">
            <v-list-item title="角色归属" :subtitle="roleNames || '未分配角色'" />
            <v-list-item><AdminSensitiveField label="邮箱" :value="user.email" /></v-list-item>
            <v-list-item><AdminSensitiveField label="手机号" :value="user.phone" /></v-list-item>
          </v-list>
        </template>
      </v-card-text>
      <v-divider />
      <v-card-actions class="pa-4">
        <v-btn
          v-if="user && canReadSecurity"
          :to="`/apps/users/${user.id}/security`"
          variant="text"
          @click="open = false"
        >查看账号安全</v-btn>
        <v-spacer />
        <v-btn variant="text" @click="open = false">关闭</v-btn>
        <v-btn
          v-if="user"
          :to="{ path: `/apps/users/${user.id}`, state: { adminSource: route.fullPath } }"
          variant="outlined"
          @click="open = false"
        >打开完整页面</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { User } from '@lovecards/sdk'
import { useDisplay } from 'vuetify'
import AdminSensitiveField from '~/components/shared/admin/AdminSensitiveField.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const props = defineProps<{ userId: number | null }>()
const open = defineModel<boolean>({ default: false })
const { xs } = useDisplay()
const route = useRoute()
const client = useApi()
const userStore = useUserStore()
const user = ref<User | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const canReadSecurity = computed(() => userStore.hasGrant('users.security.read', 'global'))
const roleNames = computed(() => {
  if (!user.value) return ''
  const embedded = new Map((user.value.roles ?? []).map((role) => [role.id, role.name]))
  return (user.value.roles_id ?? []).map((id) => embedded.get(id) || `角色 #${id}`).join('、')
})
const status = computed(() => {
  const labels: Record<number, { status: string; label: string }> = {
    0: { status: 'active', label: '正常' },
    1: { status: 'disabled', label: '已封禁' },
    2: { status: 'degraded', label: '已冻结' },
    3: { status: 'pending', label: '待审核' },
  }
  return labels[Number(user.value?.status)] ?? { status: 'unknown', label: '未知状态' }
})

let latestLoad = 0
async function load() {
  const id = props.userId
  if (!open.value || !id) return
  const requestId = ++latestLoad
  loading.value = true
  error.value = null
  user.value = null
  try {
    const result = await client.users.getManaged(id)
    if (requestId === latestLoad && open.value) user.value = result
  } catch (cause) {
    if (requestId === latestLoad && open.value) error.value = isApiError(cause) ? cause.message : '无法加载用户详情。'
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}
watch([open, () => props.userId], () => {
  if (open.value) void load()
  else latestLoad += 1
})
</script>

<style scoped>
.admin-quick-view-body { max-height: min(70vh, 640px); overflow-y: auto; }
@media (max-width: 599.98px) {
  .admin-quick-view-body { max-height: none; flex: 1 1 auto; }
}
</style>
