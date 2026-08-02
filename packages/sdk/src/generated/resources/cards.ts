import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Cards extends BaseResource {
  list(params?: ApiTypes.CardsListParams): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/cards", params)
  }
  get(id: number): Promise<ApiTypes.Card> {
    return this._get<ApiTypes.Card>(`/cards/${id}`)
  }
  hot(): Promise<ApiTypes.Card[]> {
    return this._get<ApiTypes.Card[]>("/cards/hot")
  }
  search(params?: ApiTypes.CardsListParams): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/cards/search", params)
  }
  create(data: ApiTypes.CreateCardParams): Promise<ApiTypes.CreateResult> {
    return this._post<ApiTypes.CreateResult>("/cards", data)
  }
  update(id: number, data: ApiTypes.UpdateCardParams): Promise<void> {
    return this._patch<void>(`/cards/${id}`, data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/cards/${id}`)
  }
  like(id: number): Promise<{ likes: number }> {
    return this._post<{ likes: number }>(`/cards/${id}/like`)
  }
  listOwn(params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Card>>("/users/me/cards", params)
  }
  batch(data: ApiTypes.BatchOperateParams): Promise<void> {
    return this._post<void>("/cards/batch", data)
  }
  listMe(params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.Card>> {
    return this.listOwn(params)
  }
}
