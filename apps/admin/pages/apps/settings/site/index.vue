<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="站点信息"
      description="维护站点对外展示的名称、地址和备案信息。"
      :breadcrumbs="[{ title: '站点' }]"
    />

    <AdminAsyncState
      :error="loadError"
      error-title="配置加载失败"
      :loading="loading"
      skeleton-type="heading, paragraph, text@8"
      @retry="loadConfig"
    >
      <v-alert v-if="siteMessage" class="mb-4" :type="siteMessage.type" variant="tonal">
        {{ siteMessage.text }}
      </v-alert>

      <AdminConfigSection
        class="mb-4"
        :dirty="identityChanged"
        description="站点对外展示的名称、地址与备案版权信息。"
        icon="mdi-web"
        title="基本信息"
      >
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="siteConfig.system.siteUrl"
              :disabled="!canUpdateConfig || siteSaving"
              density="comfortable"
              hint="包含协议的站点公开访问地址"
              label="站点地址"
              persistent-hint
              placeholder="https://www.example.com"
              type="url"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="siteConfig.system.siteName"
              :disabled="!canUpdateConfig || siteSaving"
              density="comfortable"
              label="站点名称"
              placeholder="LoveCards"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="siteConfig.system.siteTitle"
              :disabled="!canUpdateConfig || siteSaving"
              density="comfortable"
              label="页面标题"
              placeholder="LoveCards"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="siteConfig.system.siteKeywords"
              :disabled="!canUpdateConfig || siteSaving"
              density="comfortable"
              hint="用于页面元信息，多个关键词用逗号分隔"
              label="站点关键词"
              persistent-hint
              placeholder="社区,内容,兴趣"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea
              v-model="siteConfig.system.siteDes"
              auto-grow
              :disabled="!canUpdateConfig || siteSaving"
              density="comfortable"
              label="站点介绍"
              rows="3"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="siteConfig.system.siteICPId"
              :disabled="!canUpdateConfig || siteSaving"
              density="comfortable"
              label="备案信息"
              placeholder="ICP备案号"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="siteConfig.system.siteCopyright"
              :disabled="!canUpdateConfig || siteSaving"
              density="comfortable"
              label="版权信息"
              placeholder="LoveCards 版权所有"
              variant="outlined"
            />
          </v-col>
        </v-row>
      </AdminConfigSection>

      <AdminConfigSaveBar class="mt-4" :dirty="siteChanged" label="站点信息">
        <AdminActionGuard capability="config.update" scope="global">
          <v-btn
            color="primary"
            :disabled="!siteChanged"
            :loading="siteSaving"
            prepend-icon="mdi-content-save-outline"
            variant="flat"
            @click="saveSite"
          >
            保存站点信息
          </v-btn>
        </AdminActionGuard>
      </AdminConfigSaveBar>
    </AdminAsyncState>

    <AdminUnsavedChangesDialog
      :model-value="unsavedChangesDialog"
      @stay="continueEditing"
      @discard="discardChanges"
    />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { JsonObject } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminConfigSection from '~/components/shared/admin/AdminConfigSection.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import { readObject, readString } from '~/utils/json'

interface SiteSettings {
  siteUrl: string
  siteName: string
  siteTitle: string
  siteKeywords: string
  siteDes: string
  siteICPId: string
  siteCopyright: string
}
interface SiteConfig {
  system: SiteSettings
}
type ActionMessage = { type: 'success' | 'error'; text: string }

const client = useApi()
const userStore = useUserStore()
const loading = ref(false)
const loadError = ref('')
const siteSaving = ref(false)
const siteMessage = ref<ActionMessage | null>(null)
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const emptyConfig: SiteConfig = {
  system: {
    siteUrl: '',
    siteName: '',
    siteTitle: '',
    siteKeywords: '',
    siteDes: '',
    siteICPId: '',
    siteCopyright: '',
  },
}
const siteConfig = ref<SiteConfig>(CommonUtils.deepClone(emptyConfig))
const originConfig = ref<SiteConfig>(CommonUtils.deepClone(emptyConfig))
const identityChanged = computed(() => JSON.stringify(siteConfig.value.system) !== JSON.stringify(originConfig.value.system))
const siteChanged = identityChanged
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(siteChanged)

const toFrontendFormat = (raw: JsonObject): SiteConfig => {
  const core = readObject(raw.core)
  return {
    system: {
      siteUrl: readString(core.url),
      siteName: readString(core.name),
      siteTitle: readString(core.title),
      siteKeywords: readString(core.keywords),
      siteDes: readString(core.description),
      siteICPId: readString(core.icp_id),
      siteCopyright: readString(core.copyright),
    },
  }
}
const toBackendSite = (config: SiteConfig): JsonObject => ({
  core: {
    url: config.system.siteUrl,
    name: config.system.siteName,
    title: config.system.siteTitle,
    keywords: config.system.siteKeywords,
    description: config.system.siteDes,
    icp_id: config.system.siteICPId,
    copyright: config.system.siteCopyright,
  },
})
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback

const loadConfig = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const converted = toFrontendFormat(await client.config.list())
    siteConfig.value = CommonUtils.deepClone(converted)
    originConfig.value = CommonUtils.deepClone(converted)
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载站点配置，请稍后重试。')
  } finally {
    loading.value = false
  }
}
const saveSite = async () => {
  if (!canUpdateConfig.value || !siteChanged.value || siteSaving.value) return
  siteSaving.value = true
  siteMessage.value = null
  try {
    await client.config.update(toBackendSite(siteConfig.value))
    originConfig.value = CommonUtils.deepClone(siteConfig.value)
    siteMessage.value = { type: 'success', text: '站点信息已保存。' }
  } catch (cause) {
    siteMessage.value = { type: 'error', text: errorMessage(cause, '站点信息保存失败，请稍后重试。') }
  } finally {
    siteSaving.value = false
  }
}

onMounted(() => {
  void loadConfig()
})
</script>
