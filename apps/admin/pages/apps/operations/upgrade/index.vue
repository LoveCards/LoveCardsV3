<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader title="系统升级" description="上传已签名的升级包，并查看升级进度。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn>
        <AdminActionGuard capability="system.update">
          <v-btn color="primary" prepend-icon="mdi-package-up" @click="uploadDialog = true">上传升级包</v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>

    <v-sheet class="mb-5 pa-4" border rounded="lg">
      <div class="text-caption text-medium-emphasis">当前软件版本</div>
      <div class="text-h6">{{ releaseStatus?.version || (loading ? '读取中' : '暂不可用') }}</div>
      <p v-if="releaseStatus?.release_id" class="text-body-2 mt-1">Release {{ releaseStatus.release_id }}</p>
      <p v-if="versionError" class="text-body-2 mt-1">读取当前版本失败，可刷新重试。</p>
    </v-sheet>

    <AdminAsyncState :loading="loading" :error="loadError" :empty="!plans.length" empty-title="暂无升级计划" empty-description="上传签名升级包后，升级状态会显示在这里。" @retry="load">
      <v-sheet border rounded="lg">
        <v-data-table :headers="headers" :items="plans" item-value="plan_id" :items-per-page="10" items-per-page-text="每页行数">
          <template #item.target_version="{ item }">{{ item.target_version || '未提供版本信息' }}</template>
          <template #item.update_notes="{ item }"><span class="release-notes">{{ item.update_notes || '此升级包未提供更新说明' }}</span></template>
          <template #item.status="{ item }">
            <AdminStatusChip :status="item.status" :label="statusLabel(item)" />
            <div v-if="failedStep(item)?.failure_code" class="text-caption text-error mt-1">{{ failedStep(item)?.failure_code }}</div>
          </template>
          <template #item.actions="{ item }">
            <v-btn :to="'/apps/operations/upgrade/' + encodeURIComponent(item.plan_id)" size="small" variant="text">查看计划</v-btn>
          </template>
        </v-data-table>
      </v-sheet>
    </AdminAsyncState>

    <v-dialog v-model="uploadDialog" aria-label="上传升级包" max-width="620" :persistent="creating">
      <v-card>
        <v-card-title class="text-h6">上传升级包</v-card-title>
        <v-divider />
        <v-card-text class="pt-5">
          <v-file-input v-model="releaseFile" accept=".zip,application/zip" label="签名 Release ZIP" prepend-icon="mdi-file-upload-outline" variant="outlined" :disabled="creating" />
          <v-alert v-if="uploadError" class="mt-3" :text="uploadError" type="error" variant="tonal" />
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4"><v-spacer /><v-btn :disabled="creating" variant="text" @click="uploadDialog = false">取消</v-btn><v-btn color="primary" :loading="creating" :disabled="!releaseFile" @click="createPlan">上传</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsReleaseStatus, OperationsUpgradePlan } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { upgradeStatusLabel as statusLabel, failedUpgradeStep as failedStep } from '~/lib/upgrade-plan'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'

const client = useApi()
const notifier = useAdminNotifier()
const plans = ref<OperationsUpgradePlan[]>([])
const releaseStatus = ref<OperationsReleaseStatus | null>(null)
const versionError = ref(false)
const router = useRouter()
const loading = ref(false)
const creating = ref(false)
const loadError = ref<string | null>(null)
const uploadError = ref('')
const uploadDialog = ref(false)
const releaseFile = ref<File | File[] | null>(null)

const headers = [
  { title: '目标版本', key: 'target_version' },
  { title: '更新说明', key: 'update_notes' },
  { title: '升级状态', key: 'status' },
  { title: '操作', key: 'actions', sortable: false },
]
async function load() {
  loading.value = true
  loadError.value = null
  versionError.value = false
  const [version, list] = await Promise.allSettled([client.operations.releaseStatus(), client.operations.upgrades({ limit: 50 })])
  if (version.status === 'fulfilled') releaseStatus.value = version.value
  else versionError.value = true
  if (list.status === 'fulfilled') plans.value = list.value
  else loadError.value = isApiError(list.reason) ? list.reason.message : '无法加载升级计划'
  loading.value = false
}

async function createPlan() {
  const file = Array.isArray(releaseFile.value) ? releaseFile.value[0] : releaseFile.value
  if (!file) return
  creating.value = true; uploadError.value = ''
  try {
    const formData = new FormData()
    formData.append('file', file)
    const plan = await client.operations.createUpgrade(formData)
    plans.value = [plan, ...plans.value.filter((item) => item.plan_id !== plan.plan_id)]
    uploadDialog.value = false; releaseFile.value = null
    notifier.success('升级计划已创建')
    await router.push('/apps/operations/upgrade/' + encodeURIComponent(plan.plan_id))
  } catch (cause) { uploadError.value = isApiError(cause) ? cause.message : '上传升级包失败' }
  finally { creating.value = false }
}

onMounted(load)
</script>

<style scoped>
.release-notes { display: inline-block; max-width: 36rem; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
