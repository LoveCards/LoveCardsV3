import type { Comment } from '@lovecards/sdk'

export type CommentGovernanceAction = {
  method: 'approve' | 'ban' | 'hide' | 'pin' | 'unpin'
  label: string
  impact: string
}

// 审核与状态命令使用服务端批量契约；置顶使用所属卡片的专用命令。
export function commentGovernanceActions(comment: Comment, caps: { moderate: boolean; pin: boolean }): CommentGovernanceAction[] {
  if (comment.deleted) return []
  const status = Number(comment.status)
  const actions: CommentGovernanceAction[] = []
  if (caps.moderate && status === 3) actions.push({ method: 'approve', label: '通过审核', impact: '待审核评论通过后才会公开；若所属卡片或父评论不可见，服务端将拒绝。' })
  if (caps.moderate && (status === 0 || status === 1)) actions.push({ method: 'ban', label: status === 1 ? '解除封禁' : '封禁评论', impact: status === 1 ? '评论将恢复正常状态。' : '评论将停止公开展示，并取消置顶。' })
  if (caps.moderate && (status === 0 || status === 2)) actions.push({ method: 'hide', label: status === 2 ? '取消隐藏' : '隐藏评论', impact: status === 2 ? '评论将恢复正常状态。' : '评论将停止公开展示，并取消置顶。' })
  if (caps.pin && status === 0 && !comment.parent_id) actions.push({ method: comment.is_pinned ? 'unpin' : 'pin', label: comment.is_pinned ? '取消置顶' : '置顶评论', impact: comment.is_pinned ? '评论将回到普通顺序。' : '评论会成为所属卡片唯一的置顶评论，并替换原置顶评论。' })
  return actions
}
