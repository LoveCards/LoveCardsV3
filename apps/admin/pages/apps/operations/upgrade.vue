<template>
  <AdminPageContainer>
    <AdminPageHeader title="升级与迁移" description="以持久化计划、单步推进和审计记录管理 Release 与数据库迁移。" :breadcrumbs="[{ title: '系统' }]">
      <template #actions>
        <v-btn prepend-icon="mdi-refresh" variant="outlined" :loading="loading" @click="load">刷新</v-btn>
        <AdminActionGuard capability="system.update">
          <v-btn color="primary" prepend-icon="mdi-package-up" @click="uploadDialog = true">创建升级计划</v-btn>
        </AdminActionGuard>
      </template>
    </AdminPageHeader>

    <v-alert class="mb-5" type="info" variant="tonal">
      升级会先验证 ZIP、签名、兼容性和备份 Gate，再逐步执行迁移。每次推进只执行一个可恢复步骤；没有 Worker 或实时设施时，契约仍保持一致但相关步骤会明确降级。
    </v-alert>

    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="plans">Release 升级计划</v-tab>
      <v-tab value="migrations">数据库迁移</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="plans">
        <AdminAsyncState
          :loading="loadingPlans"
          :error="plansError"
          :empty="!plans.length"
          empty-title="暂无升级计划"
          empty-description="上传一个经过签名和兼容性检查的 Release ZIP 后，升级计划会在这里留下完整的推进记录。"
          empty-icon="mdi-package-variant-closed"
          @retry="loadPlans"
        >
          <v-sheet border rounded="lg">
            <v-data-table :headers="planHeaders" :items="plans" item-value="plan_id" :items-per-page="10" items-per-page-text="每页行数">
              <template #item.plan="{ item }">
                <strong>{{ item.release_id }}</strong>
                <div class="text-caption text-medium-emphasis">{{ item.plan_id }}</div>
              </template>
              <template #item.status="{ item }"><AdminStatusChip :status="item.status" /></template>
              <template #item.step="{ item }">
                <span>{{ stepLabel(item.current_step) }}</span>
                <div class="text-caption text-medium-emphasis">{{ completedSteps(item) }}/{{ item.steps.length }} 步已完成</div>
              </template>
              <template #item.contract="{ item }">
                <AdminStatusChip :status="item.contract_approved ? 'approved' : contractState(item)" :label="item.contract_approved ? '已批准' : contractState(item) === 'pending' ? '待批准' : '无需批准'" />
              </template>
              <template #item.package="{ item }">
                <span>{{ formatBytes(item.package.size_bytes) }}</span>
                <div class="text-caption text-medium-emphasis"><code>{{ shortChecksum(item.package.checksum) }}</code></div>
              </template>
              <template #item.actions="{ item }">
                <div class="d-flex flex-wrap ga-1">
                  <v-btn size="small" variant="text" prepend-icon="mdi-eye-outline" @click="openDetails(item)">详情</v-btn>
                  <AdminActionGuard capability="system.update">
                    <v-btn v-if="canAdvance(item)" size="small" color="primary" variant="text" prepend-icon="mdi-play" :loading="actionPlanId === item.plan_id && actionType === 'advance'" @click="openAdvance(item)">推进</v-btn>
                  </AdminActionGuard>
                  <AdminActionGuard capability="system.update.contract">
                    <v-btn v-if="canApprove(item)" size="small" color="warning" variant="text" prepend-icon="mdi-database-alert" @click="openContract(item)">批准 Contract</v-btn>
                  </AdminActionGuard>
                  <AdminActionGuard capability="system.recover">
                    <v-btn v-if="canRecover(item)" size="small" color="error" variant="text" prepend-icon="mdi-backup-restore" @click="openRecover(item)">恢复</v-btn>
                  </AdminActionGuard>
                </div>
              </template>
            </v-data-table>
          </v-sheet>
        </AdminAsyncState>
      </v-window-item>

      <v-window-item value="migrations">
        <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-3">
          <p class="text-body-2 text-medium-emphasis mb-0">安装基线和 Release 迁移共用同一份持久化账本；Contract 只能在 Release 已激活、备份 Gate 已完成的升级计划中审批。</p>
          <AdminActionGuard capability="operations.migrate">
            <div class="d-flex flex-wrap ga-2">
              <v-btn color="primary" prepend-icon="mdi-play" :loading="running" @click="run">推进下一阶段</v-btn>
            </div>
          </AdminActionGuard>
        </div>
        <v-alert v-if="migrationActionError" class="mb-3" type="error" variant="tonal" :text="migrationActionError" />
        <AdminAsyncState :loading="loadingMigrations" :error="migrationError" :empty="!migrations.length" empty-title="暂无迁移记录" @retry="loadMigrations">
          <v-sheet border rounded="lg">
            <v-data-table :headers="migrationHeaders" :items="migrations" item-value="id" items-per-page-text="每页行数">
              <template #item.name="{ item }">{{ item.migration?.description || item.migration?.id || item.id || '-' }}</template>
              <template #item.phase="{ item }"><AdminStatusChip :status="item.progress?.phase || item.phase || item.state" /></template>
              <template #item.status="{ item }"><AdminStatusChip :status="item.progress?.status || item.status || item.state" /></template>
              <template #item.attempts="{ item }">{{ item.progress?.attempts ?? 0 }}</template>
              <template #item.failure="{ item }"><span class="text-error">{{ item.progress?.failure_code || '-' }}</span></template>
            </v-data-table>
          </v-sheet>
        </AdminAsyncState>
      </v-window-item>
    </v-window>

    <v-dialog v-model="uploadDialog" aria-label="创建升级计划" max-width="620" :persistent="creating">
      <v-card>
        <v-card-title class="text-h6">创建升级计划</v-card-title>
        <v-divider />
        <v-card-text class="pt-5">
          <v-alert class="mb-4" type="info" variant="tonal" density="compact">ZIP 会被复制到受保护的升级暂存区，服务端会重新计算 checksum 并校验 Release Manifest。</v-alert>
          <v-file-input v-model="releaseFile" accept=".zip,application/zip" label="Release ZIP" prepend-icon="mdi-file-upload-outline" variant="outlined" :disabled="creating" :error-messages="uploadTouched && !releaseFile ? ['请选择 ZIP 文件'] : []" @blur="uploadTouched = true" />
          <v-alert v-if="uploadError" class="mt-3" :text="uploadError" type="error" variant="tonal" />
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4"><v-spacer /><v-btn :disabled="creating" variant="text" @click="uploadDialog = false">取消</v-btn><v-btn color="primary" :loading="creating" :disabled="!releaseFile" @click="createPlan">创建计划</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="detailDialog" aria-label="升级计划详情" max-width="820">
      <v-card v-if="selectedPlan">
        <v-card-title class="d-flex align-center justify-space-between ga-3"><span>升级计划详情</span><AdminStatusChip :status="selectedPlan.status" /></v-card-title>
        <v-divider />
        <v-card-text>
          <dl class="plan-summary mb-6">
            <dt>Release</dt><dd><code>{{ selectedPlan.release_id }}</code></dd>
            <dt>计划 ID</dt><dd><code>{{ selectedPlan.plan_id }}</code></dd>
            <dt>版本修订</dt><dd>{{ selectedPlan.revision }}</dd>
            <dt>ZIP</dt><dd>{{ formatBytes(selectedPlan.package.size_bytes) }} · <code>{{ selectedPlan.package.checksum || '-' }}</code></dd>
          </dl>
          <h3 class="text-subtitle-1 mb-2">步骤时间线</h3>
          <AdminStateTimeline :items="timelineItems(selectedPlan)" />
          <h3 class="text-subtitle-1 mt-5 mb-2">里程碑</h3>
          <v-list v-if="Object.keys(selectedPlan.milestones).length" density="compact" lines="one" border rounded="lg">
            <v-list-item v-for="(value, key) in selectedPlan.milestones" :key="key" :title="milestoneLabel(String(key))" :subtitle="String(value ?? '-')" />
          </v-list>
          <p v-else class="text-body-2 text-medium-emphasis">暂无里程碑记录。</p>
        </v-card-text>
        <v-divider /><v-card-actions class="pa-4"><v-spacer /><v-btn variant="text" @click="detailDialog = false">关闭</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

    <AdminDangerActionDialog v-model="advanceDialog" title="推进升级计划" action-label="推进一个步骤" :resource-label="selectedPlan ? `${selectedPlan.release_id} · ${stepLabel(selectedPlan.current_step)}` : ''" impact="本次请求只推进一个持久化步骤。若执行失败，计划会保留失败代码并可在修复环境后重试。" :loading="actionPlanId !== null && actionType === 'advance'" :error="actionError" @confirm="confirmAdvance" />
    <AdminDangerActionDialog v-model="contractDialog" title="批准 Contract 阶段" action-label="批准并继续" resource-label="数据库 Contract" impact="Contract 可能删除已弃用字段或索引，且该阶段执行后不可撤销。请先确认当前运行代码已经兼容新旧结构，批准结果会写入审计日志。" :loading="actionPlanId !== null && actionType === 'contract'" :error="actionError" @confirm="confirmContract" />
    <AdminDangerActionDialog v-model="recoverDialog" title="恢复升级前 Release" action-label="确认恢复" :resource-label="selectedPlan ? `${selectedPlan.release_id} · ${selectedPlan.plan_id}` : ''" impact="系统会使用升级计划的指针备份恢复旧 Release，数据库结构不会自动回退。恢复本身也会写入审计日志，请确认已经完成故障判断。" :loading="actionPlanId !== null && actionType === 'recover'" :error="actionError" @confirm="confirmRecover" />

  </AdminPageContainer>
