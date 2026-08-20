import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Library extends BaseResource {
  history_clear(): Promise<void> {
    return this._delete<void>("/me/history")
  }
  history(params?: { cursor?: string; limit?: number }): Promise<ApiTypes.CursorPage> {
    return this._get<ApiTypes.CursorPage>("/me/history", params)
  }
  history_record(data: { resource_type: string; resource_id: string }): Promise<void> {
    return this._post<void>("/me/history", data)
  }
  remove(params: { resource_type: string; resource_id: string }): Promise<void> {
    return this._delete<void>("/me/library", params)
  }
  list(params?: { cursor?: string; limit?: number }): Promise<ApiTypes.CursorPage> {
    return this._get<ApiTypes.CursorPage>("/me/library", params)
  }
  save(data: { resource_type: string; resource_id: string }): Promise<void> {
    return this._post<void>("/me/library", data)
  }
}
