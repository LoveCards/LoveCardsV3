<template>
  <v-card :border="!embedded" rounded="lg" variant="flat">
    <v-card-text class="pt-4">
      <AdminAsyncState :loading="loading" :error="loadError" :empty="!comment" empty-title="评论不存在" @retry="load">
        <template v-if="comment">
          <div class="d-flex flex-wrap align-center ga-3 mb-4">
            <AdminContentStatus :status="comment.status" :pinned="comment.is_pinned" />
            <span v-if="comment.deleted" class="text-body-2 text-medium-emphasis">已删除</span>
          </div>
          <p v-if="mode === 'view'" class="text-body-1 text-pre-wrap mb-4">{{ comment.content || '—' }}</p>
          <template v-else>
            <v-alert class="mb-4" type="info" variant="tonal">后台修改会记录操作者与变更前后状态。</v-alert>
            <v-alert v-if="formError" class="mb-4" role="alert" type="error" variant="tonal">{{ formError }}</v-alert>
            <v-textarea v-model="draft" auto-grow :disabled="saving" label="评论正文" maxlength="140" rows="4" variant="outlined" />
          </template>
          <v-row dense>
            <v-col cols="12" sm="6"><strong>作者</strong><div><AdminResourceLink resource-type="user" :resource-id="comment.user_id" :label="`${comment.username || '用户'} #${comment.user_id}`" /></div></v-col>
            <v-col cols="12" sm="6"><strong>所属卡片</strong><div><AdminResourceLink resource-type="card" :resource-id="comment.card_id" /></div></v-col>
            <v-col cols="12" sm="6"><strong>父评论</strong><div v-if="comment.parent_id"><AdminResourceLink resource-type="comment" :resource-id="comment.parent_id" /></div><div v-else>—</div></v-col>
            <v-col cols="12" sm="6"><strong>点赞数</strong><div>{{ comment.goods }}</div></v-col>
            <v-col cols="12" sm="6"><strong>发布时间</strong><div><AdminTableTime :value="comment.created_at" /></div></v-col>
            <v-col cols="12" sm="6"><strong>更新时间</strong><div><AdminTableTime :value="comment.updated_at" /></div></v-col>
            <v-col v-if="comment.post_ip" cols="12" sm="6"><strong>发布 IP</strong><div>{{ comment.post_ip }}</div></v-col>
          </v-row>
          <v-expansion-panels v-if="comment.data" class="my-4" variant="accordion"><v-expansion-panel title="查看扩展字段（只读）"><v-expansion-panel-text><pre class="text-wrap">{{ displayJson(comment.data) }}</pre></v-expansion-panel-text></v-expansion-panel></v-expansion-panels>
          <v-sheet v-if="mode === 'view' && commands.length" border rounded="lg" class="pa-4 mt-5">
            <h3 class="text-subtitle-1 font-weight-medium mb-2">评论治理</h3>
            <p class="text-body-2 text-medium-emphasis mb-3">审核、封禁、隐藏和置顶是立即生效的命令。</p>
            <v-alert v-if="commandError" class="mb-3" role="alert" type="error" variant="tonal">{{ commandError }}</v-alert>
            <div class="d-flex flex-wrap ga-2"><v-btn v-for="action in commands" :key="action.method" :disabled="busy" variant="outlined" @click="pendingCommand = action">{{ action.label }}</v-btn></div>
            <v-alert v-if="pendingCommand" class="mt-4" type="warning" variant="tonal">
              {{ pendingCommand.impact }}确定对评论 #{{ commentId }}执行“{{ pendingCommand.label }}”吗？
              <template #append><v-btn :disabled="busy" variant="text" @click="pendingCommand = null">取消</v-btn><v-btn color="warning" :loading="busy" variant="flat" @click="runCommand">{{ pendingCommand.label }}</v-btn></template>
            </v-alert>
          </v-sheet>
        </template>
      </AdminAsyncState>
    </v-card-text>
    <v-divider />
    <v-card-actions v-if="comment && !loading" class="pa-4">
      <v-spacer />
      <v-btn v-if="mode === 'view' && canUpdate && !comment.deleted" variant="tonal" @click="mode = 'edit'">编辑评论</v-btn>
      <v-btn v-if="mode === 'edit'" :disabled="saving" variant="text" @click="cancelEdit">取消编辑</v-btn>
      <v-btn v-if="mode === 'edit'" color="primary" :loading="saving" variant="flat" @click="save">保存修改</v-btn>
    </v-card-actions>
    <v-alert v-if="confirmDiscard" class="ma-4" type="warning" variant="tonal">评论有未保存修改，确定放弃吗？<template #append><v-btn variant="text" @click="confirmDiscard = false">继续编辑</v-btn><v-btn color="warning" variant="flat" @click="discardEdit">放弃修改</v-btn></template></v-alert>
  </v-card>
</template>

<script setup lang="ts">
import type { Comment } from '@lovecards/sdk'
import { commentGovernanceActions, type CommentGovernanceAction } from '~/components/comments/commentGovernanceActions'
import AdminContentStatus from '~/components/content/AdminContentStatus.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'
import { useAdminNotifier } from '~/composables/useAdminNotifier'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const props = withDefaults(defineProps<{ commentId: number; initialMode?: 'view' | 'edit'; embedded?: boolean }>(), { initialMode: 'view', embedded: false })
const emit = defineEmits<{ saved: []; 'dirty-change': [value: boolean]; 'busy-change': [value: boolean] }>()
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
watch(dirty, value => emit('dirty-change', value), { immediate: true })
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
  if (!comment.value || !canUpdate.value || saving.value) return
  if (!dirty.value) { notifier.warning('请修改后再提交'); return }
  saving.value = true
  formError.value = ''
  try {
    await client.comments.moderateManaged(comment.value.id, { content: draft.value })
    mode.value = 'view'
    saving.value = false
    emit('saved')
    await load()
  } catch (cause) { formError.value = isApiError(cause) ? cause.message : '评论保存失败，请稍后重试。' }
  finally { saving.value = false }
}
async function runCommand() {
  const action = pendingCommand.value
  if (!action || !comment.value || busy.value || dirty.value) return
  busy.value = true
  commandError.value = ''
  try {
    const current = await client.comments.getManaged(comment.value.id)
    if (!commentGovernanceActions(current, { moderate: canModerate.value, pin: canPin.value }).some(item => item.method === action.method && item.label === action.label)) throw new Error('评论状态已变化，请刷新后重新选择操作。')
    if (action.method === 'pin') await client.comments.pinManaged(current.card_id, { comment_id: current.id })
    else if (action.method === 'unpin') await client.comments.unpinManaged(current.card_id)
    else await client.comments.batchManaged({ method: action.method, ids: [current.id] })
    pendingCommand.value = null
    emit('saved')
    await load()
  } catch (cause) { commandError.value = cause instanceof Error ? cause.message : '评论操作失败，请稍后重试。' }
  finally { busy.value = false }
}
const openComment = () => { mode.value = props.initialMode === 'edit' && canUpdate.value ? 'edit' : 'view'; void load() }
onMounted(openComment)
watch(() => props.commentId, openComment)
</script>

<style scoped>.text-wrap { overflow-wrap: anywhere; white-space: pre-wrap; }</style>
