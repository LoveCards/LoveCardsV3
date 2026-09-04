<template>
  <v-dialog v-model="thisDialogState" :aria-label="panelTitle" max-width="1040" :persistent="saving || uploadingImages" scrollable>
    <v-card prepend-icon="mdi-file-edit-outline" :title="panelTitle">
      <v-card-text class="pt-4">
        <AdminAsyncState
          :error="loadError"
          error-title="卡片加载失败"
          :loading="loading"
          skeleton-type="list-item-two-line@3, text@3"
          @retry="loadCard"
        >
          <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">
            {{ formError }}
          </v-alert>

          <v-list bg-color="surface-variant" class="mb-4" density="compact" rounded="lg">
            <v-row class="ma-0" dense>
              <v-col cols="12" sm="6" md="4">
                <v-list-item prepend-icon="mdi-account-outline" subtitle="用户 ID" :title="displayValue(CardData.edit.user_id)" />
              </v-col>
              <v-col cols="12" sm="6" md="4">
                <v-list-item prepend-icon="mdi-clock-outline" subtitle="发布时间" :title="displayValue(CardData.edit.created_at)" />
              </v-col>
              <v-col cols="12" sm="6" md="4">
                <v-list-item prepend-icon="mdi-ip-network-outline" subtitle="发布 IP" :title="displayValue(CardData.edit.post_ip)" />
              </v-col>
              <v-col cols="12" sm="6" md="4">
                <v-list-item prepend-icon="mdi-heart-outline" subtitle="获赞数" :title="displayValue(CardData.edit.goods)" />
              </v-col>
              <v-col cols="12" sm="6" md="4">
                <v-list-item prepend-icon="mdi-eye-outline" subtitle="浏览数" :title="displayValue(CardData.edit.views)" />
              </v-col>
              <v-col cols="12" sm="6" md="4">
                <v-list-item prepend-icon="mdi-comment-outline" subtitle="评论数" :title="displayValue(CardData.edit.comments)" />
              </v-col>
            </v-row>
          </v-list>

          <v-alert v-if="panelMode === 'view'" class="mb-4" density="compact" type="info" variant="tonal">
            当前账号只有查看权限，字段保持只读。
          </v-alert>

          <v-row dense>
            <v-col cols="12">
              <v-select
                v-model="CardData.edit.tags"
                chips
                clearable
                :disabled="!canUpdateContent || saving"
                density="comfortable"
                item-title="name"
                item-value="id"
                :items="viewTags"
                label="标签"
                multiple
                variant="outlined"
              />
            </v-col>
            <v-col v-if="canPinCard" cols="12" sm="6">
              <v-select
                v-model="CardData.edit.is_top"
                :disabled="saving"
                density="comfortable"
                item-title="title"
                item-value="value"
                :items="SelectUtils.Cards.top"
                label="置顶状态"
                variant="outlined"
              />
            </v-col>
            <v-col v-if="canApproveCard" cols="12" sm="6">
              <v-select
                v-model="CardData.edit.status"
                :disabled="saving"
                density="comfortable"
                item-title="title"
                item-value="value"
                :items="SelectUtils.Cards.status"
                label="审核状态"
                variant="outlined"
              />
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="CardData.edit.content"
                auto-grow
                :disabled="!canUpdateContent || saving"
                density="comfortable"
                label="卡片正文"
                rows="5"
                variant="outlined"
              />
            </v-col>
          </v-row>

          <v-expansion-panels v-if="hasCustomData" class="mb-4" variant="accordion">
            <v-expansion-panel title="查看扩展字段（只读）">
              <v-expansion-panel-text>
                <pre class="ma-0 text-body-2 text-wrap">{{ displayJson(CardData.edit.data) }}</pre>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>

          <v-divider class="mb-4" />
          <div class="mb-3 d-flex align-center justify-space-between ga-3">
            <div>
              <h3 class="text-subtitle-1 font-weight-medium">卡片图片</h3>
              <p class="mb-0 text-body-2 text-medium-emphasis">第一张图片会作为封面；已有视频会保留，当前仅支持增删图片。</p>
            </div>
            <v-chip size="small" variant="outlined">{{ CardMediaData.edit.length }} 项</v-chip>
          </div>

          <v-alert v-if="uploadError" class="mb-4" role="alert" type="error" variant="tonal">
            {{ uploadError }}
          </v-alert>
          <v-alert v-if="hasUnboundMedia" class="mb-4" role="alert" type="warning" variant="tonal">
            部分旧媒体缺少可验证的文件 ID，媒体增删已锁定；仍可保存正文、标签或审核字段。请先完成媒体迁移后再调整媒体集合。
          </v-alert>
          <v-row dense class="mb-2">
            <v-col v-for="(item, index) in CardMediaData.edit" :key="`${item.fileId ?? 'legacy'}:${index}`" cols="6" sm="4" md="3">
              <v-sheet border rounded="sm">
                <v-img v-if="item.type === 'image'" :alt="`卡片图片 ${index + 1}`" :src="item.url" aspect-ratio="1" cover>
                  <div class="d-flex justify-end pa-1">
                    <v-progress-circular v-if="item.fileId === 0" color="primary" indeterminate size="28" />
                    <v-btn
                      v-else-if="canUpdateContent"
                      :aria-label="`删除第 ${index + 1} 张图片`"
                      color="surface"
                      :disabled="hasUnboundMedia"
                      density="comfortable"
                      icon="mdi-close"
                      size="small"
                      variant="flat"
                      @click="removeCardImage(index)"
                    />
                  </div>
                </v-img>
                <div v-else class="d-flex flex-column align-center justify-center pa-4 text-medium-emphasis media-placeholder">
                  <v-icon icon="mdi-video-outline" size="36" />
                  <span class="mt-2 text-caption">视频媒体</span>
                </div>
              </v-sheet>
            </v-col>
          </v-row>
          <v-file-input
            v-if="canUpdateContent && canUploadFiles"
            v-model="viewCardFiles"
            accept="image/*"
            :disabled="hasUnboundMedia || saving || uploadingImages"
            density="comfortable"
            label="上传图片"
            :loading="uploadingImages"
            multiple
            prepend-icon="mdi-image-plus-outline"
            show-size
            variant="outlined"
            @update:model-value="uploadSelectedImages"
          />
          <v-alert v-else-if="canUpdateContent" density="compact" type="info" variant="tonal">
            当前账号没有上传文件权限；仍可编辑正文、标签或移除已有图片。
          </v-alert>

        </AdminAsyncState>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn :disabled="saving || uploadingImages" variant="text" @click="thisDialogState = false">
          {{ canEditCard ? '取消' : '关闭' }}
        </v-btn>
        <v-btn
          v-if="canEditCard"
          color="primary"
          :disabled="loading || Boolean(loadError) || uploadingImages || !canEditCard"
          :loading="saving"
          variant="flat"
          @click="submit"
        >
          {{ panelActionLabel }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import type { Card } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import { isApiError, useApi } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'
import SelectUtils from '~/utils/select'

interface ManagedMedia {
  fileId: number | null
  url: string
  type: 'image' | 'video'
}
type ManagedCard = Omit<Card, 'tags'> & { tags: number[] }
type CardUpdateInput = {
  media_file_ids?: number[]
  cover_file_id?: number
  content?: string
  is_top?: number
  status?: number
  tags?: number[]
}

const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const props = defineProps<{ getTableData?: () => unknown }>()
const tagsStore = useTagsStore()
const viewTags = computed(() => Array.isArray(tagsStore.tags) ? tagsStore.tags : [])
const thisDialogState = defineModel<boolean>('thisDialogState', { default: false })
const editCardData = defineModel<Card | null>('editCardData')
const emptyCard = {} as ManagedCard
const CardData = ref<{ edit: ManagedCard; origin: ManagedCard }>({ edit: emptyCard, origin: emptyCard })
const CardMediaData = ref<{ edit: ManagedMedia[]; origin: ManagedMedia[] }>({ edit: [], origin: [] })
const viewCardFiles = ref<File[]>([])
const loading = ref(false)
const saving = ref(false)
const uploadingImages = ref(false)
const loadError = ref('')
const formError = ref('')
const uploadError = ref('')
const canUpdateContent = computed(() => userStore.hasGrant('cards.update', 'all'))
const canPinCard = computed(() => userStore.hasGrant('cards.pin', 'all'))
const canApproveCard = computed(() => userStore.hasGrant('cards.approve', 'all'))
const canUploadFiles = computed(() => userStore.hasGrant('files.upload', 'global'))
const canEditCard = computed(() => canUpdateContent.value || canPinCard.value || canApproveCard.value)
const panelMode = computed<'view' | 'review' | 'edit'>(() => {
  if (canUpdateContent.value) return 'edit'
  if (canApproveCard.value || canPinCard.value) return 'review'
  return 'view'
})
const panelTitle = computed(() => panelMode.value === 'edit' ? '编辑卡片' : panelMode.value === 'review' ? '审核卡片' : '查看卡片')
const panelActionLabel = computed(() => panelMode.value === 'edit' ? '保存修改' : '提交审核')
const hasUnboundMedia = computed(() => CardMediaData.value.edit.some(item => item.fileId === null))

const hasCustomData = computed(() => Object.keys(CardData.value.edit.data || {}).length > 0)
const displayValue = (value: unknown) => value === undefined || value === null || value === '' ? '—' : String(value)
const displayJson = (value: unknown) => {
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return displayValue(value)
  }
}
const errorMessage = (cause: unknown, fallback: string) => {
  if (isApiError(cause)) return cause.message
  return cause instanceof Error && cause.message ? cause.message : fallback
}
const normalizeStringArray = (value: unknown): string[] => {
  if (Array.isArray(value)) return value.filter((item): item is string => typeof item === 'string')
  if (typeof value !== 'string' || value.trim() === '') return []
  try {
    return normalizeStringArray(JSON.parse(value))
  } catch {
    return []
  }
}
const positiveId = (value: unknown): number | null => {
  const normalized = typeof value === 'string' && /^\d+$/.test(value.trim()) ? Number(value) : value
  return typeof normalized === 'number' && Number.isSafeInteger(normalized) && normalized > 0 ? normalized : null
}
const normalizeTagIds = (card: Card): number[] => {
  const topicIds = Array.isArray(card.topics)
    ? card.topics.map(topic => positiveId(topic?.id)).filter((id): id is number => id !== null)
    : []
  if (topicIds.length > 0) return [...new Set(topicIds)]

  let tags: unknown = card.tags
  if (typeof tags === 'string') {
    try {
      tags = JSON.parse(tags)
    } catch {
      tags = [tags]
    }
  }
  if (!Array.isArray(tags)) return []
  return [...new Set(tags.map(positiveId).filter((id): id is number => id !== null))]
}
const hydrateMedia = (card: Card): ManagedMedia[] => {
  const media = Array.isArray(card.media)
    ? [...card.media].sort((left, right) => left.position - right.position)
    : []
  if (media.length > 0) {
    return media.map(item => ({
      fileId: positiveId(item.file_id),
      url: item.url,
      type: item.type === 'video' ? 'video' : 'image',
    }))
  }

  // 旧数据只有 URL 时仅用于展示；没有可验证 file_id 时绝不猜测或提交媒体绑定。
  return normalizeStringArray(card.pictures).map(url => ({ fileId: null, url, type: 'image' }))
}

