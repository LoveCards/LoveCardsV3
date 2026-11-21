<template>
  <v-card :border="!embedded" rounded="lg" variant="flat">
      <v-card-text class="pa-4 pa-sm-6">
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

          <div v-if="loaded" class="d-flex flex-wrap align-center ga-3 mb-4">
            <AdminContentStatus :status="CardData.origin.status" :pinned="Number(CardData.origin.is_top) === 1" />
            <span class="text-body-2 text-medium-emphasis">{{ visibilityLabel }}</span>
          </div>

          <section v-if="mode === 'view'" class="mb-5" aria-label="卡片内容">
            <p class="card-detail__body text-pre-wrap mb-3">{{ CardData.origin.content || '—' }}</p>
            <p class="text-body-2 text-medium-emphasis mb-0">标签：{{ tagNames || '—' }}</p>
            <div v-if="canEditCard" class="d-flex justify-end mt-4">
              <v-btn prepend-icon="mdi-pencil-outline" variant="tonal" @click="beginEdit">编辑内容</v-btn>
            </div>
          </section>
          <v-row v-else dense>
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



          <template v-if="CardMediaData.edit.length || mode === 'edit'">
          <v-divider class="mb-4" />
          <div class="mb-3 d-flex align-center justify-space-between ga-3">
            <div>
              <h3 class="text-subtitle-1 font-weight-medium">媒体</h3>
              <p v-if="mode === 'edit'" class="mb-0 text-body-2 text-medium-emphasis">第一张图片会作为封面；已有视频会保留，当前仅支持增删图片。</p>
            </div>
            <v-chip size="small" variant="outlined">{{ CardMediaData.edit.length }} 项</v-chip>
          </div>

          <v-alert v-if="uploadError" class="mb-4" role="alert" type="error" variant="tonal">
            {{ uploadError }}
          </v-alert>
          <v-alert v-if="mode === 'edit' && uploadedFileIds.length" class="mb-4" role="alert" type="warning" variant="tonal">
            本次已上传 {{ uploadedFileIds.length }} 个文件。放弃卡片修改不会撤销这些上传；可在文件管理中检查未引用文件。
          </v-alert>
          <v-alert v-if="mode === 'edit' && hasUnboundMedia" class="mb-4" role="alert" type="warning" variant="tonal">
            部分旧媒体缺少可验证的文件 ID，媒体增删已锁定；仍可保存正文或标签。请先完成媒体迁移后再调整媒体集合。
          </v-alert>
          <v-row dense class="mb-2">
            <v-col v-for="(item, index) in CardMediaData.edit" :key="`${item.fileId ?? 'legacy'}:${index}`" cols="6" sm="4" md="3">
              <v-sheet border rounded="sm">
                <v-img v-if="item.type === 'image'" :alt="`卡片图片 ${index + 1}`" :src="item.url" aspect-ratio="1" cover>
                  <div class="d-flex justify-end pa-1">
                    <v-progress-circular v-if="item.fileId === 0" color="primary" indeterminate size="28" />
                    <v-btn
                      v-else-if="mode === 'edit' && canUpdateContent"
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
                  <template #error>
                    <div class="d-flex flex-column align-center justify-center text-medium-emphasis media-placeholder">
                      <v-icon icon="mdi-image-off-outline" size="32" />
                      <span class="mt-2 text-caption">图片暂不可预览</span>
                    </div>
                  </template>
                </v-img>
                <div v-else class="d-flex flex-column align-center justify-center pa-4 text-medium-emphasis media-placeholder">
                  <v-icon icon="mdi-video-outline" size="36" />
                  <span class="mt-2 text-caption">视频媒体</span>
                </div>
              </v-sheet>
            </v-col>
          </v-row>
          <v-alert v-if="mode === 'edit' && canUpdateContent && canUploadFiles" class="mb-3" density="compact" type="info" variant="tonal">图片选择后立即上传；放弃卡片编辑不会撤销已经完成的文件上传。</v-alert>
          <v-file-input
            v-if="mode === 'edit' && canUpdateContent && canUploadFiles"
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
          <v-alert v-else-if="mode === 'edit' && canUpdateContent" density="compact" type="info" variant="tonal">
            当前账号没有上传文件权限；仍可编辑正文、标签或移除已有图片。
          </v-alert>
          </template>

          <v-divider class="my-5" />
          <h3 class="text-subtitle-1 font-weight-medium mb-3">发布与关联</h3>
          <v-row dense>
            <v-col cols="12" sm="6"><AdminDetailField label="作者"><AdminResourceLink resource-type="user" :resource-id="CardData.origin.user_id" :label="`${CardData.origin.username || '用户'} #${CardData.origin.user_id}`" /></AdminDetailField></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="发布时间" :value="displayValue(CardData.origin.created_at)" /></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="获赞数" :value="CardData.origin.goods" /></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="浏览数" :value="CardData.origin.views" /></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="评论数" :value="CardData.origin.comments" /></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="发布 IP" :value="displayValue(CardData.origin.post_ip)" /></v-col>
          </v-row>

          <v-expansion-panels v-if="hasCustomData" class="mt-5" variant="accordion">
            <v-expansion-panel title="查看扩展字段（只读）">
              <v-expansion-panel-text>
                <pre class="ma-0 text-body-2 text-wrap">{{ displayJson(CardData.edit.data) }}</pre>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>

          <section v-if="mode === 'view' && commands.length" class="mt-5">
            <v-divider class="mb-4" />
            <h3 class="text-subtitle-1 font-weight-medium mb-2">卡片治理</h3>
            <v-alert v-if="commandError" class="mb-3" role="alert" type="error" variant="tonal">{{ commandError }}</v-alert>
            <div class="d-flex flex-wrap ga-2">
              <v-btn v-for="command in commands" :key="command.method" :disabled="commandBusy" variant="outlined" @click="pendingCommand = command">{{ command.label }}</v-btn>
            </div>
            <v-alert v-if="pendingCommand" class="mt-4" type="warning" variant="tonal">
              {{ pendingCommand.impact }}确定对卡片 #{{ cardId }}执行“{{ pendingCommand.label }}”吗？
              <template #append>
                <v-btn :disabled="commandBusy" variant="text" @click="pendingCommand = null">取消</v-btn>
                <v-btn color="warning" :loading="commandBusy" variant="flat" @click="runCommand">{{ pendingCommand.label }}</v-btn>
              </template>
            </v-alert>
          </section>

        </AdminAsyncState>
      </v-card-text>

      <v-divider />
      <v-card-actions v-if="loaded && !loading && !loadError && mode === 'edit'" class="pa-4 pa-sm-6">
        <v-spacer />
        <v-btn v-if="mode === 'edit'" :disabled="saving || uploadingImages" variant="text" @click="cancelEdit">取消编辑</v-btn>
        <v-btn
          v-if="mode === 'edit' && canEditCard"
          color="primary"
          :disabled="loading || Boolean(loadError) || uploadingImages || !canEditCard || !dirty"
          :loading="saving"
          variant="flat"
          @click="submit"
        >
          保存修改
        </v-btn>
      </v-card-actions>
      <v-alert v-if="showDiscardConfirm" class="ma-4" type="warning" variant="tonal">
        当前修改尚未保存，确定放弃吗？
        <template #append>
          <v-btn variant="text" @click="showDiscardConfirm = false">继续编辑</v-btn>
          <v-btn color="warning" variant="flat" @click="discardEdit">放弃修改</v-btn>
        </template>
      </v-alert>
  </v-card>
