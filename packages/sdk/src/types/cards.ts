import type { JsonObject } from './json'
import type { ContentMention } from './mentions'

export interface CardMedia {
  file_id: number | null
  type: 'image' | 'video'
  url: string
  mime_type: string | null
  size: number | null
  width: number | null
  height: number | null
  duration_ms: number | null
  poster_url: string | null
  is_animated: boolean
  position: number
}

export interface CardTopic {
  id: number
  name: string
}

export type CardVisibility = 'public' | 'followers' | 'private'

export interface Card {
  id: number
  user_id: number
  uid: string
  username?: string
  status: number
  visibility: CardVisibility
  is_top: number
  content: string
  /** 原始卡片扩展数据；新写入不使用 title/summary 作为正文层级。 */
  data: JsonObject
  cover: string | null
  pictures: string[]
  media: CardMedia[]
  tags: number[]
  topics: CardTopic[]
  mentions: ContentMention[]
  goods: number
  views: number
  comments: number
  created_at: string
  updated_at: string
  /** Legacy moderation fields are not present on public card responses. */
  post_ip?: string
  deleted_at?: string | null
}

export interface CardsListParams {
  page?: number
  list_rows?: number
  tag?: string
  status?: number
  search_value?: string
  search_keys?: string[]
  search_like?: number
  order_key?: string
  order_desc?: boolean
}

export interface CreateCardParams {
  content: string
  visibility?: CardVisibility
  data?: JsonObject
  tags?: string[] | number[]
  cover?: string
  pictures?: string[]
  media_file_ids?: number[]
  cover_file_id?: number
}

export interface UpdateCardParams {
  content?: string
  visibility?: CardVisibility
  data?: JsonObject
  tags?: string[] | number[]
  cover?: string
  media_file_ids?: number[]
  cover_file_id?: number
}
