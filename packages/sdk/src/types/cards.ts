export interface Card {
  id: number
  aid: number
  user_id: number
  status: number
  is_top: number
  content: string
  data: Record<string, any>
  cover: string
  tags: string
  goods: number
  views: number
  comments: number
  post_ip: string
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export interface CardsListParams {
  page?: number
  list_rows?: number
  tag?: string
  status?: number
}

export interface SearchParams {
  page?: number
  list_rows?: number
  keyword: string
}

export interface CreateCardParams {
  content: string
  data?: Record<string, any>
  tags?: string[]
  cover?: string
}

export interface UpdateCardParams {
  content?: string
  data?: Record<string, any>
  tags?: string[]
  cover?: string
}
