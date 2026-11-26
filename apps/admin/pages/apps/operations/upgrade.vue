<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader title="系统升级" description="上传已签名的升级包，并查看升级进度。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading || detailLoading" @click="refresh">刷新</v-btn>
        <AdminActionGuard capability="system.update">
          <v-btn color="primary" prepend-icon="mdi-package-up" @click="uploadDialog = true">上传升级包</v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>

    <v-sheet class="mb-5 pa-4" border rounded="lg">
      <div class="text-caption text-medium-emphasis">当前版本</div>
      <div class="text-h6">{{ currentVersion || (loading ? '读取中' : '未知') }}</div>
    </v-sheet>

    <section v-if="selectedPlanId" class="mb-5" aria-label="升级计划诊断">
      <AdminAsyncState :loading="detailLoading" :error="detailError" error-title="无法读取升级诊断" @retry="loadDetail">
        <UpgradePlanDetail v-if="detailPlan" :plan="detailPlan" :busy="actionPlanId !== null" @close="selectPlan(null)" @action="openAction" />
      </AdminAsyncState>
    </section>

    <AdminAsyncState :loading="loading" :error="loadError" :empty="!plans.length" empty-title="暂无升级计划" empty-description="上传签名升级包后，升级状态会显示在这里。" @retry="load">
      <v-sheet border rounded="lg">
        <v-data-table :headers="headers" :items="plans" item-value="plan_id" :items-per-page="10" items-per-page-text="每页行数">
          <template #item.target_version="{ item }">{{ item.target_version || '未提供版本信息' }}</template>
          <template #item.update_notes="{ item }"><span class="release-notes">{{ item.update_notes || '此升级包未提供更新说明' }}</span></template>
          <template #item.status="{ item }">
            <AdminStatusChip :status="item.status" :label="upgradeStatusLabel(item)" />
          </template>
          <template #item.failure_code="{ item }"><code v-if="failedStep(item)?.failure_code" class="text-error">{{ failedStep(item)?.failure_code }}</code><span v-else>—</span></template>
          <template #item.actions="{ item }">
            <AdminTableRowAction @click="selectPlan(item.plan_id)">查看</AdminTableRowAction>
          </template>
        </v-data-table>
      </v-sheet>
    </AdminAsyncState>

    <v-dialog v-model="uploadDialog" aria-label="上传升级包" max-width="620" :persistent="creating">
      <v-card>
        <v-card-title class="text-h6">上传升级包</v-card-title>
        <v-divider />
        <v-card-text class="pt-5">
          <p class="mb-4 text-body-2 text-medium-emphasis">选择已签名的 Release ZIP；上传后会立即创建升级计划。</p>
          <v-file-input v-model="releaseFile" accept=".zip,application/zip" label="签名 Release ZIP" prepend-icon="mdi-file-upload-outline" variant="outlined" :disabled="creating" />
          <v-alert v-if="uploadError" class="mt-3" :text="uploadError" type="error" variant="tonal" />
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4"><v-spacer /><v-btn :disabled="creating" variant="text" @click="uploadDialog = false">取消</v-btn><v-btn color="primary" :loading="creating" :disabled="!releaseFile" variant="flat" @click="createPlan">上传</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

    <AdminDangerActionDialog v-model="actionDialog" :title="actionTitle" :action-label="actionTitle" :resource-label="selectedPlan?.target_version || selectedPlan?.release_id || ''" :impact="actionImpact" :color="actionType === 'advance' ? 'primary' : 'error'" :loading="actionPlanId !== null" :error="actionError" @confirm="confirmAction" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsUpgradePlan } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import UpgradePlanDetail from '~/components/operations/UpgradePlanDetail.vue'
import { canAdvanceUpgrade, canApproveUpgrade, canRecoverUpgrade, upgradeStatusLabel } from '~/components/operations/upgradeState'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'

const client = useApi()
const route = useRoute()
const router = useRouter()
const notifier = useAdminNotifier()
const plans = ref<OperationsUpgradePlan[]>([])
const currentVersion = ref('')
const loading = ref(false)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailPlan = ref<OperationsUpgradePlan | null>(null)
const selectedPlanId = computed(() => typeof route.query.plan === 'string' ? route.query.plan.trim() : '')
let detailRequest = 0
const creating = ref(false)
const loadError = ref<string | null>(null)
const uploadError = ref('')
const actionError = ref('')
const uploadDialog = ref(false)
const actionDialog = ref(false)
const releaseFile = ref<File | File[] | null>(null)
const selectedPlan = ref<OperationsUpgradePlan | null>(null)
const actionPlanId = ref<string | null>(null)
const actionType = ref<'advance' | 'contract' | 'recover'>('advance')