const loadCard = async () => {
  if (!editCardData.value) return
  loading.value = true
  loadError.value = ''
  formError.value = ''
  uploadError.value = ''
  viewCardFiles.value = []
  CardMediaData.value = { edit: [], origin: [] }
  try {
    const card = await client.cards.getManaged(editCardData.value.id)
    const normalized: ManagedCard = { ...card, tags: normalizeTagIds(card) }
    CardData.value = {
      edit: CommonUtils.deepClone(normalized),
      origin: CommonUtils.deepClone(normalized),
    }
    const media = hydrateMedia(card)
    CardMediaData.value.edit = CommonUtils.deepClone(media)
    CardMediaData.value.origin = CommonUtils.deepClone(media)
  } catch (cause) {
    loadError.value = errorMessage(cause, '无法加载卡片详情，请稍后重试。')
  } finally {
    loading.value = false
  }
}

const uploadSelectedImages = async (value: File | File[] | null) => {
  if (!canUpdateContent.value || !canUploadFiles.value || hasUnboundMedia.value) return
  const files = (Array.isArray(value) ? value : value ? [value] : []).filter(file => file.type.startsWith('image/'))
  if (files.length === 0) return
  uploadingImages.value = true
  uploadError.value = ''
  try {
    await Promise.all(files.map(async (file) => {
      const previewUrl = URL.createObjectURL(file)
      const image: ManagedMedia = { fileId: 0, url: previewUrl, type: 'image' }
      CardMediaData.value.edit.push(image)
      try {
        const formData = new FormData()
        formData.append('file', file)
        const result = await client.files.upload(formData)
        image.fileId = result.id
        image.url = result.url
      } catch (cause) {
        const index = CardMediaData.value.edit.indexOf(image)
        if (index >= 0) CardMediaData.value.edit.splice(index, 1)
        throw cause
      } finally {
        URL.revokeObjectURL(previewUrl)
      }
    }))
    viewCardFiles.value = []
  } catch (cause) {
    uploadError.value = errorMessage(cause, '部分图片上传失败，请重新选择。')
  } finally {
    uploadingImages.value = false
  }
}