</template>

<script setup lang="ts">
import type { Card } from '@lovecards/sdk'
import { cardGovernanceActions, type CardGovernanceAction } from '~/components/cards/cardGovernanceActions'
import AdminContentStatus from '~/components/content/AdminContentStatus.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminDetailField from '~/components/shared/admin/AdminDetailField.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import { isApiError, useApi } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { useUserStore } from '~/stores/userStore'
import CommonUtils from '~/utils/common'

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
  tags?: number[]
}

const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const props = withDefaults(defineProps<{ cardId: number; initialMode?: 'view' | 'edit'; embedded?: boolean }>(), { initialMode: 'view', embedded: false })
const emit = defineEmits<{ saved: []; 'dirty-change': [value: boolean]; 'busy-change': [value: boolean]; 'editing-change': [value: boolean] }>()
const tagsStore = useTagsStore()
const viewTags = computed(() => Array.isArray(tagsStore.tags) ? tagsStore.tags : [])
const emptyCard = {} as ManagedCard
const CardData = ref<{ edit: ManagedCard; origin: ManagedCard }>({ edit: emptyCard, origin: emptyCard })
const CardMediaData = ref<{ edit: ManagedMedia[]; origin: ManagedMedia[] }>({ edit: [], origin: [] })
const viewCardFiles = ref<File[]>([])
const loading = ref(false)
const loaded = ref(false)
const saving = ref(false)
const uploadingImages = ref(false)
const loadError = ref('')
const formError = ref('')
const uploadError = ref('')
const uploadedFileIds = ref<number[]>([])
const commandError = ref('')
const commandBusy = ref(false)
const pendingCommand = ref<CardGovernanceAction | null>(null)
let latestLoad = 0
const mode = ref<'view' | 'edit'>('view')
const showDiscardConfirm = ref(false)
const emitDirtyChange = (value: boolean) => emit('dirty-change', value)
const canUpdateContent = computed(() => userStore.hasGrant('cards.update', 'all'))
const canPinCard = computed(() => userStore.hasGrant('cards.pin', 'all'))
const canApproveCard = computed(() => userStore.hasGrant('cards.approve', 'all'))
const canUploadFiles = computed(() => userStore.hasGrant('files.upload', 'global'))
const canEditCard = canUpdateContent
const commands = computed<CardGovernanceAction[]>(() => {
  if (!loaded.value) return []
  return cardGovernanceActions(CardData.value.origin, { approve: canApproveCard.value, update: canUpdateContent.value, pin: canPinCard.value })
})
const visibilityLabel = computed(() => ({ public: '公开', followers: '仅关注者', private: '仅自己' })[CardData.value.origin.visibility ?? 'public'])
const tagNames = computed(() => (CardData.value.origin.tags ?? []).map(id => viewTags.value.find(tag => Number(tag.id) === id)?.name ?? `#${id}`).join('、'))
const hasUnboundMedia = computed(() => CardMediaData.value.edit.some(item => item.fileId === null))
const dirty = computed(() => mode.value === 'edit' && (JSON.stringify({ content: CardData.value.edit.content, tags: CardData.value.edit.tags }) !== JSON.stringify({ content: CardData.value.origin.content, tags: CardData.value.origin.tags }) || JSON.stringify(CardMediaData.value.edit.map(item => item.fileId)) !== JSON.stringify(CardMediaData.value.origin.map(item => item.fileId))))
const reportUnsavedUpload = () => {
  if (!uploadedFileIds.value.length) return
  notifier.warning(`本次上传的 ${uploadedFileIds.value.length} 个文件可能尚未被卡片引用，可在文件管理中检查。`)
  uploadedFileIds.value = []
}
watch(dirty, emitDirtyChange, { immediate: true })
watch(mode, value => emit('editing-change', value === 'edit'), { immediate: true })
watch(() => saving.value || uploadingImages.value || commandBusy.value, value => emit('busy-change', value), { immediate: true })
const beginEdit = () => { if (canEditCard.value) mode.value = 'edit' }
const discardEdit = () => {
  reportUnsavedUpload()
  CardData.value.edit = CommonUtils.deepClone(CardData.value.origin)
  CardMediaData.value.edit = CommonUtils.deepClone(CardMediaData.value.origin)
  viewCardFiles.value = []
  formError.value = ''
  uploadError.value = ''
  commandError.value = ''
  showDiscardConfirm.value = false
  mode.value = 'view'
}
const cancelEdit = () => { if (dirty.value) showDiscardConfirm.value = true; else discardEdit() }

