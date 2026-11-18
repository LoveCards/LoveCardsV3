<template>
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
</template>

<script setup lang="ts">
import type { AuthorizationScope, CapabilityItem } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import { isApiError, useApi } from '~/lib/api'

const props = defineProps<{ capability: string; scope: string }>()
const client = useApi()
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
  const capability = props.capability
  const scope = props.scope
  permission.value = null
  error.value = null
  if (!capability || !validScopes.includes(scope as AuthorizationScope)) {
    error.value = '权限标识或作用域无效。'
    loading.value = false
    return
  }
  loading.value = true
  try {
    const catalog = await client.permissions.all()
    if (requestId !== latestLoad) return
    permission.value = catalog.find(item => item.capability === capability && item.scope === scope) ?? null
  } catch (cause) {
    if (requestId === latestLoad) error.value = isApiError(cause) ? cause.message : '无法加载权限详情，请稍后重试。'
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}

watch(() => [props.capability, props.scope], () => { void load() }, { immediate: true })
</script>
