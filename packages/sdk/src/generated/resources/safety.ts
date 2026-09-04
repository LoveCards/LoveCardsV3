import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Safety extends BaseResource {
  listBlocked(params?: { cursor?: string; limit?: number; query?: string }): Promise<ApiTypes.BlockedUserCursorPage> {
    return this._get<ApiTypes.BlockedUserCursorPage>("/me/blocked-users", params)
  }
  preferences(): Promise<ApiTypes.PrivacyPreferences> {
    return this._get<ApiTypes.PrivacyPreferences>("/me/privacy")
  }
  preferences_update(data?: Partial<ApiTypes.PrivacyPreferences>): Promise<ApiTypes.PrivacyPreferences> {
    return this._post<ApiTypes.PrivacyPreferences>("/me/privacy", data)
  }
  report(data: { resource_type: string; resource_id: string; reason: string; details?: string }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/reports", data)
  }
  unblock(uid: string): Promise<void> {
    return this._delete<void>(`/users/${encodeURIComponent(String(uid))}/block`)
  }
  block(uid: string): Promise<void> {
    return this._post<void>(`/users/${encodeURIComponent(String(uid))}/block`)
  }
}
