import { BaseResource } from './base'
import type { PaginationParams } from '../types/api'
import type { Like } from '../types/likes'

export class Likes extends BaseResource {
  list(params?: PaginationParams): Promise<Like[]> {
    return this._get<Like[]>('/likes', params)
  }

  unlike(id: number): Promise<void> {
    return this._delete<void>(`/likes/${id}`)
  }
}
