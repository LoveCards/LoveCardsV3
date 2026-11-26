import type { OperationsUpgradePlan } from '@lovecards/sdk'

export function isUpgradeLocked(plan: OperationsUpgradePlan) {
  return plan.legacy_plan || plan.execution_uncertain || Boolean(plan.milestones.pointer_restored_at)
    || ['completed', 'complete', 'recovered'].includes(plan.status)
}

export function canApproveUpgrade(plan: OperationsUpgradePlan) {
  return !isUpgradeLocked(plan) && !plan.contract_approved && plan.current_step === 'contract_migrations'
    && plan.steps.find((step) => step.name === plan.current_step)?.state === 'waiting'
}

export function canAdvanceUpgrade(plan: OperationsUpgradePlan) {
  return !isUpgradeLocked(plan) && Boolean(plan.current_step) && !canApproveUpgrade(plan)
}

export function canRecoverUpgrade(plan: OperationsUpgradePlan) {
  const contract = plan.steps.find((step) => step.name === 'contract_migrations')
  const contractStarted = !contract || Boolean(plan.milestones.contract_executed_at) || ['running', 'failed', 'succeeded'].includes(contract.state)
  return !plan.legacy_plan && !plan.execution_uncertain
    && Boolean(plan.previous_release_id && plan.milestones.activated_at) && plan.status !== 'recovered'
    && !plan.milestones.recovered_at && !plan.service_recovered && !contractStarted
}

export function upgradeStatusLabel(plan: OperationsUpgradePlan) {
  if (plan.execution_uncertain) return '执行结果待核实'
  if (plan.legacy_plan) return '历史计划（只读）'
  if (plan.service_recovered) return '旧版本服务已恢复'
  if (plan.milestones.pointer_restored_at || plan.status === 'recovered') return '旧指针已还原，健康待核实'
  if (canApproveUpgrade(plan)) return '等待数据库收缩批准'
  if (plan.status === 'failed') return '升级失败'
  if (['completed', 'complete'].includes(plan.status)) return '升级完成'
  if (plan.status === 'pending') return '等待开始'
  return '升级中'
}

export const upgradeStepLabels: Record<string, string> = {
  environment_probe: '验证运行环境', import_release: '导入 Release', verify_release: '验证升级包', stage_release: '准备 Release',
  schema_check: '验证数据库结构', preflight_migrations: '迁移预检', expand_migrations: '扩展数据库',
  backfill: '回填数据', verify_migrations: '验证迁移', backup_gate: '确认备份',
  activate_release: '激活 Release', health_check: '检查服务健康', contract_migrations: '收缩数据库',
  drain_runtime: '排空运行时', reload_runtime: '重载运行时', final_health: '最终健康验证',
}
