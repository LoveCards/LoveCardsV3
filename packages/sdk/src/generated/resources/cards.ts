import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Cards extends BaseResource {
  listManaged(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/admin/cards", params)
  }
  deleteManaged(id: number): Promise<void> {
    return this._delete<void>(`/admin/cards/${id}`)
  }
  getManaged(id: number): Promise<ApiTypes.Card> {
    return this._get<ApiTypes.Card>(`/admin/cards/${id}`)
  }
  updateManaged(id: number, data?: { pictures?: unknown[]; content?: string; data?: unknown[]; media_file_ids?: unknown[]; picture_file_ids?: unknown[]; cover_file_id?: number; tags?: unknown[]; is_top?: number; status?: number; user_id?: number; comments?: number; views?: number; good?: number; cover?: string }): Promise<void> {
    return this._patch<void>(`/admin/cards/${id}`, data)
  }
  batchManaged(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/admin/cards/batch", data)
  }
  list(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/cards", params)
  }
  get(id: number): Promise<ApiTypes.Card> {
    return this._get<ApiTypes.Card>(`/cards/${id}`)
  }
  unlike(id: number): Promise<void> {
    return this._delete<void>(`/cards/${id}/like`)
  }
  like(id: number): Promise<{ likes: number }> {
    return this._post<{ likes: number }>(`/cards/${id}/like`)
  }
  hot(): Promise<ApiTypes.Card[]> {
    return this._get<ApiTypes.Card[]>("/cards/hot")
  }
  search(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/cards/search", params)
  }
  listOwn(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/me/cards", params)
  }
  create(data: { pictures?: unknown[]; data?: unknown[]; media_file_ids?: unknown[]; picture_file_ids?: unknown[]; cover_file_id?: number; tags?: unknown[]; content: string; cover?: string }): Promise<ApiTypes.CreateResult> {
    return this._post<ApiTypes.CreateResult>("/me/cards", data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/me/cards/${id}`)
  }
  getOwn(id: number): Promise<ApiTypes.Card> {
    return this._get<ApiTypes.Card>(`/me/cards/${id}`)
  }
  update(id: number, data?: { pictures?: unknown[]; content?: string; data?: unknown[]; media_file_ids?: unknown[]; picture_file_ids?: unknown[]; cover_file_id?: number; tags?: unknown[]; cover?: string }): Promise<void> {
    return this._patch<void>(`/me/cards/${id}`, data)
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this.listOwn(params)
  }
}
