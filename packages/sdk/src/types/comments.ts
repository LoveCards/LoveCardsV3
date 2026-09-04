import type { ContentMention } from './mentions'

export interface Comment {
  id: number
  card_id: number
  user_id: number
  uid: string | null
  username?: string
  parent_id: number | null
  content: string
  mentions: ContentMention[]
  status: number
  goods: number
  created_at: string
  updated_at: string | null
  deleted: boolean
  is_pinned: boolean
  /** Legacy moderation fields are not present on public comment responses. */
  aid?: number
  pid?: number
  data?: unknown[]
  post_ip?: string
}

export interface CreateCommentParams {
  content: string
  parent_id?: number
}
