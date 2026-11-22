<template>
  <div :class="{ 'comment-detail__page-surface': !embedded }">
    <v-card-text class="pa-4 pa-sm-6">
      <AdminAsyncState :loading="loading && !comment" :error="!comment ? loadError : ''" :empty="!comment" empty-title="评论不存在" @retry="load">
        <template v-if="comment">
          <v-alert v-if="loadError" class="mb-4" role="alert" type="error" variant="tonal">{{ loadError }}</v-alert>
          <section v-if="mode === 'view'" aria-label="评论内容">
            <div class="text-body-2 text-medium-emphasis mb-2">评论正文</div>
            <p class="comment-detail__body text-pre-wrap mb-3">{{ comment.content || '—' }}</p>
          </section>
          <template v-else>
            <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">{{ formError }}</v-alert>
            <v-textarea v-model="draft" auto-grow :disabled="saving" label="评论正文" maxlength="140" rows="3" variant="outlined" hide-details />
          </template>
          <v-divider class="my-5" />
          <v-row class="align-start">
            <v-col cols="12" sm="6"><AdminDetailField label="作者"><AdminResourceLink resource-type="user" :resource-id="comment.user_id" :label="`${comment.username || '用户'} #${comment.user_id}`" /></AdminDetailField></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="所属卡片"><AdminResourceLink resource-type="card" :resource-id="comment.card_id" /></AdminDetailField></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="父评论"><AdminResourceLink v-if="comment.parent_id" resource-type="comment" :resource-id="comment.parent_id" /><span v-else>—</span></AdminDetailField></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="点赞数" :value="comment.goods" /></v-col>
            <v-col cols="12">
              <v-divider class="my-1" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select v-if="statusCommands.length" :model-value="'current'" :items="[{ title: statusLabel, value: 'current' }, ...statusCommands.map(action => ({ title: action.label, value: action.method }))]" :disabled="busy || saving" label="审核状态" variant="outlined" hide-details @update:model-value="selectCommand" />
              <AdminDetailField v-else label="审核状态" :value="statusLabel" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select v-if="pinCommand" :model-value="'current'" :items="[{ title: comment.is_pinned ? '已置顶' : '未置顶', value: 'current' }, { title: pinCommand.label, value: pinCommand.method }]" :disabled="busy || saving" label="置顶状态" variant="outlined" hide-details @update:model-value="selectCommand" />
              <AdminDetailField v-else label="置顶状态" :value="comment.parent_id ? '回复不可置顶' : comment.is_pinned ? '已置顶' : '未置顶'" />
            </v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="发布时间"><AdminTableTime :value="comment.created_at" /></AdminDetailField></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="更新时间"><AdminTableTime :value="comment.updated_at" /></AdminDetailField></v-col>
            <v-col v-if="comment.post_ip" cols="12" sm="6"><AdminDetailField label="发布 IP" :value="comment.post_ip" /></v-col>
          </v-row>
          <v-expansion-panels v-if="comment.data" class="my-4" variant="accordion"><v-expansion-panel title="查看扩展字段（只读）"><v-expansion-panel-text><pre class="text-wrap">{{ displayJson(comment.data) }}</pre></v-expansion-panel-text></v-expansion-panel></v-expansion-panels>
          <section v-if="commandError || pendingCommand" class="mt-5">
            <v-alert v-if="commandError" class="mb-3" role="alert" type="error" variant="tonal">{{ commandError }}</v-alert>
            <v-alert v-if="pendingCommand" class="mt-4" type="warning" variant="tonal">
              {{ pendingCommand.impact }}确定对评论 #{{ commentId }}执行“{{ pendingCommand.label }}”吗？<span v-if="dirty">当前未保存的正文修改会保留，仍需单独保存。</span>
              <template #append><v-btn :disabled="busy" variant="text" @click="pendingCommand = null">取消</v-btn><v-btn color="warning" :disabled="saving" :loading="busy" variant="flat" @click="runCommand">{{ pendingCommand.label }}</v-btn></template>
            </v-alert>
          </section>
        </template>
      </AdminAsyncState>
    </v-card-text>
    <AdminDetailActions v-if="comment && !loading && canUpdate && (mode === 'edit' || !comment.deleted)">
      <v-btn v-if="mode === 'view'" variant="tonal" :disabled="busy" @click="mode = 'edit'">编辑</v-btn>
      <template v-else>
        <v-btn :disabled="saving || busy" variant="text" @click="cancelEdit">取消编辑</v-btn>
        <v-btn color="primary" :loading="saving" :disabled="!dirty || busy || comment.deleted" variant="flat" @click="save">保存修改</v-btn>
      </template>
    </AdminDetailActions>
    <v-alert v-if="confirmDiscard" class="ma-4" type="warning" variant="tonal">评论有未保存修改，确定放弃吗？<template #append><v-btn variant="text" @click="confirmDiscard = false">继续编辑</v-btn><v-btn color="warning" variant="flat" @click="discardEdit">放弃修改</v-btn></template></v-alert>
  </div>
</template>

