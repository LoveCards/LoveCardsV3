<template>
  <AdminPageContainer :max-width="1040">
    <AdminPageHeader title="升级计划" :description="plan?.target_version ? '目标软件版本 ' + plan.target_version : '查看升级步骤和当前可执行的操作。'" :breadcrumbs="[{ title: '系统' }, { title: '系统升级', to: '/apps/operations/upgrade' }, { title: '升级计划' }]">
      <template #actions><v-btn prepend-icon="mdi-refresh" variant="outlined" :disabled="acting" :loading="loading" @click="load">刷新</v-btn></template>
    </AdminPageHeader>
    <AdminAsyncState :loading="loading && !plan" :error="loadError" :empty="!plan" @retry="load">
      <template v-if="plan">
        <AdminDetailSection title="当前状态" class="mb-5">
          <div class="d-flex flex-wrap align-center ga-3 mb-3">
            <AdminStatusChip :status="plan.status" :label="upgradeStatusLabel(plan)" />
            <span v-if="plan.current_step">{{ upgradeStepLabel(plan.current_step) }}</span>
          </div>
          <p class="text-body-2 mb-2">Release {{ plan.release_id }} · 计划版本 {{ plan.revision }}</p>
          <p v-if="plan.update_notes" class="text-pre-wrap mb-3">{{ plan.update_notes }}</p>
          <v-alert v-if="plan.execution_uncertain" type="warning" variant="tonal" class="mb-3">请先核实上次执行的 SQL 与子进程状态，再按升级恢复流程处理。</v-alert>
          <v-alert v-else-if="plan.legacy_plan" type="info" variant="tonal" class="mb-3">此旧版计划只提供历史记录。</v-alert>
          <div class="d-flex flex-wrap ga-2">
            <AdminActionGuard capability="system.update"><v-btn v-if="canAdvance" color="primary" @click="openAction('advance')">{{ plan.status === 'failed' ? '重试失败步骤' : '继续升级' }}</v-btn></AdminActionGuard>
            <AdminActionGuard capability="system.update.contract"><v-btn v-if="canApproveUpgrade(plan)" color="warning" @click="openAction('contract')">批准数据库收缩</v-btn></AdminActionGuard>
            <AdminActionGuard capability="system.recover"><v-btn v-if="canRecover" color="error" variant="outlined" @click="openAction('recover')">恢复旧版本</v-btn></AdminActionGuard>
          </div>
        </AdminDetailSection>
        <AdminDetailSection title="执行步骤">
          <v-list lines="two">
            <v-list-item v-for="step in plan.steps" :key="step.name" :title="upgradeStepLabel(step.name)" :subtitle="step.failure_code ? '失败代码 ' + step.failure_code : '尝试 ' + step.attempts + ' 次'">
              <template #append><AdminStatusChip :status="step.state" :label="upgradeStepState(step.state)" /></template>
            </v-list-item>
          </v-list>
        </AdminDetailSection>
      </template>
    </AdminAsyncState>
    <AdminDangerActionDialog v-model="actionDialog" :title="actionTitle" :action-label="actionTitle" :resource-label="plan?.target_version || plan?.release_id || ''" :impact="actionImpact" :confirm-text="actionType === 'contract' ? '批准 CONTRACT' : undefined" :color="actionType === 'advance' ? 'primary' : 'error'" :loading="acting" :error="actionError" @confirm="confirmAction" />
  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsUpgradePlan } from '@lovecards/sdk'
import { canApproveUpgrade, currentUpgradeStep, upgradeStatusLabel, upgradeStepLabel, upgradeStepState } from '~/lib/upgrade-plan'
import { isApiError, useApi } from '~/lib/api'
import { useUserStore } from '~/stores/userStore'

const route = useRoute()
const client = useApi()
const userStore = useUserStore()
const notifier = useAdminNotifier()
const plan = ref<OperationsUpgradePlan | null>(null)
const loading = ref(false)
const loadError = ref<string | null>(null)
const actionDialog = ref(false)
const actionType = ref<'advance' | 'contract' | 'recover'>('advance')
const acting = ref(false)
const actionError = ref('')
const actionTitle = computed(() => ({ advance: '继续升级', contract: '批准数据库收缩', recover: '恢复旧版本' })[actionType.value])
const actionImpact = computed(() => ({
  advance: '本次推进一个步骤，失败时可查看对应代码。',
  contract: '批准后可推进数据库结构收缩，请先核对备份和新版本健康状态。',
  recover: '恢复旧 Release 指针，数据库恢复使用升级前备份。',
})[actionType.value])
const canAdvance = computed(() => {
  const current = plan.value
  return !!current?.current_step && !current.legacy_plan && !current.execution_uncertain && !current.milestones.pointer_restored_at
    && !['completed', 'recovered'].includes(current.status) && currentUpgradeStep(current)?.state !== 'running' && !canApproveUpgrade(current)
})
const canRecover = computed(() => {
  const current = plan.value
  if (!current || current.legacy_plan || current.execution_uncertain || current.status === 'recovered') return false
  const contract = current.steps.find(step => step.name === 'contract_migrations')
  return !!current.previous_release_id && !!current.milestones.activated_at && !!contract && contract.attempts === 0
    && !['running', 'failed', 'succeeded'].includes(contract.state)
})
let latestLoad = 0
async function load() {
  const requestId = ++latestLoad
  const planId = String(route.params.planId)
  if (plan.value?.plan_id !== planId) plan.value = null
  loading.value = true
  loadError.value = null
  try {
    const result = await client.operations.upgrade(planId)
    if (requestId === latestLoad) plan.value = result
  } catch (cause) {
    if (requestId === latestLoad) loadError.value = isApiError(cause) ? cause.message : '无法加载升级计划'
  } finally {
    if (requestId === latestLoad) loading.value = false
  }
}
function openAction(type: typeof actionType.value) {
  actionType.value = type
  actionError.value = ''
  actionDialog.value = true
}
async function confirmAction() {
  const current = plan.value
  if (!current || acting.value || loading.value || current.plan_id !== route.params.planId) return
  const action = actionType.value
  const allowed = action === 'advance' ? canAdvance.value && userStore.hasGrant('system.update', 'global')
    : action === 'contract' ? canApproveUpgrade(current) && userStore.hasGrant('system.update.contract', 'global')
      : canRecover.value && userStore.hasGrant('system.recover', 'global')
  if (!allowed) return
  acting.value = true
  actionError.value = ''
  try {
    const updated = action === 'advance' ? await client.operations.advanceUpgrade(current.plan_id)
      : action === 'contract' ? await client.operations.approveUpgradeContract(current.plan_id)
        : await client.operations.recoverUpgrade(current.plan_id)
    if (route.params.planId === updated.plan_id) plan.value = updated
    actionDialog.value = false
    notifier.success('升级计划已更新')
  } catch (cause) {
    actionError.value = isApiError(cause) ? cause.message : '升级操作失败'
    if (isApiError(cause) && cause.status === 409) await load()
  } finally { acting.value = false }
}
watch(() => route.params.planId, () => { void load() }, { immediate: true })
</script>
