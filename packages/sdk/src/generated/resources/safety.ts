import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Safety extends BaseResource {
  preferences(): Promise<Record<string, unknown>> {
    return this._get<Record<string, unknown>>("/me/privacy")
  }
  preferences_update(data?: { profile_visibility?: string; messages_from?: string; show_relations?: boolean }): Promise<void> {
    return this._post<void>("/me/privacy", data)
  }
  report(data: { resource_type: string; resource_id: string; reason: string; details?: string }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/reports", data)
  }
  unblock(id: number): Promise<void> {
    return this._delete<void>(`/users/${id}/block`)
  }
  block(id: number): Promise<void> {
    return this._post<void>(`/users/${id}/block`)
  }
}