<script setup lang="ts">
import AdminDetailActions from '~/components/shared/admin/AdminDetailActions.vue'
import type { Comment } from '@lovecards/sdk'
import { commentGovernanceActions, type CommentGovernanceAction } from '~/components/comments/commentGovernanceActions'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminDetailField from '~/components/shared/admin/AdminDetailField.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const props = withDefaults(defineProps<{ commentId: number; initialMode?: 'view' | 'edit'; embedded?: boolean }>(), { initialMode: 'view', embedded: false })
const emit = defineEmits<{ saved: []; 'command-applied': []; 'dirty-change': [value: boolean]; 'busy-change': [value: boolean]; 'editing-change': [value: boolean] }>()
const client = useApi()
const notifier = useAdminNotifier()
const userStore = useUserStore()
const comment = ref<Comment | null>(null)
const draft = ref('')
const mode = ref<'view' | 'edit'>('view')
const loading = ref(false)
const saving = ref(false)
const busy = ref(false)
const loadError = ref('')
const formError = ref('')
const commandError = ref('')
const pendingCommand = ref<CommentGovernanceAction | null>(null)
const confirmDiscard = ref(false)
const canUpdate = computed(() => userStore.hasGrant('comments.update', 'all'))
const canModerate = computed(() => userStore.hasGrant('comments.moderate', 'all'))
const canPin = computed(() => userStore.hasGrant('comments.pin', 'all'))
const dirty = computed(() => mode.value === 'edit' && comment.value !== null && draft.value !== comment.value.content)
const commands = computed(() => comment.value ? commentGovernanceActions(comment.value, { moderate: canModerate.value, pin: canPin.value }) : [])
const statusCommands = computed(() => commands.value.filter(action => action.method !== 'pin' && action.method !== 'unpin'))
const pinCommand = computed(() => commands.value.find(action => action.method === 'pin' || action.method === 'unpin'))
const statusLabel = computed(() => ({ 0: '正常', 1: '封禁', 2: '隐藏', 3: '待审核' })[Number(comment.value?.status) as 0 | 1 | 2 | 3] ?? '未知状态')
function selectCommand(value: string) {
  if (value === 'current') return
  pendingCommand.value = commands.value.find(action => action.method === value) ?? null
}

watch(dirty, value => emit('dirty-change', value), { immediate: true })
watch(mode, value => emit('editing-change', value === 'edit'), { immediate: true })
watch(() => loading.value || saving.value || busy.value, value => emit('busy-change', value), { immediate: true })
const displayJson = (value: unknown) => { try { return JSON.stringify(value, null, 2) } catch { return String(value) } }
let latestLoad = 0
async function load() {
  const request = ++latestLoad
  const id = props.commentId
  loadError.value = ''
  if (!Number.isSafeInteger(id) || id < 1) { comment.value = null; loadError.value = '评论 ID 无效。'; loading.value = false; return }
  loading.value = true
  try {
    const result = await client.comments.getManaged(id)
    if (request !== latestLoad || id !== props.commentId) return
    comment.value = result
    draft.value = result.content
  } catch (cause) {
    if (request === latestLoad && id === props.commentId) loadError.value = isApiError(cause) ? cause.message : '无法加载评论详情，请稍后重试。'
  } finally { if (request === latestLoad) loading.value = false }
}
function discardEdit() { draft.value = comment.value?.content ?? ''; mode.value = 'view'; formError.value = ''; confirmDiscard.value = false }
function cancelEdit() { if (dirty.value) confirmDiscard.value = true; else discardEdit() }
async function save() {
  if (!comment.value || !canUpdate.value || saving.value || busy.value) return
  if (!dirty.value) { notifier.warning('请修改后再提交'); return }
  saving.value = true
  formError.value = ''
  try {
    await client.comments.moderateManaged(comment.value.id, { content: draft.value })
    await load()
    if (!loadError.value) { mode.value = 'view'; emit('saved') }
    else { loadError.value = ''; formError.value = '修改已提交，但无法读取最新评论；请刷新后核对结果。'; emit('command-applied') }
  } catch (cause) { formError.value = isApiError(cause) ? cause.message : '评论保存失败，请稍后重试。' }
  finally { saving.value = false }
}
async function runCommand() {
  const action = pendingCommand.value
  if (!action || !comment.value || busy.value || saving.value) return
  busy.value = true
  commandError.value = ''
  const id = comment.value.id
  try {
    const current = await client.comments.getManaged(id)
    if (id !== props.commentId) return
    if (!commentGovernanceActions(current, { moderate: canModerate.value, pin: canPin.value }).some(item => item.method === action.method && item.label === action.label)) throw new Error('评论状态已变化，请刷新后重新选择操作。')
    if (action.method === 'pin') await client.comments.pinManaged(current.card_id, { comment_id: current.id })
    else if (action.method === 'unpin') await client.comments.unpinManaged(current.card_id)
    else await client.comments.batchManaged({ method: action.method, ids: [current.id] })
    pendingCommand.value = null
    emit('command-applied')
    try {
      const refreshed = await client.comments.getManaged(id)
      if (id !== props.commentId) return
      // 治理状态刷新不覆盖正文草稿；只有未编辑正文时才同步输入值。
      if (!dirty.value) draft.value = refreshed.content
      comment.value = refreshed
    } catch {
      commandError.value = '操作已执行，但状态刷新失败。请刷新页面确认。'
    }
  } catch (cause) { commandError.value = cause instanceof Error ? cause.message : '评论操作失败，请稍后重试。' }
  finally { busy.value = false }
}
const openComment = () => { mode.value = props.initialMode === 'edit' && canUpdate.value ? 'edit' : 'view'; void load() }
onMounted(openComment)
watch(() => props.commentId, openComment)
</script>

<style scoped>
.comment-detail__page-surface { border: 1px solid rgb(var(--v-theme-outline-variant)); border-radius: 4px; background: rgb(var(--v-theme-surface)); }
.text-wrap { overflow-wrap: anywhere; white-space: pre-wrap; }
.comment-detail__body { max-width: 76ch; font-size: 1.0625rem; line-height: 1.75; overflow-wrap: anywhere; }
</style>
