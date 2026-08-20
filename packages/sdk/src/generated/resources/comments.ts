import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Comments extends BaseResource {
  listManaged(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>("/admin/comments", params)
  }
  deleteManaged(id: number): Promise<void> {
    return this._delete<void>(`/admin/comments/${id}`)
  }
  getManaged(id: number): Promise<ApiTypes.Comment> {
    return this._get<ApiTypes.Comment>(`/admin/comments/${id}`)
  }
  updateManaged(id: number, data?: { aid?: number; pid?: number; is_top?: number; status?: number; user_id?: number; good?: number; content?: string; data?: unknown[] }): Promise<void> {
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
  listOwn(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Comment>>("/me/comments", params)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/me/comments/${id}`)
  }
  update(id: number, data?: { content?: string; data?: unknown[] }): Promise<void> {
    return this._patch<void>(`/me/comments/${id}`, data)
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Comment>> {
    return this.listOwn(params)
  }
}
