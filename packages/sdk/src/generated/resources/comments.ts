import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Comments extends BaseResource {
  cardList(id: number, params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>(`/cards/${id}/comments`, params)
  }
  create(id: number, data: { parent_id?: number; content: string; data?: unknown[] }): Promise<ApiTypes.CreateResult> {
    return this._post<ApiTypes.CreateResult>(`/cards/${id}/comments`, data)
  }
  list(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>("/comments", params)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/comments/${id}`)
  }
  get(id: number): Promise<ApiTypes.Comment> {
    return this._get<ApiTypes.Comment>(`/comments/${id}`)
  }
  update(id: number, data: { aid?: number; pid?: number; is_top?: number; status?: number; user_id?: number; good?: number; content?: string; data?: unknown[] }): Promise<void> {
    return this._patch<void>(`/comments/${id}`, data)
  }
  batch(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/comments/batch", data)
  }
  listOwn(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>("/users/me/comments", params)
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this.listOwn(params)
  }
}
