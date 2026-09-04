<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="站点与社区规则"
      description="维护站点基础信息和社区行为规则。保存后会影响前台展示与内容发布流程。"
      :breadcrumbs="[{ title: '系统配置' }, { title: '站点与社区规则' }]"
    />

    <v-sheet border rounded="lg">
      <v-tabs v-model="viewTab" color="primary">
        <v-tab prepend-icon="mdi-web" value="site">基本信息</v-tab>
        <v-tab prepend-icon="mdi-tune-variant" value="rules">社区规则</v-tab>
      </v-tabs>
      <v-divider />

      <div class="pa-4 pa-md-6">
        <AdminAsyncState
          :error="loadError"
          error-title="配置加载失败"
          :loading="loading"
          skeleton-type="heading, paragraph, text@8"
          @retry="loadConfig"
        >
          <v-window v-model="viewTab">
            <v-window-item value="site">
              <v-alert v-if="siteMessage" class="mb-4" :type="siteMessage.type" variant="tonal">
                {{ siteMessage.text }}
              </v-alert>
              <v-form @submit.prevent="saveSite">
                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="systemConfig.system.siteUrl"
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
                      v-model="systemConfig.system.siteName"
                      :disabled="!canUpdateConfig || siteSaving"
                      density="comfortable"
                      label="站点名称"
                      placeholder="LoveCards"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="systemConfig.system.siteTitle"
                      :disabled="!canUpdateConfig || siteSaving"
                      density="comfortable"
                      label="页面标题"
                      placeholder="LoveCards"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="systemConfig.system.siteKeywords"
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
                      v-model="systemConfig.system.siteDes"
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
                      v-model="systemConfig.system.siteICPId"
                      :disabled="!canUpdateConfig || siteSaving"
                      density="comfortable"
                      label="备案信息"
                      placeholder="ICP备案号"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="systemConfig.system.siteCopyright"
                      :disabled="!canUpdateConfig || siteSaving"
                      density="comfortable"
                      label="版权信息"
                      placeholder="LoveCards 版权所有"
                      variant="outlined"
                    />
                  </v-col>
                </v-row>
                <v-divider class="mb-4" />
                <div class="d-flex justify-end">
                  <AdminActionGuard capability="config.update" scope="global">
                    <v-btn
                      color="primary"
                      :disabled="!siteChanged"
                      :loading="siteSaving"
                      prepend-icon="mdi-content-save-outline"
                      type="submit"
                      variant="flat"
                    >
                      保存基本信息
                    </v-btn>
                  </AdminActionGuard>
                </div>
              </v-form>
            </v-window-item>

            <v-window-item value="rules">
              <v-alert v-if="rulesMessage" class="mb-4" :type="rulesMessage.type" variant="tonal">
                {{ rulesMessage.text }}
              </v-alert>
              <v-alert class="mb-4" type="info" variant="tonal">
                审核开关和上传限制会直接影响新内容发布，请在低峰期调整并观察审核队列。
              </v-alert>
              <v-form @submit.prevent="saveRules">
                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="systemConfig.master.System.VisitorMode"
                      :disabled="!canUpdateConfig || rulesSaving"
                      density="comfortable"
                      item-title="title"
                      item-value="value"
                      :items="switchItems"
                      label="访客浏览"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="systemConfig.master.UserAuth.Captcha"
                      :disabled="!canUpdateConfig || rulesSaving"
                      density="comfortable"
                      item-title="title"
                      item-value="value"
                      :items="switchItems"
                      label="注册邮件验证"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-number-input
                      v-model="systemConfig.master.Upload.UserImageSize"
                      control-variant="split"
                      :disabled="!canUpdateConfig || rulesSaving"
                      density="comfortable"
                      label="单张图片大小上限（MB）"
                      :min="1"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="systemConfig.master.Upload.UserImageExt"
                      :disabled="!canUpdateConfig || rulesSaving"
                      density="comfortable"
                      hint="多个扩展名使用逗号分隔"
                      label="允许的图片格式"
                      persistent-hint
                      placeholder="jpg,png,gif,webp"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-number-input
                      v-model="systemConfig.master.Cards.PictureLimit"
                      control-variant="split"
                      :disabled="!canUpdateConfig || rulesSaving"
                      density="comfortable"
                      label="卡片图集上限"
                      :min="0"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-number-input
                      v-model="systemConfig.master.Cards.TagLimit"
                      control-variant="split"
                      :disabled="!canUpdateConfig || rulesSaving"
                      density="comfortable"
                      label="卡片标签上限"
                      :min="0"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="systemConfig.master.Cards.Approve"
                      :disabled="!canUpdateConfig || rulesSaving"
                      density="comfortable"
                      item-title="title"
                      item-value="value"
                      :items="switchItems"
                      label="卡片发布审核"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="systemConfig.master.Comments.Approve"
                      :disabled="!canUpdateConfig || rulesSaving"
                      density="comfortable"
                      item-title="title"
                      item-value="value"
                      :items="switchItems"
                      label="评论发布审核"
                      variant="outlined"
                    />
                  </v-col>
                </v-row>
                <v-divider class="mb-4" />
                <div class="d-flex justify-end">
                  <AdminActionGuard capability="config.update" scope="global">
                    <v-btn
                      color="primary"
                      :disabled="!rulesChanged"
                      :loading="rulesSaving"
                      prepend-icon="mdi-content-save-outline"
                      type="submit"
                      variant="flat"
                    >
                      保存社区规则
                    </v-btn>
                  </AdminActionGuard>
                </div>
              </v-form>
            </v-window-item>
          </v-window>
        </AdminAsyncState>
      </div>
    </v-sheet>
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
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import { readBoolean, readNumber, readObject, readString } from '~/utils/json'

