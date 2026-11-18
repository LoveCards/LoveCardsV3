<template>
  <div>
    <AdminAsyncState :loading="loading" :error="loadError" :empty="!file" @retry="load">
      <template v-if="file">
        <AdminDetailSection title="文件信息" description="文件元数据只读；审核、公开和回收站操作分别提交。">
          <v-alert v-if="commandError" class="mb-4" type="error" variant="tonal">{{ commandError }}</v-alert>
          <v-row dense>
            <v-col cols="12" sm="6"><v-list-item subtitle="文件名" :title="file.original_name || `文件 #${file.id}`" /></v-col>
            <v-col cols="12" sm="6"><v-list-item subtitle="编号" :title="String(file.id)" /></v-col>
            <v-col cols="12" sm="6"><v-list-item subtitle="上传者"><AdminResourceLink resource-type="user" :resource-id="file.user_id" /></v-list-item></v-col>
            <v-col cols="12" sm="6"><v-list-item subtitle="渠道" :title="file.channel_slug || '—'" /></v-col>
            <v-col cols="12" sm="6"><v-list-item subtitle="场景" :title="file.scene || '—'" /></v-col>
            <v-col cols="12" sm="6"><v-list-item subtitle="媒体类型" :title="file.mime_type || '—'" /></v-col>
            <v-col cols="12" sm="6"><v-list-item subtitle="文件大小" :title="formatSize(file.file_size)" /></v-col>
            <v-col cols="12" sm="6"><v-list-item subtitle="创建时间" :title="file.created_at || '—'" /></v-col>
          </v-row>
          <div class="d-flex flex-wrap ga-2 mt-3">
            <v-chip size="small" variant="tonal">{{ file.is_deleted ? '回收站' : '正常列表' }}</v-chip>
            <v-chip size="small" variant="tonal">{{ file.status === 0 ? '审核通过' : '已封禁' }}</v-chip>
            <v-chip size="small" variant="tonal">{{ file.is_public ? '公开' : '非公开' }}</v-chip>
          </div>
          <div v-if="!file.is_deleted && canUpdate" class="d-flex flex-wrap ga-2 mt-4">
            <v-btn v-if="file.status !== 0" variant="outlined" :disabled="busy" @click="selectCommand('approve')">审核通过</v-btn>
            <v-btn v-if="file.status === 0" variant="outlined" :disabled="busy" @click="selectCommand('ban')">封禁文件</v-btn>
            <v-btn variant="outlined" :disabled="busy" @click="selectCommand('toggle_public')">{{ file.is_public ? '设为非公开' : '设为公开' }}</v-btn>
          </div>
          <div class="d-flex flex-wrap ga-2 mt-3">
            <v-btn v-if="!file.is_deleted && canTrash" color="error" variant="text" :disabled="busy" @click="selectCommand('trash')">移入回收站</v-btn>
            <v-btn v-if="file.is_deleted && canRestore" variant="outlined" :disabled="busy" @click="selectCommand('restore')">恢复文件</v-btn>
            <v-btn v-if="file.is_deleted && canPurge" color="error" variant="text" :disabled="busy" @click="selectCommand('hard_delete')">永久删除文件</v-btn>
          </div>
          <v-alert v-if="pendingCommand" class="mt-4" type="warning" variant="tonal">
            {{ commandImpact }}确定对文件 #{{ file.id }}执行“{{ commandTitle }}”吗？
            <div class="d-flex flex-wrap justify-end ga-2 mt-3">
              <v-btn :disabled="busy" variant="text" @click="pendingCommand = null">取消</v-btn>
              <v-btn :color="pendingCommand === 'trash' || pendingCommand === 'hard_delete' ? 'error' : 'primary'" :loading="busy" variant="flat" @click="runCommand">{{ commandTitle }}</v-btn>
            </div>
          </v-alert>
        </AdminDetailSection>

        <AdminDetailSection v-if="canReadUsage" id="usage" class="mt-5" title="资源引用" description="引用信息由独立权限控制。">
          <v-alert v-if="usageError" type="error" variant="tonal">{{ usageError }}</v-alert>
          <v-progress-linear v-else-if="usageLoading" indeterminate />
          <template v-else-if="usage">
            <v-alert v-if="usage.orphaned" class="mb-3" type="warning" variant="tonal">当前未发现业务引用；仍须经过清理策略核验。</v-alert>
            <v-list v-if="usage.references.length" density="compact">
              <v-list-item v-for="(reference, index) in usage.references" :key="index">
                <AdminResourceLink :resource-type="reference.resource_type || 'unknown'" :resource-id="reference.resource_id" />
              </v-list-item>
            </v-list>
            <p v-else class="text-body-2 text-medium-emphasis mb-0">暂无业务引用。</p>
          </template>
        </AdminDetailSection>
      </template>
    </AdminAsyncState>

  </div>
</template>

