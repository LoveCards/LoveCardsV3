<template>
  <div class="site-policy-panel">

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
              label="内容文件大小上限（MB）"
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
              label="内容允许的扩展名"
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
        class="mb-4"
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

      <AdminConfigSection
        class="mb-4"
        :dirty="tagsChanged"
        description="控制普通用户在发布卡片时能否创建新标签，以及新标签是否需要先审核；持有后台访问能力的管理员不受这两个开关限制。"
        icon="mdi-tag-outline"
        title="标签"
      >
        <v-row dense>
          <v-col cols="12" sm="6">
            <v-select
              v-model="rulesConfig.tags.allowUserCreate"
              :disabled="!canUpdateConfig || rulesSaving"
              density="comfortable"
              hint="关闭后，用户发布卡片时只能使用已有标签"
              item-title="title"
              item-value="value"
              :items="switchItems"
              label="允许用户创建新标签"
              persistent-hint
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              v-model="rulesConfig.tags.reviewNew"
              :disabled="!canUpdateConfig || rulesSaving"
              density="comfortable"
              hint="开启后，用户新标签进入待审核状态，在标签管理中放行后才公开"
              item-title="title"
              item-value="value"
              :items="switchItems"
              label="新用户标签需先审核"
              persistent-hint
              variant="outlined"
            />
          </v-col>
        </v-row>
      </AdminConfigSection>

      <AdminConfigSection class="mb-5" title="当前生效的上传规则" description="以下为已保存的站点限制与当前存储渠道共同求得的上限。">
        <v-list v-if="uploadPolicy" density="compact">
          <v-list-item v-for="(label, scene) in uploadScenes" :key="scene" :title="label">
            <p class="text-body-2 text-medium-emphasis text-break">{{ uploadSummary(scene) }}</p>
          </v-list-item>
        </v-list>
        <p v-else class="text-body-2">暂时无法读取当前上传规则。</p>
        <p v-if="uploadPolicy" class="text-body-2 mb-2">每张卡片最多 {{ uploadPolicy.card_media_limit }} 个媒体文件</p>
        <NuxtLink v-if="userStore.canAccessPath('/apps/settings/storage')" to="/apps/settings/storage">查看存储渠道限制</NuxtLink>
      </AdminConfigSection>

      <AdminConfigSaveBar class="mt-4" :dirty="rulesChanged" label="内容规则">
        <AdminActionGuard capability="config.update" scope="global">
          <v-btn
            color="primary"
            :disabled="!rulesChanged"
            :loading="rulesSaving"
            prepend-icon="mdi-content-save-outline"
            variant="flat"
            @click="saveRules"
          >
            保存内容规则
          </v-btn>
        </AdminActionGuard>
      </AdminConfigSaveBar>
    </AdminAsyncState>

    <AdminUnsavedChangesDialog
      :model-value="unsavedChangesDialog"
      @stay="continueEditing"
      @discard="discardChanges"
    />
  </div>
</template>

<script setup lang="ts">
import type { UploadPolicy } from '@lovecards/sdk'
import type { JsonObject } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminConfigSaveBar from '~/components/shared/admin/AdminConfigSaveBar.vue'
import AdminConfigSection from '~/components/shared/admin/AdminConfigSection.vue'
import AdminUnsavedChangesDialog from '~/components/shared/admin/AdminUnsavedChangesDialog.vue'
import { useApi } from '~/lib/api'
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
interface TagSettings {
  allowUserCreate: boolean
  reviewNew: boolean
}
interface RulesConfig {
  upload: UploadSettings
  cards: CardSettings
  comments: CommentSettings
  tags: TagSettings
}
type ActionMessage = { type: 'success' | 'error'; text: string }