</template>

<script setup lang="ts">
import type { OperationsMigration, OperationsUpgradePlan, OperationsUpgradeStep } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import AdminAsyncState from '~/components/shared/admin/AdminAsyncState.vue'
import AdminDangerActionDialog from '~/components/shared/admin/AdminDangerActionDialog.vue'
import AdminPageContainer from '~/components/shared/admin/AdminPageContainer.vue'
import AdminPageHeader from '~/components/shared/admin/AdminPageHeader.vue'
import AdminStateTimeline, { type AdminTimelineItem } from '~/components/shared/admin/AdminStateTimeline.vue'
import AdminStatusChip from '~/components/shared/admin/AdminStatusChip.vue'
import { useApi, isApiError } from '~/lib/api'
import { useAdminNotifier } from '~/composables/useAdminNotifier'

const client = useApi()
const notifier = useAdminNotifier()
const tab = ref<'plans' | 'migrations'>('plans')
const plans = ref<OperationsUpgradePlan[]>([])
const migrations = ref<OperationsMigration[]>([])
const loading = ref(false)
const loadingPlans = ref(false)
const loadingMigrations = ref(false)
const creating = ref(false)
const running = ref(false)
const plansError = ref<string | null>(null)
const migrationError = ref<string | null>(null)
const uploadError = ref('')
const migrationActionError = ref('')
const actionError = ref('')
const uploadDialog = ref(false)
const detailDialog = ref(false)
const advanceDialog = ref(false)
const contractDialog = ref(false)
const recoverDialog = ref(false)
const releaseFile = ref<File | File[] | null>(null)
const uploadTouched = ref(false)
const selectedPlan = ref<OperationsUpgradePlan | null>(null)
const actionPlanId = ref<string | null>(null)
const actionType = ref<'advance' | 'contract' | 'recover' | null>(null)

