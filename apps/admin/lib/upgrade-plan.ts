import type { OperationsUpgradePlan } from '@lovecards/sdk'

const stepLabels: Record<string, string> = {
  environment_probe: '检查运行环境', import_release: '导入升级包', verify_release: '校验签名', stage_release: '准备新版本',
  schema_check: '检查数据库结构', preflight_migrations: '迁移预检', expand_migrations: '扩展数据库结构',
  backfill: '回填数据', verify_migrations: '核对迁移结果', backup_gate: '检查备份', drain_runtime: '等待在途任务结束',
  activate_release: '启用新版本', reload_runtime: '重新加载服务', health_check: '检查服务健康',
  contract_migrations: '收缩数据库结构', final_health: '最终健康检查',
}
const stateLabels: Record<string, string> = { pending: '等待开始', running: '正在执行', waiting: '等待批准', succeeded: '完成', failed: '失败', skipped: '已跳过' }
export const upgradeStepLabel = (name: string) => stepLabels[name] ?? name
export const upgradeStepState = (state: string) => stateLabels[state] ?? state
export const currentUpgradeStep = (plan: OperationsUpgradePlan) => plan.steps.find(step => step.name === plan.current_step)
export const failedUpgradeStep = (plan: OperationsUpgradePlan) => plan.steps.find(step => step.state === 'failed')
export const canApproveUpgrade = (plan: OperationsUpgradePlan) => !plan.legacy_plan && !plan.execution_uncertain
  && !plan.contract_approved && plan.current_step === 'contract_migrations' && currentUpgradeStep(plan)?.state === 'waiting'

export function upgradeStatusLabel(plan: OperationsUpgradePlan) {
  if (plan.milestones.pointer_restored_at && !plan.service_recovered) return '指针已恢复，服务待确认'
  if (plan.execution_uncertain) return '执行结果待确认'
  if (canApproveUpgrade(plan)) return '等待数据库收缩批准'
  return ({ failed: '升级失败', completed: '升级完成', recovered: '已恢复旧版本', pending: '等待开始', waiting: '等待操作', running: '升级中' } as Record<string, string>)[plan.status] ?? plan.status
}
