<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="user ? `用户 · ${user.username}` : `用户 #${route.params.id}`"
      description="查看账号身份、状态与角色归属；安全会话和恢复能力保持在独立的受限页面。"
      :breadcrumbs="[
        { title: '用户与权限' },
        { title: '用户管理', to: '/apps/users' },
        { title: user?.username || `#${route.params.id}` },
      ]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" to="/apps/users" variant="outlined">返回列表</v-btn>
        <AdminActionGuard capability="users.security.read" scope="global">
          <v-btn
            v-if="user"
            prepend-icon="mdi-shield-account-outline"
            :to="`/apps/users/${user.id}/security`"
            variant="tonal"
          >
            账号安全
          </v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>

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
        <v-sheet border rounded="lg" overflow-hidden>
          <v-list lines="two">
            <v-list-item>
              <template #prepend>
                <v-avatar color="surface-variant" size="48">
                  <v-img v-if="user.avatar" :alt="`${user.username} 的头像`" :src="user.avatar" />
                  <v-icon v-else aria-label="默认头像" icon="mdi-account" />
                </v-avatar>
              </template>
              <v-list-item-title>{{ user.username }}</v-list-item-title>
              <v-list-item-subtitle>用户 #{{ user.id }} · 账号 {{ user.number }}</v-list-item-subtitle>
              <template #append>
                <AdminStatusChip :status="accountStatus.status" :label="accountStatus.label" />
              </template>
            </v-list-item>
            <v-divider />
            <v-list-item prepend-icon="mdi-email-outline" subtitle="邮箱">
              <AdminSensitiveField label="邮箱" :value="user.email" />
            </v-list-item>
            <v-divider />
            <v-list-item prepend-icon="mdi-phone-outline" subtitle="手机号">
              <AdminSensitiveField label="手机号" :value="user.phone" />
            </v-list-item>
          </v-list>
        </v-sheet>

        <section class="mt-8" aria-labelledby="user-roles-title">
          <h2 id="user-roles-title" class="mb-3 text-h6">角色归属</h2>
          <v-sheet border class="pa-4" rounded="lg">
            <div v-if="roleLabels.length" class="d-flex flex-wrap ga-2">
              <v-chip
                v-for="role in roleLabels"
                :key="role.id"
                :color="role.slug === 'root' ? 'error' : undefined"
                :prepend-icon="role.slug === 'root' ? 'mdi-shield-crown-outline' : 'mdi-account-key-outline'"
                variant="tonal"
              >
                {{ role.label }}
              </v-chip>
            </div>
            <v-empty-state
              v-else
              class="py-4"
              icon="mdi-account-off-outline"
              title="未分配角色"
              text="该账号当前没有显式角色归属。"
            />
          </v-sheet>
        </section>

        <v-alert
          class="mt-6"
          icon="mdi-shield-lock-outline"
          type="info"
          variant="tonal"
          title="安全操作独立授权"
          text="查看本页不等于可以修改用户或撤销会话；所有动作仍由服务端 capability 和目标账号保护规则判定。"
        />
      </template>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { User } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminSensitiveField from '~/components/shared/admin/AdminSensitiveField.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { isApiError, useApi } from '~/lib/api'

const route = useRoute()
const client = useApi()
const user = ref<User | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const accountStatus = computed(() => {
  const states: Record<number, { status: string; label: string }> = {
    0: { status: 'active', label: '正常' },
    1: { status: 'disabled', label: '已封禁' },
    2: { status: 'degraded', label: '已冻结' },
    3: { status: 'pending', label: '待审核' },
  }
  return states[user.value?.status ?? -1] ?? { status: 'unknown', label: '未知状态' }
})

const roleLabels = computed(() => {
  if (!user.value) return []
  const embedded = new Map((user.value.roles ?? []).map((role) => [role.id, role]))
  return (user.value.roles_id ?? []).map((id) => {
    const role = embedded.get(id)
    return {
      id,
      slug: role?.slug ?? '',
      label: role?.name ? `${role.name} · ${role.slug}` : `角色 #${id}`,
    }
  })
})

let latestLoad = 0
async function load() {
  const requestId = ++latestLoad
  const id = Number(route.params.id)
  user.value = null
  error.value = null
  if (!Number.isInteger(id) || id < 1) {
    error.value = '用户 ID 无效。'
    return
  }

  loading.value = true
  try {
    const nextUser = await client.users.getManaged(id)
    if (requestId === latestLoad) user.value = nextUser
  } catch (cause) {
    if (requestId === latestLoad) {
      error.value = isApiError(cause) ? cause.message : '无法加载用户详情，请稍后重试。'
    }
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}

watch(() => route.params.id, () => { void load() }, { immediate: true })
</script>
