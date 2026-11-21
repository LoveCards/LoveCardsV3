<template>
  <div :class="{ 'card-record__page-surface': !embedded }">
    <v-card-text class="pa-4 pa-sm-6">
      <AdminAsyncState
        :error="loadError"
        error-title="卡片加载失败"
        :loading="loading"
        skeleton-type="list-item-two-line@3, text@3"
        @retry="loadCard"
      >
        <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">{{ formError }}</v-alert>
        <div v-if="loaded" class="card-record">
          <div class="card-record__field">
            <div class="card-record__label">正文</div>
            <v-textarea
              v-if="mode === 'edit'"
              v-model="CardData.edit.content"
              :disabled="!canUpdateContent || saving"
              auto-grow
              density="comfortable"
              hide-details="auto"
              aria-label="正文"
              rows="5"
              variant="outlined"
            />
            <div v-else class="card-record__read card-record__read--long">{{ CardData.origin.content || '—' }}</div>
          </div>

          <div v-if="CardMediaData.edit.length || mode === 'edit'" class="card-record__field">
            <div class="card-record__label">图片与媒体</div>
            <v-alert v-if="uploadError" class="mb-3" role="alert" type="error" variant="tonal">{{ uploadError }}</v-alert>
            <v-alert v-if="mode === 'edit' && hasUnboundMedia" class="mb-3" role="alert" type="warning" variant="tonal">旧媒体缺少文件 ID，暂不能调整媒体；正文、标签和 JSON 仍可保存。</v-alert>
            <v-row dense>
              <v-col v-for="(item, index) in CardMediaData.edit" :key="`${item.fileId ?? 'legacy'}:${index}`" cols="6" sm="4" md="3">
                <v-sheet border rounded="sm" class="card-record__media">
                  <v-img v-if="item.type === 'image'" :alt="`第 ${index + 1} 张图片`" :src="item.url" aspect-ratio="16/10" cover>
                    <v-chip v-if="item.fileId && item.fileId === coverFileId" class="ma-2" color="surface" size="x-small">封面</v-chip>
                    <v-progress-circular v-if="item.fileId === 0" class="ma-2" color="primary" indeterminate size="24" />
                    <template #error><div class="d-flex align-center justify-center h-100 text-medium-emphasis">图片暂不可预览</div></template>
                  </v-img>
                  <div v-else class="d-flex align-center justify-center text-medium-emphasis card-record__video">视频媒体</div>
                  <div class="card-record__media-name text-caption">{{ index + 1 }}. {{ item.type === 'image' ? '图片' : '视频' }} #{{ item.fileId ?? '旧记录' }}</div>
                  <div v-if="mode === 'edit' && canUpdateContent" class="d-flex flex-wrap ga-1 px-1 pb-1">
                    <v-btn :disabled="hasUnboundMedia || index === 0 || uploadingImages" size="x-small" variant="text" @click="moveCardImage(index, -1)">上移</v-btn>
                    <v-btn :disabled="hasUnboundMedia || index === CardMediaData.edit.length - 1 || uploadingImages" size="x-small" variant="text" @click="moveCardImage(index, 1)">下移</v-btn>
                    <v-btn v-if="item.type === 'image' && item.fileId !== coverFileId" :disabled="hasUnboundMedia || !item.fileId || uploadingImages" size="x-small" variant="text" @click="setCover(item.fileId)">设封面</v-btn>
                    <v-btn :disabled="hasUnboundMedia || uploadingImages" size="x-small" variant="text" @click="removeCardImage(index)">移除</v-btn>
                  </div>
                </v-sheet>
              </v-col>
            </v-row>
            <v-file-input
              v-if="mode === 'edit' && canUpdateContent && canUploadFiles"
              v-model="viewCardFiles"
              accept="image/*"
              class="mt-3"
              :disabled="hasUnboundMedia || saving || uploadingImages"
              density="comfortable"
              aria-label="添加图片"
              :loading="uploadingImages"
              multiple
              prepend-icon="mdi-image-plus-outline"
              show-size
              variant="outlined"
              @update:model-value="uploadSelectedImages"
            />
            <p v-if="mode === 'edit' && uploadedFileIds.length" class="text-caption text-medium-emphasis mt-2">已上传 {{ uploadedFileIds.length }} 个文件；取消编辑不会撤销上传，未绑定文件可在文件管理检查。</p>
            <p v-else-if="mode === 'edit' && !canUploadFiles" class="text-caption text-medium-emphasis mt-2">当前账号没有上传文件权限。</p>
          </div>

          <v-row dense>
            <v-col cols="12" sm="6">
              <div class="card-record__label">标签</div>
              <v-alert v-if="tagLoadError && mode === 'edit'" class="mb-2" density="compact" role="alert" type="warning" variant="tonal">
                {{ tagLoadError }} <v-btn size="small" variant="text" @click="loadTagOptions">重试</v-btn>
              </v-alert>
              <v-autocomplete
                v-if="mode === 'edit'"
                v-model="CardData.edit.tags"
                chips
                closable-chips
                clearable
                :disabled="!canUpdateContent || saving"
                density="comfortable"
                item-title="name"
                item-value="id"
                :items="viewTags"
                aria-label="搜索并选择标签"
                multiple
                variant="outlined"
              />
              <div v-else class="card-record__read d-flex flex-wrap ga-1">
                <v-chip v-for="tag in selectedTags" :key="tag.id" size="small" variant="tonal">{{ tag.name }} #{{ tag.id }}</v-chip>
                <span v-if="!selectedTags.length">—</span>
              </div>
            </v-col>
            <v-col cols="12" sm="6">
              <div class="card-record__label">发布范围</div>
              <div class="card-record__read">{{ visibilityLabel }}</div>
              <p v-if="mode === 'edit'" class="text-caption text-medium-emphasis mt-1">管理端当前写入契约不支持修改此字段。</p>
            </v-col>
          </v-row>

          <div class="card-record__field">
            <div class="card-record__label">扩展数据 · JSON</div>
            <v-textarea
              v-if="mode === 'edit'"
              v-model="dataText"
              :disabled="!canUpdateContent || saving"
              :error-messages="jsonError"
              class="card-record__json"
              density="comfortable"
              aria-label="扩展数据 JSON"
              rows="8"
              spellcheck="false"
              variant="outlined"
              @update:model-value="jsonError = ''"
            />
            <pre v-else class="card-record__read card-record__json-read">{{ displayJson(CardData.origin.data ?? {}) }}</pre>
          </div>

          <v-row dense>
            <v-col cols="12" sm="6"><div class="card-record__label">审核与发布状态</div>
              <v-select v-if="statusOptions.length > 1" :model-value="null" :items="statusOptions" :disabled="commandBusy || dirty" density="comfortable" item-title="title" item-value="value" aria-label="审核与发布状态" variant="outlined" @update:model-value="requestCommand" />
              <div v-else class="card-record__read">{{ statusLabel }}</div>
            </v-col>
            <v-col cols="12" sm="6"><div class="card-record__label">置顶状态</div>
              <v-select v-if="pinOptions.length > 1" :model-value="null" :items="pinOptions" :disabled="commandBusy || dirty || Number(CardData.origin.status) !== 0" density="comfortable" item-title="title" item-value="value" aria-label="置顶状态" variant="outlined" @update:model-value="requestCommand" />
              <div v-else class="card-record__read">{{ Number(CardData.origin.is_top) === 1 ? '已置顶' : '未置顶' }}</div>
            </v-col>
          </v-row>
          <v-alert v-if="commandError" role="alert" type="error" variant="tonal">{{ commandError }}</v-alert>
          <v-alert v-if="pendingCommand" type="warning" variant="tonal">
            {{ pendingCommand.impact }}确定对卡片 #{{ cardId }}执行“{{ pendingCommand.label }}”吗？此操作立即生效。
            <template #append>
              <v-btn :disabled="commandBusy" variant="text" @click="pendingCommand = null">取消</v-btn>
              <v-btn color="warning" :loading="commandBusy" variant="flat" @click="runCommand">{{ pendingCommand.label }}</v-btn>
            </template>
          </v-alert>

          <v-divider />
          <v-row dense>
            <v-col cols="12" sm="4"><div class="card-record__label">作者</div><div class="card-record__read"><AdminResourceLink resource-type="user" :resource-id="CardData.origin.user_id" :label="`${CardData.origin.username || '用户'} #${CardData.origin.user_id}`" /></div></v-col>
            <v-col cols="12" sm="4"><div class="card-record__label">发布时间</div><div class="card-record__read">{{ displayValue(CardData.origin.created_at) }}</div></v-col>
            <v-col cols="12" sm="4"><div class="card-record__label">数据统计</div><div class="card-record__read">{{ CardData.origin.goods }} 赞 · {{ CardData.origin.comments }} 评论 · {{ CardData.origin.views }} 浏览</div></v-col>
          </v-row>
        </div>
      </AdminAsyncState>
    </v-card-text>
    <template v-if="loaded && !loading && !loadError">
      <v-divider />
      <v-card-actions class="pa-4 pa-sm-6" :class="{ 'card-record__actions--embedded': embedded }">
        <v-spacer />
        <v-btn v-if="mode === 'view' && canEditCard" variant="tonal" @click="beginEdit">编辑</v-btn>
        <template v-if="mode === 'edit'">
          <v-btn :disabled="saving || uploadingImages" variant="text" @click="cancelEdit">取消编辑</v-btn>
          <v-btn color="primary" :disabled="saving || uploadingImages || !dirty" :loading="saving" variant="flat" @click="submit">保存修改</v-btn>
        </template>
      </v-card-actions>
    </template>
    <v-alert v-if="showDiscardConfirm" class="ma-4" type="warning" variant="tonal">
      当前修改尚未保存，确定放弃吗？
      <template #append>
        <v-btn variant="text" @click="showDiscardConfirm = false">继续编辑</v-btn>
        <v-btn color="warning" variant="flat" @click="discardEdit">放弃修改</v-btn>
      </template>
    </v-alert>
  </div>