const migrationHeaders = [
  { title: '迁移', key: 'name' }, { title: '阶段', key: 'phase' }, { title: '状态', key: 'status' },
  { title: '尝试次数', key: 'attempts' }, { title: '失败代码', key: 'failure' },
]
const planHeaders = [
  { title: '计划 / Release', key: 'plan' }, { title: '状态', key: 'status' }, { title: '当前步骤', key: 'step' },
  { title: 'Contract', key: 'contract' }, { title: '包', key: 'package' }, { title: '操作', key: 'actions', sortable: false },
]

async function loadPlans() {
  loadingPlans.value = true; plansError.value = null
  try { plans.value = await client.operations.upgrades({ limit: 50 }) }
  catch (cause) { plansError.value = isApiError(cause) ? cause.message : '无法加载升级计划' }
  finally { loadingPlans.value = false }
}

async function loadMigrations() {
  loadingMigrations.value = true; migrationError.value = null
  try { migrations.value = await client.operations.migrations() }
  catch (cause) { migrationError.value = isApiError(cause) ? cause.message : '无法加载迁移计划' }
  finally { loadingMigrations.value = false }
}

async function load() {
  loading.value = true
  await Promise.all([loadPlans(), loadMigrations()])
  loading.value = false
}

async function createPlan() {
  uploadTouched.value = true
  const file = Array.isArray(releaseFile.value) ? releaseFile.value[0] : releaseFile.value
  if (!file) return
  creating.value = true; uploadError.value = ''
  try {
    const formData = new FormData()
    formData.append('file', file)
    const plan = await client.operations.createUpgrade(formData)
    plans.value = [plan, ...plans.value.filter((item) => item.plan_id !== plan.plan_id)]
    uploadDialog.value = false
    releaseFile.value = null; uploadTouched.value = false
    notifier.success('升级计划已创建，等待逐步推进')
  } catch (cause) { uploadError.value = isApiError(cause) ? cause.message : '创建升级计划失败' }
  finally { creating.value = false }
}

