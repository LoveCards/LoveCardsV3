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
      <v-sheet border rounded="lg" class="pa-6">
        <v-row dense>
          <v-col cols="12" sm="6"><AdminDetailField label="能力标识" :value="permission.capability" /></v-col>
          <v-col cols="12" sm="6"><AdminDetailField label="作用域" :value="scopeLabel" /></v-col>
          <v-col cols="12"><AdminDetailField label="服务端说明" :value="permission.description" /></v-col>
        </v-row>
      </v-sheet>

    </template>
  </AdminAsyncState>
</template>

<script setup lang="ts">
import type { AuthorizationScope, CapabilityItem } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailField from '~/components/shared/admin/AdminDetailField.vue'
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