const client = useApi()
const uploadPolicy = ref<UploadPolicy | null>(null)
const uploadScenes = { avatar: '头像', profile_cover: '主页背景', card_image: '卡片图片', card_video: '卡片视频', comment: '评论附件', direct: '直传' } as const
function uploadSummary(scene: keyof typeof uploadScenes) {
  const rule = uploadPolicy.value?.scenes[scene]
  if (!rule?.available) return '当前渠道不支持该方式'
  const source = rule.storage_max_bytes !== null && rule.storage_max_bytes < rule.site_max_bytes ? '存储渠道' : '站点规则'
  const size = (rule.max_bytes / 1048576).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
  return [`最多 ${size} MiB（${source}）`, rule.extensions.join('、'), rule.mime_patterns.join('、')].filter(Boolean).join(' · ')
}
async function loadUploadPolicy() {
  try {
    const policy = await client.files.managedUploadPolicy()
    uploadPolicy.value = policy?.scenes ? policy : null
  } catch {
    uploadPolicy.value = null
  }
}
const configSnapshot = useConfigSnapshot(client)
const userStore = useUserStore()
const props = withDefaults(defineProps<{ active?: boolean }>(), { active: true })
const emit = defineEmits<{ 'dirty-change': [dirty: boolean] }>()
const loading = ref(false)
const loaded = ref(false)
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
  tags: {
    allowUserCreate: true,
    reviewNew: false,
  },
}
const rulesConfig = ref<RulesConfig>(CommonUtils.deepClone(emptyConfig))
const originConfig = ref<RulesConfig>(CommonUtils.deepClone(emptyConfig))
const uploadChanged = computed(() => JSON.stringify(rulesConfig.value.upload) !== JSON.stringify(originConfig.value.upload))
const cardsChanged = computed(() => JSON.stringify(rulesConfig.value.cards) !== JSON.stringify(originConfig.value.cards))
const commentsChanged = computed(() => JSON.stringify(rulesConfig.value.comments) !== JSON.stringify(originConfig.value.comments))
const tagsChanged = computed(() => JSON.stringify(rulesConfig.value.tags) !== JSON.stringify(originConfig.value.tags))
const rulesChanged = computed(() => uploadChanged.value || cardsChanged.value || commentsChanged.value || tagsChanged.value)
watch(rulesChanged, value => emit('dirty-change', value), { immediate: true })
const { unsavedChangesDialog, continueEditing, discardChanges } = useAdminUnsavedChanges(rulesChanged)

// 场景新键未配置时回落到旧全局键，与后端 UploadLimits 的读时回落链保持一致。
const toFrontendFormat = (raw: JsonObject): RulesConfig => {
  const upload = readObject(raw.upload)
  const cards = readObject(raw.cards)
  const comments = readObject(raw.comments)
  const tags = readObject(raw.tags)
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
    // 新键未注册时默认保持现行行为：允许创建、不审核。
    tags: {
      allowUserCreate: readBoolean(tags.allow_user_create, true),
      reviewNew: readBoolean(tags.review_new_tags),
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
  tags: {
    allow_user_create: config.tags.allowUserCreate,
    review_new_tags: config.tags.reviewNew,
  },
})
const errorMessage = configSnapshot.errorMessage

const loadConfig = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const converted = toFrontendFormat(await configSnapshot.list())
    rulesConfig.value = CommonUtils.deepClone(converted)
    originConfig.value = CommonUtils.deepClone(converted)
    loaded.value = true
    void loadUploadPolicy()
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载内容规则配置，请稍后重试。')
  } finally {
    loading.value = false
  }
}
const saveRules = async () => {
  if (!canUpdateConfig.value || !rulesChanged.value || rulesSaving.value) return
  rulesSaving.value = true
  rulesMessage.value = null
  try {
    const committed = await configSnapshot.update(toBackendRules(rulesConfig.value))
    rulesConfig.value = toFrontendFormat(committed)
    originConfig.value = CommonUtils.deepClone(rulesConfig.value)
    rulesMessage.value = { type: 'success', text: '内容规则已保存。' }
    void loadUploadPolicy()
  } catch (cause) {
    rulesMessage.value = { type: 'error', text: errorMessage(cause, '内容规则保存失败，请稍后重试。') }
  } finally {
    rulesSaving.value = false
  }
}

onMounted(() => {
  if (props.active) void loadConfig()
})
watch(() => props.active, (active) => {
  if (active && !loaded.value && !loading.value) void loadConfig()
})
</script>
