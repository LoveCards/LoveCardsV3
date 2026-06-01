export interface Card {
  id: number
  user_id: number
  status: number
  is_top: number
  content: string
  /** 卡片自定义数据（如 title 等扩展字段） */
  data: Record<string, any>
  cover: string | null
  pictures: string[] | null
  tags: string[] | null
  goods: number
  views: number
  comments: number
  post_ip: string
  created_at: string | null
  updated_at: string | null
  deleted_at: string | null
}

export interface CardsListParams {
  page?: number
  list_rows?: number
  tag?: string
  status?: number
  search_value?: string
  search_keys?: string[]
  order_key?: string
  order_desc?: 0 | 1
}

export interface CreateCardParams {
  content: string
  data?: Record<string, any>
  tags?: string
  cover?: string
  pictures?: string[]
}

export interface UpdateCardParams {
  content?: string
  data?: Record<string, any>
  tags?: string
  cover?: string
}
