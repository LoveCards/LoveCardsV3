import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Comments extends BaseResource {
  unpinManaged(id: number): Promise<void> {
    return this._delete<void>(`/admin/cards/${id}/pinned-comment`)
  }
  pinManaged(id: number, data: { comment_id: number }): Promise<void> {
    return this._put<void>(`/admin/cards/${id}/pinned-comment`, data)
  }
  listManaged(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>("/admin/comments", params)
  }
  deleteManaged(id: number): Promise<void> {
    return this._delete<void>(`/admin/comments/${id}`)
  }
  getManaged(id: number): Promise<ApiTypes.Comment> {
    return this._get<ApiTypes.Comment>(`/admin/comments/${id}`)
  }
  moderateManaged(id: number, data?: { content?: string; status?: number; data?: unknown[] }): Promise<void> {
    return this._patch<void>(`/admin/comments/${id}`, data)
  }
  batchManaged(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/admin/comments/batch", data)
  }
  cardList(id: number, params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>(`/cards/${id}/comments`, params)
  }
  create(id: number, data: { parent_id?: number; content: string; data?: unknown[] }): Promise<ApiTypes.CreateResult> {
    return this._post<ApiTypes.CreateResult>(`/cards/${id}/comments`, data)
  }
  get(id: number): Promise<ApiTypes.Comment> {
    return this._get<ApiTypes.Comment>(`/comments/${id}`)
  }
  unlike(id: number): Promise<void> {
    return this._delete<void>(`/comments/${id}/like`)
  }
  like(id: number): Promise<{ likes: number }> {
    return this._post<{ likes: number }>(`/comments/${id}/like`)
  }
  unpin(id: number): Promise<void> {
    return this._delete<void>(`/me/cards/${id}/pinned-comment`)
  }
  pin(id: number, data: { comment_id: number }): Promise<void> {
    return this._put<void>(`/me/cards/${id}/pinned-comment`, data)
  }
  listOwn(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>("/me/comments", params)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/me/comments/${id}`)
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this.listOwn(params)
  }
}