const hasCustomData = computed(() => loaded.value && Object.keys(CardData.value.edit.data || {}).length > 0)
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
  const request = ++latestLoad
  if (!Number.isSafeInteger(props.cardId) || props.cardId <= 0) {
    loadError.value = '卡片 ID 无效。'
    loaded.value = false
    return
  }
  loading.value = true
  loaded.value = false
  const requestedId = props.cardId
  loadError.value = ''
  formError.value = ''
  uploadError.value = ''
  viewCardFiles.value = []
  CardMediaData.value = { edit: [], origin: [] }
  try {
    const card = await client.cards.getManaged(props.cardId)
    if (request !== latestLoad || requestedId !== props.cardId) return
    const normalized: ManagedCard = { ...card, tags: normalizeTagIds(card) }
    CardData.value = {
      edit: CommonUtils.deepClone(normalized),
      origin: CommonUtils.deepClone(normalized),
    }
    const media = hydrateMedia(card)
    CardMediaData.value.edit = CommonUtils.deepClone(media)
    CardMediaData.value.origin = CommonUtils.deepClone(media)
    loaded.value = true
  } catch (cause) {
    if (request === latestLoad && requestedId === props.cardId) loadError.value = errorMessage(cause, '无法加载卡片详情，请稍后重试。')
  } finally {
    if (request === latestLoad && requestedId === props.cardId) loading.value = false
  }
}

