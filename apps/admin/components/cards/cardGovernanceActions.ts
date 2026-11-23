import type { Card } from '@lovecards/sdk'

export type CardGovernanceAction = {
  method: 'approve' | 'ban' | 'hide' | 'top'
  label: string
  impact: string
}

type Capabilities = { approve: boolean; update: boolean; pin: boolean }

// 单条治理命令与批量 API 使用相同的方法值；按当前状态给出明确的结果文案。
export function cardGovernanceActions(card: Pick<Card, 'status' | 'is_top'>, caps: Capabilities): CardGovernanceAction[] {
  const status = Number(card.status)
  const actions: CardGovernanceAction[] = []
  if (caps.approve && status === 3) actions.push({ method: 'approve', label: '通过审核', impact: '这张待审核卡片将公开展示。' })
  if (caps.approve && (status === 0 || status === 1)) actions.push({ method: 'ban', label: status === 1 ? '解除封禁' : '封禁卡片', impact: status === 1 ? '卡片将恢复正常状态。' : '卡片将停止公开展示。' })
  if (caps.update && (status === 0 || status === 2)) actions.push({ method: 'hide', label: status === 2 ? '取消隐藏' : '隐藏卡片', impact: status === 2 ? '卡片将恢复正常状态。' : '卡片将停止公开展示。' })
  if (caps.pin && status === 0) actions.push({ method: 'top', label: Number(card.is_top) === 1 ? '取消置顶' : '置顶卡片', impact: '卡片在内容列表中的置顶顺序会立即更新。' })
  return actions
}
