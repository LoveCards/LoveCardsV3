<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader :title="`文件 #${route.params.id}`" description="核对文件用途、业务引用与永久清理进度。" :breadcrumbs="breadcrumbs">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <div id="usage">
      <AdminAsyncState :loading="loading" :error="error" :empty="!usage" @retry="load">
        <template v-if="usage">
        <v-alert v-if="usage.orphaned && !purgeStatus" class="mb-4" type="warning" variant="tonal">当前未发现业务引用。孤立状态不等于可以立即物理删除，仍需经过清理策略核验。</v-alert>
        <v-sheet border rounded="lg" class="pa-5 mb-5">
          <h2 class="text-h6 mb-3">永久清理</h2>
          <p class="mb-2">{{ purgeStatus || '尚未申请永久清理' }}</p>
          <p v-if="usage.file.purge_requested_at" class="text-body-2 text-medium-emphasis mb-2">受理时间 {{ usage.file.purge_requested_at }}</p>
          <v-alert v-if="usage.file.purge_error && usage.purge_task?.status !== 'pending'" class="mb-3" type="warning" variant="tonal">{{ usage.file.purge_error }}</v-alert>
          <p v-if="usage.references.length" class="mb-2">仍有业务引用，服务端会拒绝永久清理。</p>
          <div class="d-flex flex-wrap ga-2">
            <AdminActionGuard v-if="usage.purge_task" capability="communications.read">
              <v-btn :to="{ path: '/apps/operations/deliveries/' + usage.purge_task.id, query: { file: String(route.params.id) } }" variant="outlined">查看清理任务</v-btn>
            </AdminActionGuard>
            <AdminActionGuard capability="audit.read">
              <v-btn :to="{ path: '/apps/operations/audit', query: { resource_type: 'file', resource_id: String(route.params.id) } }" variant="text">查看文件审计</v-btn>
            </AdminActionGuard>
          </div>
        </v-sheet>
        <v-sheet v-if="usage.file.purge_status !== 'purged'" border rounded="lg" class="pa-5">
          <h2 class="text-h6 mb-4">文件信息</h2>
          <dl class="file-metadata">
            <template v-for="(value, key) in visibleFileFields" :key="key"><dt>{{ fieldLabel(String(key)) }}</dt><dd>{{ displayValue(String(key), value) }}</dd></template>
          </dl>
        </v-sheet>
        <v-sheet border rounded="lg" class="pa-5 mt-5">
          <div class="d-flex align-center justify-space-between ga-3 mb-3"><h2 class="text-h6">资源引用</h2><v-chip size="small" variant="outlined">{{ usage.references.length }} 条</v-chip></div>
          <v-list v-if="usage.references.length" lines="two">
            <v-list-item v-for="(reference, index) in usage.references" :key="index" prepend-icon="mdi-link-variant">
              <v-list-item-subtitle>{{ purposeLabel(reference.purpose) }} · {{ reference.is_public ? '公开引用' : '受限引用' }}</v-list-item-subtitle>
              <AdminResourceLink :resource-type="reference.resource_type || 'unknown'" :resource-id="reference.resource_id" />
            </v-list-item>
          </v-list>
          <p v-else class="text-medium-emphasis mb-0">暂无业务引用。</p>
        </v-sheet>
        </template>
      </AdminAsyncState>
    </div>
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { FileUsage, JsonObject, JsonValue } from '@lovecards/sdk'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminResourceLink from '~/components/shared/admin/AdminResourceLink.vue'
import { useApi, isApiError } from '~/lib/api'
const route = useRoute()
const client = useApi()
const usage = ref<FileUsage | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const breadcrumbs = [{ title: '文件管理', to: '/apps/settings/storage/files' }, { title: `文件 #${route.params.id}`, disabled: true }]
const visibleFields = ['id', 'original_name', 'scene', 'channel_slug', 'mime_type', 'file_size', 'upload_status', 'created_at']
const purgeLabels: Record<string, string> = {
  pending: '申请已受理，等待存储队列执行', processing: '正在清理存储对象', failed: '存储清理失败',
  storage_deleted: '存储对象已删除，等待移除文件记录', purged: '永久清理完成，文件记录已移除',
}
const purgeStatus = computed(() => {
  const lifecycle = String(usage.value?.file.purge_status ?? '')
  const task = usage.value?.purge_task?.status
  if (task === 'dead') return '清理任务已失败，可在任务详情检查并重试'
  if (task === 'pending' && lifecycle === 'failed') return '上次清理失败，等待再次执行'
  return purgeLabels[lifecycle] ?? ''
})
const purposes: Record<string, string> = { avatar: '头像', profile_cover: '主页背景', card: '卡片媒体', cover: '封面', comment: '评论附件', direct: '直传' }
function purposeLabel(purpose?: string) { return purpose ? purposes[purpose] ?? purpose : '附件' }
const visibleFileFields = computed<JsonObject>(() => Object.fromEntries(visibleFields.map(key => [key, usage.value?.file[key] ?? null])))
const labels: Record<string, string> = { id: '编号', scene: '上传场景', channel_slug: '存储渠道', original_name: '原始文件名', file_url: '访问地址', mime_type: '媒体类型', file_size: '大小', status: '状态', upload_status: '上传状态', created_at: '创建时间' }
function fieldLabel(key: string) { return labels[key] ?? key }
const uploadLabels: Record<string, string> = { '0': '待上传', '1': '上传完成', '2': '上传失败', pending: '待上传', completed: '上传完成', failed: '上传失败' }
function displayValue(key: string, value: JsonValue | undefined) {
  if (value === null || value === undefined) return '—'
  if (key === 'scene') return purposeLabel(String(value))
  if (key === 'upload_status') return uploadLabels[String(value)] ?? '未知状态'
  if (key === 'file_size' && typeof value === 'number') return value.toLocaleString('zh-CN') + ' 字节'
  return String(value)
}
async function load() {
  loading.value = true; error.value = null
  try { usage.value = await client.files.usage(Number(route.params.id)) }
  catch (cause) { error.value = isApiError(cause) ? cause.message : '无法加载文件使用关系' }
  finally { loading.value = false }
}
onMounted(load)
</script>

<style scoped>
.file-metadata { display: grid; grid-template-columns: minmax(120px, 200px) minmax(0, 1fr); gap: 10px 20px; margin: 0; }
.file-metadata dt { color: rgb(var(--v-theme-on-surface-variant)); }
.file-metadata dd { margin: 0; overflow-wrap: anywhere; }
@media (max-width: 600px) { .file-metadata { grid-template-columns: 1fr; gap: 4px; } .file-metadata dd { margin-bottom: 12px; } }
</style>
