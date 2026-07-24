import { BaseResource } from './base'
import type { ListResult, CreateResult, BatchOperateParams } from '../types/api'
import type { Card, CardsListParams, CreateCardParams, UpdateCardParams } from '../types/cards'

export class Cards extends BaseResource {
  list(params?: CardsListParams): Promise<ListResult<Card>> {
    return this._get<ListResult<Card>>('/cards', params)
  }

  get(id: number): Promise<Card> {
    return this._get<Card>(`/cards/${id}`)
  }

  hot(): Promise<Card[]> {
    return this._get<Card[]>('/cards/hot')
  }

  search(params?: CardsListParams): Promise<ListResult<Card>> {
    return this._get<ListResult<Card>>('/cards/search', params)
  }

  create(data: CreateCardParams): Promise<CreateResult> {
    return this._post<CreateResult>('/cards', data)
  }

  update(id: number, data: UpdateCardParams): Promise<void> {
    return this._patch<void>(`/cards/${id}`, data)
  }

  delete(id: number): Promise<void> {
    return this._delete<void>(`/cards/${id}`)
  }

  like(id: number): Promise<{ likes: number }> {
    return this._post<{ likes: number }>(`/cards/${id}/like`)
  }

  listOwn(params?: { page?: number; list_rows?: number }): Promise<ListResult<Card>> {
    return this._get<ListResult<Card>>('/users/me/cards', params)
  }

  listMe(params?: { page?: number; list_rows?: number }): Promise<ListResult<Card>> {
    return this.listOwn(params)
  }

  batch(data: BatchOperateParams): Promise<void> {
    return this._post<void>('/cards/batch', data)
  }
}