const uploadSelectedImages = async (value: File | File[] | null) => {
  if (!canUpdateContent.value || !canUploadFiles.value || hasUnboundMedia.value) return
  const files = (Array.isArray(value) ? value : value ? [value] : []).filter(file => file.type.startsWith('image/'))
  if (files.length === 0) return
  viewCardFiles.value = []
  uploadingImages.value = true
  uploadError.value = ''
  const uploadCardId = props.cardId
  try {
    const results = await Promise.allSettled(files.map(async (file) => {
      const previewUrl = URL.createObjectURL(file)
      const image: ManagedMedia = { fileId: 0, url: previewUrl, type: 'image' }
      CardMediaData.value.edit.push(image)
      try {
        const formData = new FormData()
        formData.append('file', file)
        const result = await client.files.upload(formData)
        if (uploadCardId !== props.cardId) {
          uploadedFileIds.value = [...uploadedFileIds.value, result.id]
          return
        }
        const currentIndex = CardMediaData.value.edit.findIndex(item => item.url === previewUrl && item.fileId === 0)
        if (currentIndex >= 0) CardMediaData.value.edit[currentIndex] = { fileId: result.id, url: result.url, type: 'image' }
        uploadedFileIds.value = [...uploadedFileIds.value, result.id]
      } catch (cause) {
        const index = CardMediaData.value.edit.findIndex(item => item.url === previewUrl && item.fileId === 0)
        if (index >= 0) CardMediaData.value.edit.splice(index, 1)
        throw cause
      } finally {
        URL.revokeObjectURL(previewUrl)
      }
    }))
    const failures = results.filter(result => result.status === 'rejected')
    if (failures.length > 0) throw new Error(`${failures.length} 张图片上传失败；已成功上传的图片仍在本次草稿中。`)
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
  if (saving.value || !canUpdateContent.value || !loaded.value) return
  saving.value = true
  formError.value = ''
  try {
    const changed = await patchCard()
    if (!changed) return
    const boundIds = new Set(CardMediaData.value.edit.map(item => item.fileId))
    uploadedFileIds.value = uploadedFileIds.value.filter(id => !boundIds.has(id))
    reportUnsavedUpload()
    mode.value = 'view'
    saving.value = false
    emit('saved')
    await loadCard()
  } catch (cause) {
    formError.value = errorMessage(cause, '卡片保存失败，请检查字段后重试。')
  } finally {
    saving.value = false
  }
}

const runCommand = async () => {
  const command = pendingCommand.value
  if (!command || commandBusy.value || dirty.value) return
  commandBusy.value = true
  commandError.value = ''
  try {
    const current = await client.cards.getManaged(props.cardId)
    const stillAllowed = cardGovernanceActions(current, { approve: canApproveCard.value, update: canUpdateContent.value, pin: canPinCard.value })
      .some(action => action.method === command.method && action.label === command.label)
    if (!stillAllowed) throw new Error('卡片状态已变化，请刷新后重新选择操作。')
    await client.cards.batchManaged({ method: command.method, ids: [props.cardId] })
    pendingCommand.value = null
    emit('saved')
    await loadCard()
  } catch (cause) {
    commandError.value = errorMessage(cause, '卡片操作失败，请稍后重试。')
  } finally {
    commandBusy.value = false
  }
}
const openCard = () => { mode.value = props.initialMode === 'edit' && canEditCard.value ? 'edit' : 'view'; void loadCard() }
onMounted(openCard)
watch(() => props.cardId, openCard)
onBeforeUnmount(reportUnsavedUpload)
</script>

<style scoped>
.text-wrap {
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.media-placeholder {
  aspect-ratio: 1;
}

.card-detail__body {
  max-width: 76ch;
  font-size: 1.0625rem;
  line-height: 1.75;
  overflow-wrap: anywhere;
}
</style>
