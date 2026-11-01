<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="主题外观"
      description="查看已安装主题、切换当前主题，并进入当前主题的配置页面。"
      :breadcrumbs="[{ title: '站点设置' }]"
    />

    <v-alert v-if="activationError" class="mb-4" role="alert" type="error" variant="tonal">
      {{ activationError }}
    </v-alert>

    <AdminAsyncState
      :empty="themes.length === 0"
      empty-description="服务器当前没有发现可用的主题包。安装主题后可在这里启用。"
      empty-icon="mdi-palette-outline"
      empty-title="尚未安装主题"
      :error="loadError"
      error-title="主题列表加载失败"
      :loading="loading"
      skeleton-type="image, article@3"
      @retry="loadThemes"
    >
      <v-row>
        <v-col v-for="theme in themes" :key="themeKey(theme)" cols="12" md="6" lg="4">
          <v-card class="d-flex h-100 flex-column">
            <v-card-item>
              <template #prepend>
                <v-avatar color="primary" variant="tonal">
                  <v-icon>mdi-palette-outline</v-icon>
                </v-avatar>
              </template>
              <v-card-title>{{ theme.name }}</v-card-title>
              <v-card-subtitle>作者：{{ theme.author || '未知' }}</v-card-subtitle>
              <template #append>
                <v-chip v-if="theme.active" color="success" prepend-icon="mdi-check-circle" size="small">
                  当前主题
                </v-chip>
              </template>
            </v-card-item>

            <v-card-text class="flex-grow-1">
              <p class="mb-4 text-body-2">{{ theme.description || '该主题没有提供说明。' }}</p>
              <div class="d-flex flex-wrap ga-2">
                <v-chip size="small" variant="outlined">版本 {{ theme.version }}</v-chip>
                <v-chip size="small" variant="outlined">{{ theme.mode.toUpperCase() }}</v-chip>
                <v-chip size="small" variant="outlined">发布 {{ theme.release }}</v-chip>
              </div>
            </v-card-text>

            <v-divider />
            <v-card-actions>
              <v-btn
                v-if="theme.active"
                color="primary"
                prepend-icon="mdi-tune-variant"
                variant="text"
                @click="openThemeConfig(theme.id, theme.release)"
              >
                配置主题
              </v-btn>
              <AdminActionGuard v-else capability="theme.activate" scope="global">
                <v-btn
                  color="primary"
                  :loading="activatingTheme === themeKey(theme)"
                  prepend-icon="mdi-check"
                  variant="text"
                  @click="activateTheme(theme.id, theme.release)"
                >
                  设为当前主题
                </v-btn>
              </AdminActionGuard>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
    </AdminAsyncState>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { ThemeItem } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const client = useApi()
const userStore = useUserStore()
const themes = ref<ThemeItem[]>([])
const loading = ref(false)
const loadError = ref('')
const activationError = ref('')
const activatingTheme = ref('')
const themeKey = (theme: Pick<ThemeItem, 'id' | 'release'>) => `${theme.id}:${theme.release}`
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback

const loadThemes = async () => {
  loading.value = true
  loadError.value = ''
  try {
    themes.value = await client.theme.list()
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载主题列表，请稍后重试。')
  } finally {
    loading.value = false
  }
}
const activateTheme = async (id: string, release: string) => {
  if (!userStore.hasGrant('theme.activate', 'global')) return
  const key = `${id}:${release}`
  activatingTheme.value = key
  activationError.value = ''
  try {
    await client.theme.activate({ id, release })
    await loadThemes()
  } catch (cause) {
    activationError.value = errorMessage(cause, '主题启用失败，请稍后重试。')
  } finally {
    activatingTheme.value = ''
  }
}
const openThemeConfig = (id: string, release: string) => {
  void navigateTo({
    path: `/apps/settings/appearance/themes/${encodeURIComponent(id)}`,
    query: { release },
  })
}

onMounted(() => {
  void loadThemes()
})
</script>
