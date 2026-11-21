<template>
  <div>
    <AdminAsyncState :loading="loading" :error="loadError" :empty="!tag" empty-title="标签不存在" @retry="loadTag">
      <template v-if="tag">
        <v-alert v-if="actionError" class="mb-4" role="alert" type="error" variant="tonal">{{ actionError }}</v-alert>
        <AdminDetailSection title="标签信息">
          <v-row class="align-start">
            <v-col cols="12" sm="6">
              <AdminDetailField v-if="mode === 'view'" label="标签名称" :value="tag.name" />
              <v-text-field v-else v-model="draftName" :disabled="pending" label="标签名称" maxlength="50" required variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" sm="6">
              <v-select
                v-if="canUpdate"
                :model-value="'current'"
                :items="[{ title: statusLabel, value: 'current' }, ...governanceActions.map(action => ({ title: action.targetLabel, value: action.method }))]"
                :disabled="pending"
                label="治理状态"
                variant="outlined"
                hide-details
                @update:model-value="selectGovernance"
              />
              <AdminDetailField v-else label="治理状态" :value="statusLabel" />
              <p class="text-caption text-medium-emphasis mt-2 mb-0">状态变更单独执行并记录审核结果。</p>
            </v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="创建用户"><AdminResourceLink resource-type="user" :resource-id="tag.user_id" :label="`用户 #${tag.user_id}`" /></AdminDetailField></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="创建时间"><AdminTableTime :value="tag.created_at" /></AdminDetailField></v-col>
            <v-col cols="12" sm="6"><AdminDetailField label="应用 ID" :value="tag.aid" /></v-col>
          </v-row>
          <v-alert v-if="confirmCancel" class="mt-4" type="warning" variant="tonal">
            标签名称有未保存修改，确定放弃吗？
            <template #append>
              <v-btn variant="text" @click="confirmCancel = false">继续编辑</v-btn>
              <v-btn color="warning" variant="flat" @click="discardEditing">放弃修改</v-btn>
            </template>
          </v-alert>
          <div v-if="mode === 'edit'" class="d-flex justify-end ga-2 mt-5">
            <v-btn :disabled="pending" variant="text" @click="cancelEditing">取消编辑</v-btn>
            <v-btn color="primary" :disabled="!canSave" :loading="pending" variant="flat" @click="saveName">保存名称</v-btn>
          </div>
          <div v-else-if="canUpdate && !pending" class="d-flex justify-end mt-5">
            <v-btn variant="tonal" prepend-icon="mdi-pencil-outline" @click="startEditing">编辑</v-btn>
          </div>
        </AdminDetailSection>
        <v-alert v-if="selectedAction" class="mt-4" type="warning" variant="tonal">
          确认将标签「{{ tag.name }}」从“{{ statusLabel }}”改为“{{ selectedAction.targetLabel }}”？<span v-if="dirty">当前未保存的名称修改会保留，仍需单独保存。</span>
          <template #append>
            <v-btn :disabled="pending" variant="text" @click="selectedAction = null">取消</v-btn>
            <v-btn color="warning" :loading="pending" variant="flat" @click="applyGovernance">确认{{ selectedAction.label }}</v-btn>
          </template>
        </v-alert>
      </template>
    </AdminAsyncState>
  </div>
</template>

<script setup lang="ts">
import type { Tag, TagsBatchMethod } from '@lovecards/sdk'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'
import { useTagsStore } from '~/stores/tagsStore'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminDetailField from '~/components/shared/admin/AdminDetailField.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import AdminTableTime from '~/components/shared/admin/AdminTableTime.vue'

type GovernanceAction = { method: TagsBatchMethod; label: string; targetLabel: string }
const props = withDefaults(defineProps<{ tagId: number; initialMode?: 'view' | 'edit' }>(), { initialMode: 'view' })
const emit = defineEmits<{ saved: []; 'write-applied': []; 'command-applied': []; 'dirty-change': [value: boolean]; 'busy-change': [value: boolean] }>()
const client = useApi()
const userStore = useUserStore()
const tagsStore = useTagsStore()
const canUpdate = computed(() => userStore.hasGrant('tags.update', 'all'))
const tag = ref<Tag | null>(null)
const loading = ref(false)
const pending = ref(false)
const loadError = ref<string | null>(null)
const actionError = ref<string | null>(null)
const mode = ref<'view' | 'edit'>('view')
const draftName = ref('')
const confirmCancel = ref(false)
const selectedAction = ref<GovernanceAction | null>(null)
let latestLoad = 0
let initialModePending = true
const statusOptions = [
  { value: 0, label: '正常', color: undefined },
  { value: 1, label: '封禁', color: 'error' },
  { value: 2, label: '隐藏', color: 'warning' },
  { value: 3, label: '待审核', color: 'warning' },
]
const statusLabel = computed(() => statusOptions.find(item => item.value === tag.value?.status)?.label ?? '未知状态')
const dirty = computed(() => mode.value === 'edit' && draftName.value !== (tag.value?.name ?? ''))
const canSave = computed(() => canUpdate.value && draftName.value.trim() !== (tag.value?.name ?? '') && Boolean(draftName.value.trim()) && !pending.value)
const governanceActions = computed<GovernanceAction[]>(() => {
  const status = Number(tag.value?.status)
  if (![0, 1, 2, 3].includes(status)) return []
  return [
    { method: 'approve', label: status === 3 ? '通过审核' : '转入待审核', targetLabel: status === 3 ? '正常' : '待审核' },
    { method: 'ban', label: status === 1 ? '解除封禁' : '封禁', targetLabel: status === 1 ? '正常' : '封禁' },
    { method: 'hide', label: status === 2 ? '取消隐藏' : '隐藏', targetLabel: status === 2 ? '正常' : '隐藏' },
  ]
})

