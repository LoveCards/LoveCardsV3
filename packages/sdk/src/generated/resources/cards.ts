import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Cards extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/cards", params)
  }
  create(data: { pictures?: unknown[]; content: string; cover?: string; tags?: unknown[] }): Promise<ApiTypes.CreateResult> {
    return this._post<ApiTypes.CreateResult>("/cards", data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/cards/${id}`)
  }
  get(id: number): Promise<ApiTypes.Card> {
    return this._get<ApiTypes.Card>(`/cards/${id}`)
  }
  update(id: number, data: { pictures?: unknown[]; content?: string; is_top?: number; status?: number; user_id?: number; comments?: number; views?: number; good?: number; cover?: string; tags?: unknown[] }): Promise<void> {
    return this._patch<void>(`/cards/${id}`, data)
  }
  like(id: number, data?: Record<string, unknown>): Promise<{ likes: number }> {
    return this._post<{ likes: number }>(`/cards/${id}/like`, data)
  }
  batch(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/cards/batch", data)
  }
  hot(): Promise<ApiTypes.Card[]> {
    return this._get<ApiTypes.Card[]>("/cards/hot")
  }
  search(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/cards/search", params)
  }
  listOwn(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/users/me/cards", params)
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this.listOwn(params)
  }
}
