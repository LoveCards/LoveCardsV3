import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Drafts extends BaseResource {
  list(params?: { cursor?: string; limit?: number }): Promise<Record<string, unknown>[]> {
    return this._get<Record<string, unknown>[]>("/me/drafts", params)
  }
  create(data?: { title?: string; content?: string; payload?: Record<string, unknown> }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/me/drafts", data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/me/drafts/${id}`)
  }
  get(id: number): Promise<Record<string, unknown>> {
    return this._get<Record<string, unknown>>(`/me/drafts/${id}`)
  }
  update(id: number, data?: { title?: string; content?: string; payload?: Record<string, unknown> }): Promise<Record<string, unknown>> {
    return this._patch<Record<string, unknown>>(`/me/drafts/${id}`, data)
  }
}