async function loadTag() {
  const id = props.tagId
  const requestId = ++latestLoad
  tag.value = null
  if (!Number.isInteger(id) || id < 1) { loading.value = false; loadError.value = '标签 ID 无效。'; return }
  loading.value = true
  loadError.value = null
  actionError.value = null
  try {
    const result = await client.tags.getManaged(id)
    if (requestId !== latestLoad || props.tagId !== id) return
    tag.value = result
    draftName.value = tag.value.name
    mode.value = initialModePending && props.initialMode === 'edit' && canUpdate.value ? 'edit' : 'view'
    initialModePending = false
    selectedAction.value = null
    confirmCancel.value = false
  } catch (cause) {
    if (requestId !== latestLoad || props.tagId !== id) return
    tag.value = null
    loadError.value = isApiError(cause) ? cause.message : '无法加载标签详情，请稍后重试。'
  } finally { if (requestId === latestLoad) loading.value = false }
}
async function reloadAfterWrite(id: number, failureMessage: string) {
  const requestId = ++latestLoad
  try {
    const result = await client.tags.getManaged(id)
    if (requestId !== latestLoad || props.tagId !== id) return false
    tag.value = result
    draftName.value = result.name
    return true
  } catch {
    if (requestId !== latestLoad || props.tagId !== id) return false
    actionError.value = failureMessage
    return false
  }
}
function startEditing() { if (tag.value && canUpdate.value) { selectedAction.value = null; confirmCancel.value = false; draftName.value = tag.value.name; mode.value = 'edit' } }
function cancelEditing() { if (dirty.value) { confirmCancel.value = true; return }; discardEditing() }
function discardEditing() { if (tag.value) draftName.value = tag.value.name; confirmCancel.value = false; mode.value = 'view' }
async function saveName() {
  if (!tag.value || !canSave.value) return
  const id = tag.value.id
  const name = draftName.value.trim()
  pending.value = true
  actionError.value = null
  try {
    await client.tags.updateManaged(id, { name })
    if (props.tagId !== id) return
    void tagsStore.getIndex().catch(() => undefined)
    if (await reloadAfterWrite(id, '名称已保存，但最新详情读取失败。请重试确认。')) {
      mode.value = 'view'
      initialModePending = false
      confirmCancel.value = false
      emit('saved')
    } else if (props.tagId === id) emit('write-applied')
  } catch (cause) { if (props.tagId === id) actionError.value = isApiError(cause) ? cause.message : '标签名称保存失败。' }
  finally { pending.value = false }
}
function selectGovernance(value: string) {
  if (value === 'current') return
  selectedAction.value = governanceActions.value.find(action => action.method === value) ?? null
}
async function applyGovernance() {
  const action = selectedAction.value
  if (!tag.value || !action || !canUpdate.value || pending.value) return
  const id = tag.value.id
  const expectedStatus = tag.value.status
  pending.value = true
  actionError.value = null
  try {
    const current = await client.tags.getManaged(id)
    if (props.tagId !== id) return
    if (current.status !== expectedStatus) {
      tag.value = current
      selectedAction.value = null
      actionError.value = '标签状态已变化，请重新核对后操作。'
      return
    }
    if (!governanceActions.value.some(candidate => candidate.method === action.method && candidate.targetLabel === action.targetLabel)) {
      selectedAction.value = null
      actionError.value = '当前状态不支持该操作，请重新核对。'
      return
    }
    // 状态操作沿用批量治理命令，以保留服务端的状态翻转、审计和通知语义。
    await client.tags.batchManaged({ method: action.method, ids: [id] })
    selectedAction.value = null
    void tagsStore.getIndex().catch(() => undefined)
    emit('command-applied')
    try {
      const result = await client.tags.getManaged(id)
      if (props.tagId !== id) return
      // 治理状态刷新不覆盖尚未保存的名称草稿。
      if (!dirty.value) draftName.value = result.name
      tag.value = result
    } catch {
      if (props.tagId === id) actionError.value = '状态操作已提交，但最新详情读取失败。请刷新后确认。'
    }
  } catch (cause) { if (props.tagId === id) actionError.value = isApiError(cause) ? cause.message : '标签状态更新失败。' }
  finally { pending.value = false }
}
watch(dirty, value => emit('dirty-change', value), { immediate: true })
watch(pending, value => emit('busy-change', value), { immediate: true })
watch(() => props.tagId, () => { initialModePending = true; mode.value = 'view'; confirmCancel.value = false; void loadTag() }, { immediate: true })
</script>
