export interface Comment {
  id: number
  card_id: number
  user_id: number
  parent_id: number | null
  content: string
  status: number
  created_at: string
  updated_at: string | null
  deleted: boolean
  /** Legacy moderation fields are not present on public comment responses. */
  aid?: number
  pid?: number
  data?: unknown[]
  is_top?: number
  goods?: number
  post_ip?: string
}

export interface CreateCommentParams {
  content: string
  parent_id?: number
}
