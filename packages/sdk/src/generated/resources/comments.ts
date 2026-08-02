import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Comments extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>("/comments", params)
  }
  cardList(cardId: number, params?: ApiTypes.PaginationParams): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>(`/cards/${cardId}/comments`, params)
  }
  create(cardId: number, data: ApiTypes.CreateCommentParams): Promise<ApiTypes.CreateResult> {
    return this._post<ApiTypes.CreateResult>(`/cards/${cardId}/comments`, data)
  }
  get(id: number): Promise<ApiTypes.Comment> {
    return this._get<ApiTypes.Comment>(`/comments/${id}`)
  }
  update(id: number, data: { content: string }): Promise<void> {
    return this._patch<void>(`/comments/${id}`, data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/comments/${id}`)
  }
  listOwn(params?: ApiTypes.PaginationParams): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>("/users/me/comments", params)
  }
  batch(data: ApiTypes.BatchOperateParams): Promise<void> {
    return this._post<void>("/comments/batch", data)
  }
  listMe(params?: ApiTypes.PaginationParams): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this.listOwn(params)
  }
}
