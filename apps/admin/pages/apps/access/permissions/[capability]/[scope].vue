<template>
  <AdminPageContainer>
    <AdminPageHeader
      :title="permission ? `权限 · ${permission.capability}` : '权限详情'"
      description="查看由服务端维护的能力含义与作用域；能力目录在管理端保持只读。"
      :breadcrumbs="[
        { title: '用户与权限' },
        { title: '权限目录', to: '/apps/access/permissions' },
        { title: permission?.capability || capability },
      ]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-arrow-left" to="/apps/access/permissions" variant="outlined">返回目录</v-btn>
      </template>
    </AdminPageHeader>

    <AdminAsyncState
      :loading="loading"
      :error="error"
      :empty="!permission"
      empty-icon="mdi-key-alert-outline"
      empty-title="权限不存在"
      empty-description="能力可能已从服务端目录移除，请返回权限目录重新选择。"
      @retry="load"
    >
      <template v-if="permission">
        <v-sheet border rounded="lg" overflow-hidden>
          <v-list lines="two">
            <v-list-item prepend-icon="mdi-key-outline" subtitle="能力标识" :title="permission.capability" />
            <v-divider />
            <v-list-item prepend-icon="mdi-target-account" subtitle="作用域" :title="scopeLabel" />
            <v-divider />
            <v-list-item prepend-icon="mdi-text-box-check-outline" subtitle="服务端说明" :title="permission.description" />
          </v-list>
        </v-sheet>

        <v-alert
          class="mt-6"
          type="warning"
          variant="tonal"
          title="授予前先确认职责边界"
          text="权限必须通过角色组合授予；页面入口、动作按钮和后端路由都应引用同一个 capability 与 scope。"
        />
      </template>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { AuthorizationScope, CapabilityItem } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { isApiError, useApi } from '~/lib/api'

const route = useRoute()
const client = useApi()
const capability = computed(() => String(route.params.capability || ''))
const scope = computed(() => String(route.params.scope || ''))
const permission = ref<CapabilityItem | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const validScopes: AuthorizationScope[] = ['own', 'all', 'global']
const scopeLabel = computed(() => ({
  own: 'own · 仅自己的资源',
  all: 'all · 全部资源',
  global: 'global · 全局能力',
} as Record<AuthorizationScope, string>)[permission.value?.scope ?? 'global'])

let latestLoad = 0
async function load() {
  const requestId = ++latestLoad
  permission.value = null
  error.value = null
  if (!capability.value || !validScopes.includes(scope.value as AuthorizationScope)) {
    error.value = '权限标识或作用域无效。'
    return
  }

  loading.value = true
  try {
    const catalog = await client.permissions.all()
    if (requestId !== latestLoad) return
    permission.value = catalog.find((item) => (
      item.capability === capability.value && item.scope === scope.value
    )) ?? null
  } catch (cause) {
    if (requestId === latestLoad) {
      error.value = isApiError(cause) ? cause.message : '无法加载权限详情，请稍后重试。'
    }
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}

watch([capability, scope], () => { void load() }, { immediate: true })
</script>
