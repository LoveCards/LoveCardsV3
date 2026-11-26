<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="主题外观"
      description="查看已安装主题、切换当前主题，并进入当前主题的配置页面。"
      :breadcrumbs="[{ title: '站点' }]"
    >
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" :disabled="mutationBusy" @click="loadThemes">刷新</v-btn>
        <AdminActionGuard capability="theme.upload" scope="global">
          <v-btn color="primary" prepend-icon="mdi-upload" :disabled="requiresVerification || mutationBusy || loading" @click="openUpload">上传主题</v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>

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
          <v-card class="d-flex h-100 flex-column" variant="flat" border>
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
                prepend-icon="mdi-tune-variant"
                variant="text"
                @click="openThemeConfig(theme.id, theme.release)"
              >
                配置主题
              </v-btn>
              <AdminActionGuard v-else capability="theme.activate" scope="global">
                <v-btn
                  :disabled="requiresVerification || mutationBusy || loading"
                  :loading="activatingTheme === themeKey(theme)"
                  prepend-icon="mdi-check"
                  variant="text"
                  @click="activateTheme(theme.id, theme.release)"
                >
                  设为当前主题
                </v-btn>
              </AdminActionGuard>
              <v-spacer />
              <AdminActionGuard v-if="!theme.active" capability="theme.delete" scope="global">
                <v-btn variant="text" :disabled="requiresVerification || mutationBusy || loading" @click="openDelete(theme)">删除</v-btn>
              </AdminActionGuard>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
    </AdminAsyncState>

    <v-dialog v-model="uploadDialog" aria-label="上传主题" max-width="560" :persistent="uploading">
      <v-card>
        <v-card-title class="text-h6">上传主题</v-card-title>
        <v-divider />
        <v-card-text class="pt-5">
          <p class="mb-4 text-body-2 text-medium-emphasis">选择主题 ZIP（不超过 32 MB）。上传会安装不可变版本，随后可手动设为当前主题。</p>
          <v-file-input v-model="themeFile" label="主题 ZIP" accept=".zip,application/zip" prepend-icon="mdi-file-upload-outline" variant="outlined" :disabled="uploading || requiresVerification" />
          <v-alert v-if="uploadError" class="mt-3" type="error" variant="tonal" role="alert">{{ uploadError }}</v-alert>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer /><v-btn variant="text" :disabled="uploading" @click="uploadDialog = false">取消</v-btn>
          <v-btn color="primary" variant="flat" :loading="uploading || loading" :disabled="!requiresVerification && !themeFile" @click="uploadTheme">{{ requiresVerification ? '刷新核对' : '上传' }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <AdminDangerActionDialog v-model="deleteDialog" title="删除主题版本" :action-label="requiresVerification ? '刷新核对' : '删除'" :color="requiresVerification ? 'primary' : 'error'" :resource-label="deletingTheme ? `${deletingTheme.name} · ${deletingTheme.release}` : ''" impact="删除此已安装版本的文件。任何站点正在使用的版本均不能删除；重新安装需要原始主题包。" :loading="deleting || loading" :error="deleteError" @confirm="deleteTheme" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { ThemeItem } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const client = useApi()
const userStore = useUserStore()
const notifier = useAdminNotifier()
const themes = ref<ThemeItem[]>([])
const loading = ref(false)
const loadError = ref('')
const activationError = ref('')
const activatingTheme = ref('')
const uploadDialog = ref(false)
const uploading = ref(false)
const uploadError = ref('')
const themeFile = ref<File | File[] | null>(null)
const deleteDialog = ref(false)
const deleting = ref(false)
const deleteError = ref('')
const deletingTheme = ref<ThemeItem | null>(null)
const requiresVerification = ref(false)
const mutationBusy = computed(() => Boolean(activatingTheme.value) || uploading.value || deleting.value)
let latestRead = 0
const themeKey = (theme: Pick<ThemeItem, 'id' | 'release'>) => `${theme.id}:${theme.release}`
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback
const mutationError = (cause: unknown, fallback: string) => {
  if (isApiError(cause)) {
    const details = cause.details
    if (details && typeof details === 'object') {
      const data = details as Record<string, unknown>
      if (data.side_effects === 'applied' || data.side_effects === 'possible'
        || (data.side_effects !== 'none' && (cause.status === 0 || cause.status >= 500))) requiresVerification.value = true
      return `${cause.message}${typeof data.operation_id === 'string' ? ` 操作编号：${data.operation_id}` : ''}`
    }
    if (cause.status === 0 || cause.status >= 500) requiresVerification.value = true
  } else requiresVerification.value = true
  return errorMessage(cause, fallback) + (requiresVerification.value ? ' 请先刷新核对，再进行下一次操作。' : '')
}

const loadThemes = async () => {
  const request = ++latestRead
  loading.value = true
  loadError.value = ''
  try {
    const result = await client.theme.list()
    if (request !== latestRead) return false
    themes.value = result
    requiresVerification.value = false
    activationError.value = ''
    return true
  } catch (cause) {
    if (request === latestRead) loadError.value = errorMessage(cause, '无法加载主题列表，请稍后重试。')
    return false
  } finally {
    if (request === latestRead) loading.value = false
  }
}
const activateTheme = async (id: string, release: string) => {
  if (!userStore.hasGrant('theme.activate', 'global') || requiresVerification.value || mutationBusy.value || loading.value) return
  const key = `${id}:${release}`
  activatingTheme.value = key
  activationError.value = ''
  try {
    await client.theme.activate({ id, release })
    await loadThemes()
  } catch (cause) {
    activationError.value = mutationError(cause, '主题启用失败，请稍后重试。')
  } finally {
    activatingTheme.value = ''
  }
}
const openUpload = () => {
  if (mutationBusy.value || loading.value || requiresVerification.value) return
  uploadError.value = ''; themeFile.value = null; uploadDialog.value = true
}
const uploadTheme = async () => {
  if (requiresVerification.value) { if (await loadThemes()) uploadDialog.value = false; return }
  if (!userStore.hasGrant('theme.upload', 'global') || mutationBusy.value || loading.value) return
  const file = Array.isArray(themeFile.value) ? themeFile.value[0] : themeFile.value
  if (!file) return
  if (!file.name.toLowerCase().endsWith('.zip') || file.size > 32 * 1024 * 1024) { uploadError.value = '请选择不超过 32 MB 的主题 ZIP。'; return }
  uploading.value = true; uploadError.value = ''
  try {
    const form = new FormData(); form.append('file', file)
    await client.theme.upload(form)
    uploadDialog.value = false; themeFile.value = null
    notifier.success('主题版本已安装')
    await loadThemes()
  } catch (cause) { uploadError.value = mutationError(cause, '主题上传失败。') }
  finally { uploading.value = false }
}
const openDelete = (theme: ThemeItem) => {
  if (theme.active || requiresVerification.value || mutationBusy.value || loading.value) return
  deletingTheme.value = theme; deleteError.value = ''; deleteDialog.value = true
}
const deleteTheme = async () => {
  if (requiresVerification.value) { if (await loadThemes()) deleteDialog.value = false; return }
  const theme = deletingTheme.value
  if (!theme || theme.active || mutationBusy.value || loading.value || !userStore.hasGrant('theme.delete', 'global')) return
  deleting.value = true; deleteError.value = ''
  try {
    await client.theme.delete({ id: theme.id, release: theme.release })
    deleteDialog.value = false; deletingTheme.value = null
    notifier.success('主题版本已删除')
    await loadThemes()
  } catch (cause) { deleteError.value = mutationError(cause, '主题删除失败。') }
  finally { deleting.value = false }
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
onBeforeUnmount(() => { latestRead += 1 })
</script>
