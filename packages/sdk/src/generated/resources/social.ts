import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Social extends BaseResource {
  unfollow(uid: string): Promise<void> {
    return this._delete<void>(`/users/${encodeURIComponent(String(uid))}/follow`)
  }
  follow(uid: string): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>(`/users/${encodeURIComponent(String(uid))}/follow`)
  }
  followers(uid: string, params?: { cursor?: string; limit?: number }): Promise<ApiTypes.CursorPage> {
    return this._get<ApiTypes.CursorPage>(`/users/${encodeURIComponent(String(uid))}/followers`, params)
  }
  following(uid: string, params?: { cursor?: string; limit?: number }): Promise<ApiTypes.CursorPage> {
    return this._get<ApiTypes.CursorPage>(`/users/${encodeURIComponent(String(uid))}/following`, params)
  }
  profile(uid: string): Promise<ApiTypes.PublicUserProfile> {
    return this._get<ApiTypes.PublicUserProfile>(`/users/${encodeURIComponent(String(uid))}/profile`)
  }
  relation(uid: string): Promise<ApiTypes.SocialRelation> {
    return this._get<ApiTypes.SocialRelation>(`/users/${encodeURIComponent(String(uid))}/relation`)
  }
  profileByHandle(handle: string): Promise<ApiTypes.PublicUserProfile> {
    return this._get<ApiTypes.PublicUserProfile>(`/users/by-handle/${encodeURIComponent(String(handle))}`)
  }
}