const removeCardImage = (index: number) => {
  if (!canUpdateContent.value || hasUnboundMedia.value) return
  if (index >= 0 && index < CardMediaData.value.edit.length) {
    CardMediaData.value.edit.splice(index, 1)
  }
}

const patchCard = async () => {
  const editData = CommonUtils.deepClone(CardData.value.edit)
  const originData = CommonUtils.deepClone(CardData.value.origin)
  const editInput: CardUpdateInput = {}
  const originInput: CardUpdateInput = {}
  if (canUpdateContent.value) {
    editInput.content = editData.content
    editInput.tags = editData.tags ?? undefined
    originInput.content = originData.content
    originInput.tags = originData.tags ?? undefined
  }
  if (canPinCard.value) {
    editInput.is_top = editData.is_top
    originInput.is_top = originData.is_top
  }
  if (canApproveCard.value) {
    editInput.status = editData.status
    originInput.status = originData.status
  }
  const params = CommonUtils.removeCommonProperties(editInput, originInput)
  const originMediaIds = CardMediaData.value.origin.map(item => item.fileId)
  const editMediaIds = CardMediaData.value.edit.map(item => item.fileId)
  if (canUpdateContent.value && JSON.stringify(originMediaIds) !== JSON.stringify(editMediaIds)) {
    if (editMediaIds.some(id => id === null || id <= 0)) {
      throw new Error('媒体集合包含缺失或无效的文件 ID，已阻止保存以保护现有媒体。')
    }
    params.media_file_ids = editMediaIds as number[]
    const cover = CardMediaData.value.edit.find(item => item.type === 'image')
    params.cover_file_id = positiveId(cover?.fileId) ?? 0
  }
  if (Object.keys(params).length === 0) {
    notifier.warning('请修改后再提交')
    return false
  }
  await client.cards.updateManaged(editData.id, params as Parameters<typeof client.cards.updateManaged>[1])
  return true
}

const submit = async () => {
  if (saving.value) return
  saving.value = true
  formError.value = ''
  try {
    const changed = await patchCard()
    if (!changed) return
    thisDialogState.value = false
    await props.getTableData?.()
  } catch (cause) {
    formError.value = errorMessage(cause, '卡片保存失败，请检查字段后重试。')
  } finally {
    saving.value = false
  }
}

watch(thisDialogState, (open) => {
  if (open) {
    void loadCard()
  }
})
</script>

<style scoped>
.text-wrap {
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.media-placeholder {
  aspect-ratio: 1;
}
</style>
