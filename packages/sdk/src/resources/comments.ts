import { BaseResource } from './base'
import type { CreateResult, BatchOperateParams, PaginationParams, ListResult } from '../types/api'
import type { Comment, CreateCommentParams } from '../types/comments'

export class Comments extends BaseResource {
  cardList(cardId: number, params?: PaginationParams): Promise<ListResult<Comment>> {
    return this._get<ListResult<Comment>>(`/cards/${cardId}/comments`, params)
  }

  create(cardId: number, data: CreateCommentParams): Promise<CreateResult> {
    return this._post<CreateResult>(`/cards/${cardId}/comments`, data)
  }

  get(id: number): Promise<Comment> {
    return this._get<Comment>(`/comments/${id}`)
  }

  update(id: number, data: { content: string }): Promise<void> {
    return this._patch<void>(`/comments/${id}`, data)
  }

  delete(id: number): Promise<void> {
    return this._delete<void>(`/comments/${id}`)
  }

  listOwn(params?: PaginationParams): Promise<ListResult<Comment>> {
    return this._get<ListResult<Comment>>('/users/me/comments', params)
  }

  batch(data: BatchOperateParams): Promise<void> {
    return this._post<void>('/comments/batch', data)
  }
}