function openDetails(plan: OperationsUpgradePlan) { selectedPlan.value = plan; detailDialog.value = true }
function openAdvance(plan: OperationsUpgradePlan) { selectedPlan.value = plan; actionPlanId.value = plan.plan_id; actionType.value = 'advance'; actionError.value = ''; advanceDialog.value = true }
function openContract(plan: OperationsUpgradePlan) { selectedPlan.value = plan; actionPlanId.value = plan.plan_id; actionType.value = 'contract'; actionError.value = ''; contractDialog.value = true }
function openRecover(plan: OperationsUpgradePlan) { selectedPlan.value = plan; actionPlanId.value = plan.plan_id; actionType.value = 'recover'; actionError.value = ''; recoverDialog.value = true }

async function executePlanAction(type: 'advance' | 'contract' | 'recover') {
  if (!actionPlanId.value) return
  const planId = actionPlanId.value
  actionType.value = type; actionError.value = ''
  try {
    const plan = type === 'advance'
      ? await client.operations.advanceUpgrade(planId)
      : type === 'contract'
        ? await client.operations.approveUpgradeContract(planId)
        : await client.operations.recoverUpgrade(planId)
    plans.value = plans.value.map((item) => item.plan_id === plan.plan_id ? plan : item)
    selectedPlan.value = plan
    advanceDialog.value = false; contractDialog.value = false; recoverDialog.value = false
    notifier.success(type === 'recover' ? '升级计划已恢复旧 Release' : type === 'contract' ? 'Contract 已批准' : '升级计划已推进一个步骤')
  } catch (cause) { actionError.value = isApiError(cause) ? cause.message : '升级操作失败' }
  finally { actionPlanId.value = null; actionType.value = null }
}

function confirmAdvance() { void executePlanAction('advance') }
function confirmContract() { void executePlanAction('contract') }
function confirmRecover() { void executePlanAction('recover') }

