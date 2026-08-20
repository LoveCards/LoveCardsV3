import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Social extends BaseResource {
  unfollow(id: number): Promise<void> {
    return this._delete<void>(`/users/${id}/follow`)
  }
  follow(id: number): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>(`/users/${id}/follow`)
  }
  followers(id: number, params?: { cursor?: string; limit?: number }): Promise<ApiTypes.CursorPage> {
    return this._get<ApiTypes.CursorPage>(`/users/${id}/followers`, params)
  }
  following(id: number, params?: { cursor?: string; limit?: number }): Promise<ApiTypes.CursorPage> {
    return this._get<ApiTypes.CursorPage>(`/users/${id}/following`, params)
  }
  relation(id: number): Promise<Record<string, unknown>> {
    return this._get<Record<string, unknown>>(`/users/${id}/relation`)
  }
  profile(handle: string): Promise<Record<string, unknown>> {
    return this._get<Record<string, unknown>>(`/users/by-handle/${handle}`)
  }
}