interface SiteSettings {
  siteUrl: string
  siteName: string
  siteTitle: string
  siteKeywords: string
  siteDes: string
  siteICPId: string
  siteCopyright: string
}
interface MasterSettings {
  System: { VisitorMode: boolean }
  UserAuth: { Captcha: boolean }
  Upload: { UserImageSize: number; UserImageExt: string }
  Cards: { PictureLimit: number; TagLimit: number; Approve: boolean }
  Comments: { Approve: boolean }
}
interface FrontendConfig {
  system: SiteSettings
  master: MasterSettings
}
type ActionMessage = { type: 'success' | 'error'; text: string }

const client = useApi()
const userStore = useUserStore()
const viewTab = ref('site')
const loading = ref(false)
const loadError = ref('')
const siteSaving = ref(false)
const rulesSaving = ref(false)
const siteMessage = ref<ActionMessage | null>(null)
const rulesMessage = ref<ActionMessage | null>(null)
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const switchItems = [
  { title: '开启', value: true },
  { title: '关闭', value: false },
]

const emptyConfig: FrontendConfig = {
  system: {
    siteUrl: '',
    siteName: '',
    siteTitle: '',
    siteKeywords: '',
    siteDes: '',
    siteICPId: '',
    siteCopyright: '',
  },
  master: {
    System: { VisitorMode: false },
    UserAuth: { Captcha: false },
    Upload: { UserImageSize: 2, UserImageExt: '' },
    Cards: { PictureLimit: 15, TagLimit: 3, Approve: false },
    Comments: { Approve: false },
  },
}
const systemConfig = ref<FrontendConfig>(CommonUtils.deepClone(emptyConfig))
const originConfig = ref<FrontendConfig>(CommonUtils.deepClone(emptyConfig))
const siteChanged = computed(() => JSON.stringify(systemConfig.value.system) !== JSON.stringify(originConfig.value.system))
const rulesChanged = computed(() => JSON.stringify(systemConfig.value.master) !== JSON.stringify(originConfig.value.master))
const hasUnsavedConfig = computed(() => siteChanged.value || rulesChanged.value)
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(hasUnsavedConfig)

const toFrontendFormat = (raw: JsonObject): FrontendConfig => {
  const core = readObject(raw.core)
  const user = readObject(raw.user)
  const upload = readObject(raw.upload)
  const cards = readObject(raw.cards)
  const comments = readObject(raw.comments)
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
    master: {
      System: { VisitorMode: readBoolean(core.visitor_mode) },
      UserAuth: { Captcha: readBoolean(user.captcha) },
      Upload: {
        UserImageSize: readNumber(upload.user_image_size, 2),
        UserImageExt: readString(upload.user_image_ext),
      },
      Cards: {
        PictureLimit: readNumber(cards.picture_limit, 15),
        TagLimit: readNumber(cards.tag_limit, 3),
        Approve: readBoolean(cards.approve),
      },
      Comments: { Approve: readBoolean(comments.approve) },
    },
  }
}
const toBackendSite = (system: SiteSettings): JsonObject => ({
  core: {
    url: system.siteUrl,
    name: system.siteName,
    title: system.siteTitle,
    keywords: system.siteKeywords,
    description: system.siteDes,
    icp_id: system.siteICPId,
    copyright: system.siteCopyright,
  },
})
const toBackendRules = (master: MasterSettings): JsonObject => ({
  core: { visitor_mode: master.System.VisitorMode },
  user: { captcha: master.UserAuth.Captcha },
  upload: {
    user_image_size: master.Upload.UserImageSize,
    user_image_ext: master.Upload.UserImageExt,
  },
  cards: {
    picture_limit: master.Cards.PictureLimit,
    tag_limit: master.Cards.TagLimit,
    approve: master.Cards.Approve,
  },
  comments: { approve: master.Comments.Approve },
})
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback

const loadConfig = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const converted = toFrontendFormat(await client.config.list())
    systemConfig.value = CommonUtils.deepClone(converted)
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
    await client.config.update(toBackendSite(systemConfig.value.system))
    originConfig.value.system = CommonUtils.deepClone(systemConfig.value.system)
    siteMessage.value = { type: 'success', text: '基本信息已保存。' }
  } catch (cause) {
    siteMessage.value = { type: 'error', text: errorMessage(cause, '基本信息保存失败，请稍后重试。') }
  } finally {
    siteSaving.value = false
  }
}
const saveRules = async () => {
  if (!canUpdateConfig.value || !rulesChanged.value || rulesSaving.value) return
  rulesSaving.value = true
  rulesMessage.value = null
  try {
    await client.config.update(toBackendRules(systemConfig.value.master))
    originConfig.value.master = CommonUtils.deepClone(systemConfig.value.master)
    rulesMessage.value = { type: 'success', text: '社区规则已保存。' }
  } catch (cause) {
    rulesMessage.value = { type: 'error', text: errorMessage(cause, '社区规则保存失败，请稍后重试。') }
  } finally {
    rulesSaving.value = false
  }
}

onMounted(() => {
  void loadConfig()
})
</script>