<script setup lang="ts">
import type { FileUsage, FilesBatchMethod, LCFile } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDetailSection from '~/components/shared/admin/AdminDetailSection.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const props = defineProps<{ fileId: number }>()
const emit = defineEmits<{ changed: []; removed: []; 'busy-change': [value: boolean] }>()
const client = useApi()
const userStore = useUserStore()
const file = ref<LCFile | null>(null)
const usage = ref<FileUsage | null>(null)
const loading = ref(false)
const usageLoading = ref(false)
const busy = ref(false)
const loadError = ref('')
const usageError = ref('')
const commandError = ref('')
const pendingCommand = ref<FilesBatchMethod | null>(null)
let loadSequence = 0

const canReadUsage = computed(() => userStore.hasGrant('files.usage.read', 'global'))
const canUpdate = computed(() => userStore.hasGrant('files.update', 'all'))
const canTrash = computed(() => userStore.hasGrant('files.delete', 'all'))
const canRestore = computed(() => userStore.hasGrant('files.restore', 'all'))
const canPurge = computed(() => userStore.hasGrant('files.purge', 'all'))
const titles: Record<FilesBatchMethod, string> = {
  approve: '审核通过', ban: '封禁文件', toggle_public: '切换公开状态',
  trash: '移入回收站', restore: '恢复文件', hard_delete: '永久删除文件',
}
const impacts: Record<FilesBatchMethod, string> = {
  approve: '文件审核状态将立即设为通过；实际访问仍取决于公开状态与上传结果。',
  ban: '文件将被封禁，并停止正常公开访问。',
  toggle_public: '文件公开状态将立即翻转。请核对当前状态后再确认。',
  trash: '文件将移入回收站并停止正常使用，永久删除前仍可恢复。',
  restore: '文件将回到正常列表；实际访问仍取决于审核、公开和上传状态。',
  hard_delete: '存储对象将进入永久清理流程，此操作不可撤销；存在业务引用时服务端会拒绝。',
}
const commandTitle = computed(() => pendingCommand.value ? titles[pendingCommand.value] : '文件操作')
const commandImpact = computed(() => pendingCommand.value ? impacts[pendingCommand.value] : '')
const errorText = (cause: unknown, fallback: string) => isApiError(cause) ? cause.message : cause instanceof Error ? cause.message : fallback
const formatSize = (bytes: number) => bytes < 1024 ? `${bytes} B` : bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`

async function load() {
  const sequence = ++loadSequence
  file.value = null
  usage.value = null
  loadError.value = ''
  usageError.value = ''
  if (!Number.isSafeInteger(props.fileId) || props.fileId <= 0) { loadError.value = '文件 ID 无效。'; return }
  loading.value = true
  try {
    const next = await client.files.getManaged(props.fileId)
    if (sequence !== loadSequence) return
    file.value = next
    if (canReadUsage.value) void loadUsage(sequence)
  } catch (cause) {
    if (sequence === loadSequence) loadError.value = errorText(cause, '无法加载文件详情。')
  } finally {
    if (sequence === loadSequence) loading.value = false
  }
}
async function loadUsage(sequence: number) {
  usageLoading.value = true
  try {
    const next = await client.files.usage(props.fileId)
    if (sequence === loadSequence) usage.value = next
  } catch (cause) {
    if (sequence === loadSequence) usageError.value = errorText(cause, '无法加载文件引用。')
  } finally {
    if (sequence === loadSequence) usageLoading.value = false
  }
}
function selectCommand(command: FilesBatchMethod) {
  if (busy.value) return
  pendingCommand.value = command
  commandError.value = ''
}
async function runCommand() {
  const command = pendingCommand.value
  if (!command || busy.value) return
  const allowed = command === 'approve' || command === 'ban' || command === 'toggle_public' ? canUpdate.value
    : command === 'trash' ? canTrash.value : command === 'restore' ? canRestore.value : canPurge.value
  if (!allowed) { commandError.value = '当前账号无权执行此文件操作。'; return }
  busy.value = true
  commandError.value = ''
  try {
    const current = await client.files.getManaged(props.fileId)
    if (current.is_deleted !== file.value?.is_deleted) throw new Error('文件位置已变化，请刷新后重新选择操作。')
    if ((command === 'approve' || command === 'ban') && current.status !== file.value?.status) throw new Error('审核状态已变化，请刷新后重新选择操作。')
    if (command === 'toggle_public' && current.is_public !== file.value?.is_public) throw new Error('公开状态已变化，请刷新后重新选择操作。')
    await client.files.batchManaged({ ids: [props.fileId], method: command })
    pendingCommand.value = null
    if (command === 'hard_delete') emit('removed')
    else { await load(); emit('changed') }
  } catch (cause) {
    commandError.value = errorText(cause, '文件操作失败，请稍后重试。')
  } finally {
    busy.value = false
  }
}
watch(busy, value => emit('busy-change', value), { immediate: true })
watch(() => props.fileId, () => { void load() })
onMounted(() => { void load() })
</script>