async function run() {
  running.value = true; migrationActionError.value = ''
  try {
    migrations.value = await client.operations.runMigrations()
    notifier.success('迁移阶段已推进')
  }
  catch (cause) { migrationActionError.value = isApiError(cause) ? cause.message : '迁移执行失败' }
  finally { running.value = false }
}
function canAdvance(plan: OperationsUpgradePlan) {
  if (['completed', 'complete', 'recovered'].includes(plan.status) || !plan.current_step) return false
  return plan.current_step !== 'contract_migrations' || stepState(plan, plan.current_step) !== 'waiting' || plan.contract_approved
}
function canApprove(plan: OperationsUpgradePlan) { return !plan.contract_approved && plan.current_step === 'contract_migrations' && stepState(plan, plan.current_step) === 'waiting' }
function canRecover(plan: OperationsUpgradePlan) {
  const contract = plan.steps.find((step) => step.name === 'contract_migrations')
  const contractStarted = contract ? contract.attempts > 0 || ['running', 'failed', 'succeeded'].includes(contract.state) : true
  return Boolean(plan.milestones.activated_at) && plan.status !== 'recovered' && !plan.milestones.recovered_at && !contractStarted
}
function stepState(plan: OperationsUpgradePlan, name: string | null) { return plan.steps.find((step) => step.name === name)?.state || '' }
function completedSteps(plan: OperationsUpgradePlan) { return plan.steps.filter((step) => ['succeeded', 'skipped'].includes(step.state)).length }
function contractState(plan: OperationsUpgradePlan) { return plan.current_step === 'contract_migrations' && stepState(plan, plan.current_step) === 'waiting' ? 'pending' : 'disabled' }
function stepLabel(step: string | null) { return ({ environment_probe: '环境探测', import_release: '导入 Release', verify_release: '校验 Release', stage_release: '暂存 Release', schema_check: 'Schema 检查', preflight_migrations: '迁移预检', expand_migrations: '扩展迁移', backfill: '回填数据', verify_migrations: '迁移校验', backup_gate: '备份 Gate', drain_runtime: '排空运行时', activate_release: '激活 Release', reload_runtime: '重载运行时', health_check: '健康检查', contract_migrations: 'Contract 迁移' } as Record<string, string>)[step || ''] || step || '等待开始' }
function milestoneLabel(key: string) { return ({ created_at: '创建时间', environment_verified_at: '环境已验证', verified_at: '包已验证', staged_at: 'Release 已暂存', schema_checked_at: 'Schema 已检查', preflight_migrations_completed_at: '迁移预检完成', expand_migrations_completed_at: '扩展迁移完成', backfill_migrations_completed_at: '回填完成', backup_gate_at: '备份 Gate', migrations_verified_at: '迁移已验证', activated_at: 'Release 已激活', health_checked_at: '健康检查', contract_executed_at: 'Contract 已开始', contract_migrations_completed_at: 'Contract 已完成', recovered_at: '恢复时间', restored_release_id: '恢复到 Release', runtime_drained: '运行时已排空', runtime_reloaded: '运行时已重载' } as Record<string, string>)[key] || key }
function timelineItems(plan: OperationsUpgradePlan): AdminTimelineItem[] { return plan.steps.map((step: OperationsUpgradeStep) => ({ id: step.name, title: stepLabel(step.name), description: step.failure_code ? `失败代码：${step.failure_code}` : step.cursor ? `游标：${step.cursor}` : null, status: step.state, timestamp: step.state === 'succeeded' || step.state === 'skipped' ? '已记录' : null })) }
function shortChecksum(value: string | null) { return value ? `${value.slice(0, 12)}…` : '-' }
function formatBytes(value: number | null) { if (value === null || value === undefined) return '-'; if (value < 1024) return `${value} B`; if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`; return `${(value / 1024 / 1024).toFixed(1)} MB` }

onMounted(load)
</script>

<style scoped>
.plan-summary { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 8px 20px; padding: 16px; border: 1px solid rgb(var(--v-theme-on-surface), 0.12); border-radius: 8px; background: rgb(var(--v-theme-surface-variant)); }
.plan-summary dt { font-weight: 600; }
.plan-summary dd { margin: 0; overflow-wrap: anywhere; }
code { overflow-wrap: anywhere; }
</style>
