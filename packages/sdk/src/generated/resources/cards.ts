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
  updateManaged(id: number, data: ApiTypes.UpdateCardParams): Promise<void> {
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
  create(data: ApiTypes.CreateCardParams): Promise<ApiTypes.CreateResult> {
    return this._post<ApiTypes.CreateResult>("/me/cards", data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/me/cards/${id}`)
  }
  getOwn(id: number): Promise<ApiTypes.Card> {
    return this._get<ApiTypes.Card>(`/me/cards/${id}`)
  }
  update(id: number, data: ApiTypes.UpdateCardParams): Promise<void> {
    return this._patch<void>(`/me/cards/${id}`, data)
  }
  listFollowing(params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/me/feed/following", params)
  }
  listPublicByUser(uid: string, params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>(`/users/${encodeURIComponent(String(uid))}/cards`, params)
  }
  publicByHandle(handle: string, params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>(`/users/by-handle/${encodeURIComponent(String(handle))}/cards`, params)
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this.listOwn(params)
  }
}
