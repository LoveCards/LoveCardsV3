export interface Comment {
  id: number
  aid: number
  pid: number
  user_id: number
  parent_id: number | null
  content: string
  status: number
  created_at: string
  updated_at: string
  children?: Comment[]
}

export interface CreateCommentParams {
  pid: number
  content: string
  parent_id?: number
}
