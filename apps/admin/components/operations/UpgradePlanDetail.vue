<template>
  <v-sheet border rounded="lg" class="pa-4 pa-sm-6" data-testid="upgrade-plan-detail">
    <div class="d-flex align-start flex-wrap ga-3 mb-5">
      <div class="flex-grow-1">
        <h2 class="text-h6">升级诊断 · {{ plan.target_version || plan.release_id }}</h2>
        <p class="text-body-2 text-medium-emphasis mt-1">{{ upgradeStatusLabel(plan) }}</p>
      </div>
      <v-btn variant="text" prepend-icon="mdi-close" @click="$emit('close')">收起</v-btn>
    </div>

    <v-alert v-if="plan.legacy_plan" type="info" variant="tonal" class="mb-4"><span class="text-high-emphasis">此计划使用历史协议，仅供诊断，不能继续执行或恢复。</span></v-alert>
    <v-alert v-if="plan.execution_uncertain" type="warning" variant="tonal" class="mb-4"><span class="text-high-emphasis">上次执行结果尚未确认。需先核实 SQL、子进程与 Release 状态；不能因锁到期直接重复执行。</span></v-alert>
    <v-alert v-if="plan.milestones.pointer_restored_at && !plan.service_recovered" type="warning" variant="tonal" class="mb-4"><span class="text-high-emphasis">旧 Release 指针已还原，但尚无服务健康恢复的确认。指针还原不代表数据库已恢复，请按运维流程核对。</span></v-alert>

    <dl class="upgrade-facts mb-5">
      <div><dt>计划编号</dt><dd>{{ plan.plan_id }}</dd></div>
      <div><dt>当前步骤</dt><dd>{{ stepLabel(plan.current_step) }}</dd></div>
      <div><dt>新 Release</dt><dd>{{ plan.release_id }}</dd></div>
      <div><dt>旧 Release</dt><dd>{{ plan.previous_release_id || '—' }}</dd></div>
      <div><dt>计划协议 / 修订</dt><dd>{{ plan.plan_version }} / {{ plan.revision }}</dd></div>
      <div><dt>数据库收缩</dt><dd>{{ plan.contract_approved ? '已批准' : '未批准' }}</dd></div>
      <div><dt>升级包大小</dt><dd>{{ plan.package.size_bytes === null ? '—' : formatSize(plan.package.size_bytes) }}</dd></div>
      <div><dt>升级包校验值</dt><dd class="text-caption">{{ plan.package.checksum || '—' }}</dd></div>
    </dl>

    <div v-if="plan.update_notes" class="mb-5"><h3 class="text-subtitle-2 mb-2">更新说明</h3><p class="text-body-2 release-notes">{{ plan.update_notes }}</p></div>
    <h3 class="text-subtitle-2 mb-2">执行步骤</h3>
    <v-table density="comfortable" class="upgrade-steps mb-5">
      <thead><tr><th>步骤</th><th>状态</th><th>尝试次数</th><th>游标</th><th>失败代码</th></tr></thead>
      <tbody><tr v-for="step in plan.steps" :key="step.name">
        <td :class="{ 'font-weight-medium': step.name === plan.current_step }">{{ stepLabel(step.name) }}</td>
        <td>{{ stateLabels[step.state] || step.state }}</td><td>{{ step.attempts }}</td>
        <td><code>{{ displayValue(step.cursor) }}</code></td><td><code :class="{ 'text-error': step.failure_code }">{{ step.failure_code || '—' }}</code></td>
      </tr></tbody>
    </v-table>

    <v-expansion-panels variant="accordion" class="mb-5">
      <v-expansion-panel title="里程碑与运行标记">
        <v-expansion-panel-text>
          <dl class="upgrade-facts"><div v-for="(value, key) in plan.milestones" :key="key"><dt>{{ milestoneLabels[key] || key }}</dt><dd>{{ displayValue(value) }}</dd></div></dl>
          <p v-if="!Object.keys(plan.milestones).length" class="text-body-2 text-medium-emphasis">尚未记录里程碑。</p>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <div class="d-flex flex-wrap ga-2 justify-end">
      <AdminActionGuard capability="system.recover"><v-btn v-if="canRecoverUpgrade(plan)" variant="outlined" :disabled="busy" @click="$emit('action', 'recover')">{{ plan.milestones.pointer_restored_at ? '继续恢复' : '恢复旧版本' }}</v-btn></AdminActionGuard>
      <AdminActionGuard capability="system.update.contract"><v-btn v-if="canApproveUpgrade(plan)" variant="outlined" :disabled="busy" @click="$emit('action', 'contract')">批准数据库收缩</v-btn></AdminActionGuard>
      <AdminActionGuard capability="system.update"><v-btn v-if="canAdvanceUpgrade(plan)" color="primary" variant="flat" :disabled="busy" @click="$emit('action', 'advance')">继续升级</v-btn></AdminActionGuard>
    </div>
  </v-sheet>
</template>

<script setup lang="ts">
import type { OperationsUpgradePlan } from '@lovecards/sdk'
import AdminActionGuard from '~/components/shared/admin/AdminActionGuard.vue'
import { formatSize } from '~/utils/storage'
import { canAdvanceUpgrade, canApproveUpgrade, canRecoverUpgrade, upgradeStatusLabel, upgradeStepLabels } from './upgradeState'

defineProps<{ plan: OperationsUpgradePlan; busy: boolean }>()
defineEmits<{ close: []; action: [type: 'advance' | 'contract' | 'recover'] }>()
const stepLabel = (name: string | null) => name ? upgradeStepLabels[name] || name : '—'
const stateLabels: Record<string, string> = { pending: '等待执行', running: '执行中', succeeded: '已完成', skipped: '已跳过', waiting: '等待批准', failed: '失败' }
const displayValue = (value: unknown) => value === null || value === undefined ? '—' : typeof value === 'boolean' ? value ? '是' : '否' : typeof value === 'object' ? JSON.stringify(value) : String(value)
const milestoneLabels: Record<string, string> = {
  created_at: '计划创建', environment_verified_at: '环境已验证', verified_at: '升级包已验证', staged_at: 'Release 已准备',
  schema_checked_at: '数据库结构已检查', preflight_migrations_completed_at: '迁移预检完成', expand_migrations_completed_at: '数据库扩展完成',
  backfill_migrations_completed_at: '数据回填完成', migrations_verified_at: '迁移已验证', backup_gate_at: '备份门禁通过',
  activated_at: '新 Release 已激活', health_checked_at: '健康检查完成', contract_executed_at: '收缩已执行',
  contract_migrations_completed_at: '收缩迁移完成', recovered_at: '恢复步骤完成', pointer_restored_at: '旧指针已还原',
  final_health_checked_at: '恢复后健康检查', restored_release_id: '还原至 Release', runtime_drained: '运行时已排空', runtime_reloaded: '运行时已重载',
}
</script>

<style scoped>
.upgrade-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 32px; }
.upgrade-facts dt { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)); font-size: .75rem; margin-bottom: 4px; }
.upgrade-facts dd { margin: 0; font-size: .875rem; overflow-wrap: anywhere; }
.upgrade-steps :deep(th) { white-space: nowrap; }
.upgrade-steps :deep(td) { min-width: 100px; }
.release-notes { white-space: pre-wrap; overflow-wrap: anywhere; }
@media (max-width: 599px) { .upgrade-facts { grid-template-columns: minmax(0, 1fr); } }
</style>
