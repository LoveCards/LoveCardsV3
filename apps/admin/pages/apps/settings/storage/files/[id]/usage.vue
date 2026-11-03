<template>
  <AdminPageContainer>
    <AdminPageHeader :title="`文件 #${route.params.id}`" description="查看文件元数据与业务引用，内部存储路径不会作为公共 URL 展示。" :breadcrumbs="breadcrumbs">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <div id="usage">
      <AdminAsyncState :loading="loading" :error="error" :empty="!usage" @retry="load">
        <template v-if="usage">
        <v-alert v-if="usage.orphaned" class="mb-4" type="warning" variant="tonal">当前未发现业务引用。孤立状态不等于可以立即物理删除，仍需经过清理策略核验。</v-alert>
        <v-sheet border rounded="lg" class="pa-5">
          <h2 class="text-h6 mb-4">文件信息</h2>
          <dl class="file-metadata">
            <template v-for="(value, key) in visibleFileFields" :key="key"><dt>{{ fieldLabel(String(key)) }}</dt><dd>{{ displayValue(value) }}</dd></template>
          </dl>
        </v-sheet>
        <v-sheet border rounded="lg" class="pa-5 mt-5">
          <div class="d-flex align-center justify-space-between ga-3 mb-3"><h2 class="text-h6">资源引用</h2><v-chip size="small" variant="outlined">{{ usage.references.length }} 条</v-chip></div>
          <v-list v-if="usage.references.length" lines="two">
            <v-list-item v-for="(reference, index) in usage.references" :key="index" prepend-icon="mdi-link-variant">
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
const hiddenFields = new Set(['path', 'storage_path', 'local_path', 'secret', 'token'])
const visibleFileFields = computed<JsonObject>(() => Object.fromEntries(Object.entries(usage.value?.file ?? {}).filter(([key]) => !hiddenFields.has(key.toLowerCase()))) as JsonObject)
const labels: Record<string, string> = { id: '编号', original_name: '原始文件名', file_url: '访问地址', mime_type: '媒体类型', file_size: '大小', status: '状态', upload_status: '上传状态', created_at: '创建时间' }
function fieldLabel(key: string) { return labels[key] ?? key }
function displayValue(value: JsonValue | undefined) { return value === null || value === undefined ? '-' : typeof value === 'object' ? JSON.stringify(value) : String(value) }
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