</template>

<script setup lang="ts">
import type { Card } from '@lovecards/sdk'
import { cardGovernanceActions, type CardGovernanceAction } from '~/components/cards/cardGovernanceActions'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
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
  data?: Card['data']
}

const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const props = withDefaults(defineProps<{ cardId: number; initialMode?: 'view' | 'edit'; embedded?: boolean }>(), { initialMode: 'view', embedded: false })
const emit = defineEmits<{ saved: []; 'dirty-change': [value: boolean]; 'busy-change': [value: boolean]; 'editing-change': [value: boolean] }>()
const tagsStore = useTagsStore()
const viewTags = computed(() => {
  const byId = new Map<number, { id: number; name: string }>()
  for (const tag of tagsStore.tags ?? []) byId.set(Number(tag.id), { id: Number(tag.id), name: tag.name })
  for (const tag of CardData.value.origin.topics ?? []) byId.set(Number(tag.id), { id: Number(tag.id), name: tag.name })
  for (const id of CardData.value.edit.tags ?? []) if (!byId.has(id)) byId.set(id, { id, name: `标签 #${id}` })
  return [...byId.values()]
})
const emptyCard = {} as ManagedCard
const CardData = ref<{ edit: ManagedCard; origin: ManagedCard }>({ edit: emptyCard, origin: emptyCard })
const CardMediaData = ref<{ edit: ManagedMedia[]; origin: ManagedMedia[] }>({ edit: [], origin: [] })
const coverFileId = ref<number | null>(null)
const originalCoverFileId = ref<number | null>(null)
const dataText = ref('{}')
const jsonError = ref('')
const viewCardFiles = ref<File[]>([])
const loading = ref(false)
const loaded = ref(false)
const saving = ref(false)
const uploadingImages = ref(false)
const loadError = ref('')
const formError = ref('')
const uploadError = ref('')
const uploadedFileIds = ref<number[]>([])
const tagLoadError = ref('')
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
const loadTagOptions = async () => {
  tagLoadError.value = ''
  try { await tagsStore.init() } catch { tagLoadError.value = '标签候选加载失败；已关联标签仍可查看。' }
}
const commands = computed<CardGovernanceAction[]>(() => {
  if (!loaded.value) return []
  return cardGovernanceActions(CardData.value.origin, { approve: canApproveCard.value, update: canUpdateContent.value, pin: canPinCard.value })
})
const statusLabel = computed(() => ({ 0: '正常发布', 1: '已封禁', 2: '已隐藏', 3: '待审核' })[Number(CardData.value.origin.status) as 0 | 1 | 2 | 3] ?? '未知状态')
const statusOptions = computed(() => [
  { title: statusLabel.value, value: null },
  ...commands.value.filter(item => item.method !== 'top').map(item => ({ title: item.label, value: item.method })),
])
const pinOptions = computed(() => [
  { title: Number(CardData.value.origin.is_top) === 1 ? '已置顶' : '未置顶', value: null },
  ...commands.value.filter(item => item.method === 'top').map(item => ({ title: item.label, value: item.method })),
])
const requestCommand = (method: CardGovernanceAction['method'] | null) => {
  if (!method || dirty.value || commandBusy.value) return
  pendingCommand.value = commands.value.find(item => item.method === method) ?? null
}
const visibilityLabel = computed(() => ({ public: '公开', followers: '仅关注者', private: '仅自己' })[CardData.value.origin.visibility ?? 'public'])
const selectedTags = computed(() => (CardData.value.origin.tags ?? []).map(id => ({ id, name: viewTags.value.find(tag => tag.id === id)?.name ?? '标签' })))
const hasUnboundMedia = computed(() => CardMediaData.value.edit.some(item => item.fileId === null))
const dirty = computed(() => mode.value === 'edit' && (
  JSON.stringify({ content: CardData.value.edit.content, tags: CardData.value.edit.tags }) !== JSON.stringify({ content: CardData.value.origin.content, tags: CardData.value.origin.tags })
  || JSON.stringify(CardMediaData.value.edit.map(item => item.fileId)) !== JSON.stringify(CardMediaData.value.origin.map(item => item.fileId))
  || coverFileId.value !== originalCoverFileId.value
  || dataText.value !== displayJson(CardData.value.origin.data ?? {})
))
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
  coverFileId.value = originalCoverFileId.value
  dataText.value = displayJson(CardData.value.origin.data ?? {})
  jsonError.value = ''
  viewCardFiles.value = []
  formError.value = ''
  uploadError.value = ''
  commandError.value = ''
  showDiscardConfirm.value = false
  mode.value = 'view'
}
const cancelEdit = () => { if (dirty.value) showDiscardConfirm.value = true; else discardEdit() }

