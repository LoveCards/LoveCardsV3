<template>
  <AdminPageContainer>
    <AdminPageHeader
      title="社区规则"
      description="维护上传限制与内容发布审核规则。保存后会影响前台内容发布流程。"
      :breadcrumbs="[{ title: '站点设置' }]"
    />

    <AdminAsyncState
      :error="loadError"
      error-title="配置加载失败"
      :loading="loading"
      skeleton-type="heading, paragraph, text@8"
      @retry="loadConfig"
    >
      <v-alert v-if="rulesMessage" class="mb-4" :type="rulesMessage.type" variant="tonal">
        {{ rulesMessage.text }}
      </v-alert>
      <v-alert class="mb-4" type="info" variant="tonal">
        审核开关和上传限制会直接影响新内容发布，请在低峰期调整并观察审核队列。
      </v-alert>

      <AdminConfigSection
        class="mb-4"
        :dirty="uploadChanged"
        description="头像与资料封面、卡片与评论图片分开设限；任一文件还要通过所用存储渠道自身的大小与格式限制。"
        icon="mdi-upload"
        title="上传限制"
      >
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-number-input
              v-model="rulesConfig.upload.avatarSize"
              control-variant="split"
              :disabled="!canUpdateConfig || rulesSaving"
              density="comfortable"
              hint="适用于头像与资料封面"
              label="头像图片大小上限（MB）"
              :min="1"
              persistent-hint
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="rulesConfig.upload.avatarExt"
              :disabled="!canUpdateConfig || rulesSaving"
              density="comfortable"
              hint="多个扩展名使用逗号分隔"
              label="头像允许的图片格式"
              persistent-hint
              placeholder="jpg,png,gif,webp"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-number-input
              v-model="rulesConfig.upload.contentSize"
              control-variant="split"
              :disabled="!canUpdateConfig || rulesSaving"
              density="comfortable"
              hint="适用于卡片图片、评论图片与直传"
              label="内容图片大小上限（MB）"
              :min="1"
              persistent-hint
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              v-model="rulesConfig.upload.contentExt"
              :disabled="!canUpdateConfig || rulesSaving"
              density="comfortable"
              hint="多个扩展名使用逗号分隔"
              label="内容允许的图片格式"
              persistent-hint
              placeholder="jpg,png,gif,webp"
              variant="outlined"
            />
          </v-col>
        </v-row>
      </AdminConfigSection>

      <AdminConfigSection
        class="mb-4"
        :dirty="cardsChanged"
        description="卡片图集与标签数量上限，以及新卡片是否需要人工审核。"
        icon="mdi-card-text-outline"
        title="卡片"
      >
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-number-input
              v-model="rulesConfig.cards.pictureLimit"
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
              v-model="rulesConfig.cards.tagLimit"
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
              v-model="rulesConfig.cards.approve"
              :disabled="!canUpdateConfig || rulesSaving"
              density="comfortable"
              item-title="title"
              item-value="value"
              :items="switchItems"
              label="卡片发布审核"
              variant="outlined"
            />
          </v-col>
        </v-row>
      </AdminConfigSection>

      <AdminConfigSection
        :dirty="commentsChanged"
        description="新评论是否需要人工审核后才对外可见。"
        icon="mdi-comment-outline"
        title="评论"
      >
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="rulesConfig.comments.approve"
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
      </AdminConfigSection>

      <AdminConfigSaveBar class="mt-4" :dirty="rulesChanged" label="社区规则">
        <AdminActionGuard capability="config.update" scope="global">
          <v-btn
            color="primary"
            :disabled="!rulesChanged"
            :loading="rulesSaving"
            prepend-icon="mdi-content-save-outline"
            variant="flat"
            @click="saveRules"
          >
            保存社区规则
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
import { readBoolean, readNumber, readObject, readString } from '~/utils/json'

