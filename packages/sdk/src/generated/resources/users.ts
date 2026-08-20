import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Users extends BaseResource {
  recovery(params?: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }): Promise<ApiTypes.ListResult<ApiTypes.RecoveryChallenge>> {
    return this._get<ApiTypes.ListResult<ApiTypes.RecoveryChallenge>>("/admin/recovery", params)
  }
  listManaged(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.User>> {
    return this._get<ApiTypes.ListResult<ApiTypes.User>>("/admin/users", params)
  }
  deleteManaged(id: number): Promise<void> {
    return this._delete<void>(`/admin/users/${id}`)
  }
  getManaged(id: number): Promise<ApiTypes.User> {
    return this._get<ApiTypes.User>(`/admin/users/${id}`)
  }
  updateManaged(id: number, data?: { avatar?: string; email?: string; phone?: string; username?: string; status?: number }): Promise<void> {
    return this._patch<void>(`/admin/users/${id}`, data)
  }
  assignRoles(id: number, data: { roles_id: number[] }): Promise<void> {
    return this._patch<void>(`/admin/users/${id}/roles`, data)
  }
  security(id: number): Promise<ApiTypes.UserSecurityOverview> {
    return this._get<ApiTypes.UserSecurityOverview>(`/admin/users/${id}/security`)
  }
  sessions(id: number): Promise<ApiTypes.UserSession[]> {
    return this._get<ApiTypes.UserSession[]>(`/admin/users/${id}/sessions`)
  }
  revokeSession(id: number, sessionId: string, data: { reason: string }): Promise<void> {
    return this._post<void>(`/admin/users/${id}/sessions/${sessionId}/revoke`, data)
  }
  revokeSessions(id: number, data: { reason: string }): Promise<void> {
    return this._post<void>(`/admin/users/${id}/sessions/revoke-all`, data)
  }
  batchManaged(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/admin/users/batch", data)
  }
  me(): Promise<ApiTypes.User> {
    return this._get<ApiTypes.User>("/me")
  }
  updateMe(data?: { avatar?: string; username?: string; password?: string }): Promise<void> {
    return this._patch<void>("/me", data)
  }
  emailCaptcha(data: { email: string }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/me/email-captcha", data)
  }
  updateEmail(data: { email: string; captcha?: string }): Promise<void> {
    return this._post<void>("/me/email", data)
  }
  updatePassword(data: { password: string }): Promise<void> {
    return this._post<void>("/me/password", data)
  }
}