const displayValue = (value: unknown) => value === undefined || value === null || value === '' ? '—' : String(value)
function displayJson(value: unknown): string {
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
const parseDataText = (): Card['data'] | null => {
  try {
    const parsed: unknown = JSON.parse(dataText.value)
    if (parsed === null || Array.isArray(parsed) || typeof parsed !== 'object') throw new Error('扩展数据顶层必须是 JSON 对象。')
    if ('visibility' in parsed) throw new Error('发布范围请使用独立字段，不写入扩展数据。')
    if ('title' in parsed || 'summary' in parsed) throw new Error('历史 title/summary 字段不可重新写入；请先移除后保存。')
    jsonError.value = ''
    return parsed as Card['data']
  } catch (cause) {
    jsonError.value = cause instanceof SyntaxError ? 'JSON 格式不正确，请检查后再保存。' : errorMessage(cause, '扩展数据格式不正确。')
    return null
  }
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
    coverFileId.value = positiveId(card.cover_file_id)
    originalCoverFileId.value = coverFileId.value
    dataText.value = displayJson(card.data ?? {})
    jsonError.value = ''
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
        formData.append('scene', 'card')
        const result = await client.files.upload(formData)
        if (uploadCardId !== props.cardId) {
          uploadedFileIds.value = [...uploadedFileIds.value, result.id]
          return
        }
        const currentIndex = CardMediaData.value.edit.findIndex(item => item.url === previewUrl && item.fileId === 0)
        if (currentIndex >= 0) CardMediaData.value.edit[currentIndex] = { fileId: result.id, url: result.url, type: 'image' }
        if (coverFileId.value === null) coverFileId.value = result.id
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
    const [removed] = CardMediaData.value.edit.splice(index, 1)
    if (removed?.fileId === coverFileId.value) coverFileId.value = CardMediaData.value.edit.find(item => item.type === 'image')?.fileId ?? null
  }
}
const moveCardImage = (index: number, direction: -1 | 1) => {
  if (!canUpdateContent.value || hasUnboundMedia.value) return
  const target = index + direction
  if (index < 0 || target < 0 || target >= CardMediaData.value.edit.length) return
  const media = CardMediaData.value.edit
  ;[media[index], media[target]] = [media[target]!, media[index]!]
}
const setCover = (fileId: number | null) => {
  if (!canUpdateContent.value || hasUnboundMedia.value || !fileId || fileId <= 0) return
  if (CardMediaData.value.edit.some(item => item.type === 'image' && item.fileId === fileId)) coverFileId.value = fileId
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
    if (dataText.value !== displayJson(originData.data ?? {})) {
      const parsed = parseDataText()
      if (parsed === null) throw new Error(jsonError.value)
      editInput.data = parsed
    }
  }
  const params = CommonUtils.removeCommonProperties(editInput, originInput)
  const originMediaIds = CardMediaData.value.origin.map(item => item.fileId)
  const editMediaIds = CardMediaData.value.edit.map(item => item.fileId)
  const mediaChanged = JSON.stringify(originMediaIds) !== JSON.stringify(editMediaIds)
  if (canUpdateContent.value && (mediaChanged || coverFileId.value !== originalCoverFileId.value)) {
    if (editMediaIds.some(id => id === null || id <= 0)) {
      throw new Error('媒体集合包含缺失或无效的文件 ID，已阻止保存以保护现有媒体。')
    }
    if (mediaChanged) params.media_file_ids = editMediaIds as number[]
    params.cover_file_id = coverFileId.value ?? 0
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
    if (!jsonError.value) formError.value = errorMessage(cause, '卡片保存失败，请检查字段后重试。')
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
const openCard = () => { mode.value = props.initialMode === 'edit' && canEditCard.value ? 'edit' : 'view'; void loadCard(); void loadTagOptions() }
onMounted(openCard)
watch(() => props.cardId, openCard)
onBeforeUnmount(reportUnsavedUpload)
</script>

<style scoped>
.card-record__page-surface { border: 1px solid rgb(var(--v-theme-outline-variant)); border-radius: 4px; background: rgb(var(--v-theme-surface)); }
.card-record { display: grid; gap: 24px; }
.card-record__field { min-width: 0; }
.card-record__label { color: rgb(var(--v-theme-on-surface-variant)); font-size: .8rem; font-weight: 600; margin-bottom: 8px; }
.card-record__read { min-height: 42px; padding: 10px 12px; border: 1px solid rgb(var(--v-theme-outline)); border-radius: 4px; background: rgb(var(--v-theme-surface-variant)); overflow-wrap: anywhere; }
.card-record__read--long { white-space: pre-wrap; line-height: 1.75; }
.card-record__json-read { white-space: pre-wrap; font: .8rem/1.65 ui-monospace, SFMono-Regular, Consolas, monospace; }
.card-record__json :deep(textarea) { font: .8rem/1.6 ui-monospace, SFMono-Regular, Consolas, monospace; }
.card-record__media { overflow: hidden; }
.card-record__media-name { padding: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.card-record__video { aspect-ratio: 16 / 10; }
.card-record__actions--embedded { position: sticky; bottom: 0; z-index: 1; background: rgb(var(--v-theme-surface)); }
</style>