const headers = [
  { title: '目标版本', key: 'target_version', minWidth: 160 },
  { title: '更新说明', key: 'update_notes', minWidth: 320 },
  { title: '升级状态', key: 'status', minWidth: 144 },
  { title: '失败代码', key: 'failure_code', sortable: false, minWidth: 160 },
  { title: '操作', key: 'actions', sortable: false, width: 100 },
]
const actionTitle = computed(() => actionType.value === 'contract' ? '批准数据库收缩' : actionType.value === 'recover' ? selectedPlan.value?.milestones.pointer_restored_at ? '继续恢复' : '恢复旧版本' : '继续升级')
const actionImpact = computed(() => actionType.value === 'contract'
  ? '批准后可能删除旧数据库结构，请确认备份和新版本健康状态。'
  : actionType.value === 'recover'
    ? '仅恢复旧 Release 指针；数据库恢复依赖升级前备份。'
    : '本次只推进一个持久化步骤，失败会保留状态和原因。')

async function load() {
  loading.value = true; loadError.value = null
  try {
    plans.value = await client.operations.upgrades({ limit: 50 })
    currentVersion.value = plans.value[0]?.current_version || ''
  } catch (cause) { loadError.value = isApiError(cause) ? cause.message : '无法加载升级状态' }
  finally { loading.value = false }
  if (!loadError.value && !currentVersion.value) {
    try { currentVersion.value = (await client.system.update()).ver?.version || '' }
    catch { currentVersion.value = '' }
  }
}

async function selectPlan(planId: string | null) {
  await router.replace({ query: { ...route.query, plan: planId || undefined } })
}

async function loadDetail() {
  const planId = selectedPlanId.value
  const request = ++detailRequest
  detailPlan.value = null; detailError.value = null
  if (!planId) { detailLoading.value = false; return }
  detailLoading.value = true
  try {
    const plan = await client.operations.upgrade(planId)
    if (request === detailRequest) detailPlan.value = plan
  } catch (cause) {
    if (request === detailRequest) detailError.value = isApiError(cause) ? cause.message : '无法读取升级计划'
  } finally { if (request === detailRequest) detailLoading.value = false }
}

async function refresh() { await Promise.all([load(), loadDetail()]) }

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
    await selectPlan(plan.plan_id)
    notifier.success('升级计划已创建')
  } catch (cause) { uploadError.value = isApiError(cause) ? cause.message : '上传升级包失败' }
  finally { creating.value = false }
}

function failedStep(plan: OperationsUpgradePlan) { return plan.steps.find((step) => step.state === 'failed') }
function openAction(type: 'advance' | 'contract' | 'recover') {
  const plan = detailPlan.value
  if (!plan || !(type === 'advance' ? canAdvanceUpgrade(plan) : type === 'contract' ? canApproveUpgrade(plan) : canRecoverUpgrade(plan))) return
  selectedPlan.value = plan; actionType.value = type; actionError.value = ''; actionDialog.value = true
}
async function confirmAction() {
  if (!selectedPlan.value) return
  const planId = selectedPlan.value.plan_id
  actionPlanId.value = planId; actionError.value = ''
  try {
    const plan = actionType.value === 'advance'
      ? await client.operations.advanceUpgrade(planId)
      : actionType.value === 'contract'
        ? await client.operations.approveUpgradeContract(planId)
        : await client.operations.recoverUpgrade(planId)
    plans.value = plans.value.map((item) => item.plan_id === plan.plan_id ? plan : item)
    if (selectedPlanId.value === plan.plan_id) detailPlan.value = plan
    actionDialog.value = false
    notifier.success(actionType.value === 'recover' ? plan.service_recovered ? '旧版本服务已恢复' : '旧 Release 指针已还原，请核对服务健康' : '升级状态已更新')
  } catch (cause) { actionError.value = isApiError(cause) ? cause.message : '升级操作失败' }
  finally { actionPlanId.value = null }
}

watch(selectedPlanId, loadDetail)
onMounted(refresh)
</script>

<style scoped>
.release-notes { display: inline-block; max-width: 36rem; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
