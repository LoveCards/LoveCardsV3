<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="role ? `角色 · ${role.name}` : `角色 #${route.params.id}`"
      description="核对角色用途和实际能力范围。角色授权修改仍通过受保护的角色管理流程完成。"
      :breadcrumbs="[
        { title: '访问控制' },
        { title: '角色', to: '/apps/access/roles' },
        { title: role?.name || `#${route.params.id}` },
      ]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" to="/apps/access/roles" variant="outlined">返回列表</v-btn>
      </template>
    </AdminPageHeader>

    <AdminAsyncState
      :loading="loading"
      :error="error"
      :empty="!role"
      empty-icon="mdi-account-key-outline"
      empty-title="角色不存在"
      @retry="load"
    >
      <template v-if="role">
        <v-sheet border rounded="lg" overflow-hidden>
          <v-list lines="two">
            <v-list-item prepend-icon="mdi-account-key-outline" subtitle="角色名称" :title="role.name" />
            <v-divider />
            <v-list-item prepend-icon="mdi-identifier" subtitle="稳定标识" :title="role.slug" />
            <v-divider />
            <v-list-item
              prepend-icon="mdi-text-box-outline"
              subtitle="职责说明"
              :title="role.description || '未填写职责说明'"
            />
            <v-divider />
            <v-list-item prepend-icon="mdi-shield-lock-outline" subtitle="角色类型">
              <template #title>
                <v-chip :color="role.is_system ? 'warning' : 'info'" size="small" variant="tonal">
                  {{ role.is_system ? '系统角色' : '自定义角色' }}
                </v-chip>
              </template>
            </v-list-item>
          </v-list>
        </v-sheet>

        <section class="mt-8" aria-labelledby="role-grants-title">
          <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
            <h2 id="role-grants-title" class="text-h6">已授予能力</h2>
            <v-chip size="small" variant="outlined">{{ grants.length }} 项</v-chip>
          </div>
          <v-sheet v-if="grants.length" border rounded="lg" overflow-hidden>
            <v-list lines="two">
              <template v-for="(grant, index) in grants" :key="`${grant.capability}:${grant.scope}`">
                <v-list-item
                  prepend-icon="mdi-key-outline"
                  :subtitle="scopeLabel(grant.scope)"
                  :title="grant.capability"
                  :to="permissionPath(grant.capability, grant.scope)"
                >
                  <template #append><v-icon icon="mdi-chevron-right" /></template>
                </v-list-item>
                <v-divider v-if="index < grants.length - 1" />
              </template>
            </v-list>
          </v-sheet>
          <v-empty-state
            v-else
            icon="mdi-key-remove-outline"
            title="没有授予能力"
            text="该角色不会单独提供管理能力。"
          />
        </section>

        <v-alert
          class="mt-6"
          type="info"
          variant="tonal"
          title="权限由服务端判定"
          text="管理端隐藏入口只是体验层；每个请求仍会由后端核验 capability、scope 和目标资源保护规则。"
        />
      </template>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { AuthorizationScope, Grant, Role } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { isApiError, useApi } from '~/lib/api'

const route = useRoute()
const client = useApi()
const role = ref<Role | null>(null)
const grants = ref<Grant[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

function scopeLabel(scope: AuthorizationScope): string {
  return ({ own: '仅自己的资源', all: '全部资源', global: '全局能力' } as Record<AuthorizationScope, string>)[scope]
}

function permissionPath(capability: string, scope: AuthorizationScope): string {
  return `/apps/access/permissions/${encodeURIComponent(capability)}/${encodeURIComponent(scope)}`
}

let latestLoad = 0
async function load() {
  const requestId = ++latestLoad
  const id = Number(route.params.id)
  role.value = null
  grants.value = []
  error.value = null
  if (!Number.isInteger(id) || id < 1) {
    error.value = '角色 ID 无效。'
    return
  }

  loading.value = true
  try {
    const [nextRole, nextGrants] = await Promise.all([
      client.roles.get(id),
      client.roles.getGrants(id),
    ])
    if (requestId !== latestLoad) return
    role.value = nextRole
    grants.value = [...nextGrants].sort((left, right) => (
      left.capability.localeCompare(right.capability) || left.scope.localeCompare(right.scope)
    ))
  } catch (cause) {
    if (requestId === latestLoad) {
      error.value = isApiError(cause) ? cause.message : '无法加载角色详情，请稍后重试。'
    }
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}

watch(() => route.params.id, () => { void load() }, { immediate: true })
</script>
