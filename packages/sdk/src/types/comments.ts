export interface Comment {
  id: number
  aid: number
  pid: number
  user_id: number
  parent_id: number | null
  content: string
  data: Record<string, any>
  is_top: number
  goods: number
  post_ip: string
  status: number
  created_at: string
  updated_at: string
  children?: Comment[]
}

export interface CreateCommentParams {
  content: string
  parent_id?: number
}