interface UploadSettings {
  avatarSize: number
  avatarExt: string
  contentSize: number
  contentExt: string
}
interface CardSettings {
  pictureLimit: number
  tagLimit: number
  approve: boolean
}
interface CommentSettings {
  approve: boolean
}
interface RulesConfig {
  upload: UploadSettings
  cards: CardSettings
  comments: CommentSettings
}
type ActionMessage = { type: 'success' | 'error'; text: string }

const client = useApi()
const userStore = useUserStore()
const loading = ref(false)
const loadError = ref('')
const rulesSaving = ref(false)
const rulesMessage = ref<ActionMessage | null>(null)
const canUpdateConfig = computed(() => userStore.hasGrant('config.update', 'global'))
const switchItems = [
  { title: '开启', value: true },
  { title: '关闭', value: false },
]

const emptyConfig: RulesConfig = {
  upload: {
    avatarSize: 2,
    avatarExt: '',
    contentSize: 2,
    contentExt: '',
  },
  cards: {
    pictureLimit: 15,
    tagLimit: 3,
    approve: false,
  },
  comments: {
    approve: false,
  },
}
const rulesConfig = ref<RulesConfig>(CommonUtils.deepClone(emptyConfig))
const originConfig = ref<RulesConfig>(CommonUtils.deepClone(emptyConfig))
const uploadChanged = computed(() => JSON.stringify(rulesConfig.value.upload) !== JSON.stringify(originConfig.value.upload))
const cardsChanged = computed(() => JSON.stringify(rulesConfig.value.cards) !== JSON.stringify(originConfig.value.cards))
const commentsChanged = computed(() => JSON.stringify(rulesConfig.value.comments) !== JSON.stringify(originConfig.value.comments))
const rulesChanged = computed(() => uploadChanged.value || cardsChanged.value || commentsChanged.value)
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(rulesChanged)

// 场景新键未配置时回落到旧全局键，与后端 UploadLimits 的读时回落链保持一致。
const toFrontendFormat = (raw: JsonObject): RulesConfig => {
  const upload = readObject(raw.upload)
  const cards = readObject(raw.cards)
  const comments = readObject(raw.comments)
  const legacySize = readNumber(upload.user_image_size, 2)
  const legacyExt = readString(upload.user_image_ext)
  return {
    upload: {
      avatarSize: readNumber(upload.avatar_size, legacySize),
      avatarExt: readString(upload.avatar_ext) || legacyExt,
      contentSize: readNumber(upload.content_size, legacySize),
      contentExt: readString(upload.content_ext) || legacyExt,
    },
    cards: {
      pictureLimit: readNumber(cards.picture_limit, 15),
      tagLimit: readNumber(cards.tag_limit, 3),
      approve: readBoolean(cards.approve),
    },
    comments: {
      approve: readBoolean(comments.approve),
    },
  }
}
const toBackendRules = (config: RulesConfig): JsonObject => ({
  upload: {
    avatar_size: config.upload.avatarSize,
    avatar_ext: config.upload.avatarExt,
    content_size: config.upload.contentSize,
    content_ext: config.upload.contentExt,
  },
  cards: {
    picture_limit: config.cards.pictureLimit,
    tag_limit: config.cards.tagLimit,
    approve: config.cards.approve,
  },
  comments: {
    approve: config.comments.approve,
  },
})
const errorMessage = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : fallback

const loadConfig = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const converted = toFrontendFormat(await client.config.list())
    rulesConfig.value = CommonUtils.deepClone(converted)
    originConfig.value = CommonUtils.deepClone(converted)
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载社区规则配置，请稍后重试。')
  } finally {
    loading.value = false
  }
}
const saveRules = async () => {
  if (!canUpdateConfig.value || !rulesChanged.value || rulesSaving.value) return
  rulesSaving.value = true
  rulesMessage.value = null
  try {
    await client.config.update(toBackendRules(rulesConfig.value))
    originConfig.value = CommonUtils.deepClone(rulesConfig.value)
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
