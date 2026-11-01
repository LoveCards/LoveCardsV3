import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Users extends BaseResource {
  recovery(params?: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }): Promise<ApiTypes.ListResult<ApiTypes.RecoveryChallenge>> {
    return this._get<ApiTypes.ListResult<ApiTypes.RecoveryChallenge>>("/admin/recovery", params)
  }
  listManaged(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; status?: number; role_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.User>> {
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
  revokeSession(id: number, sessionId: string): Promise<void> {
    return this._post<void>(`/admin/users/${id}/sessions/${encodeURIComponent(String(sessionId))}/revoke`)
  }
  revokeSessions(id: number): Promise<void> {
    return this._post<void>(`/admin/users/${id}/sessions/revoke-all`)
  }
  batchManaged(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/admin/users/batch", data)
  }
  me(): Promise<ApiTypes.CurrentUser> {
    return this._get<ApiTypes.CurrentUser>("/me")
  }
  updateMe(data?: ApiTypes.ProfileUpdateParams): Promise<ApiTypes.CurrentUser> {
    return this._patch<ApiTypes.CurrentUser>("/me", data)
  }
  emailCaptcha(data: ApiTypes.EmailCaptchaParams): Promise<ApiTypes.EmailCaptchaResult> {
    return this._post<ApiTypes.EmailCaptchaResult>("/me/email-captcha", data)
  }
  updateEmail(data: ApiTypes.EmailParams): Promise<void> {
    return this._post<void>("/me/email", data)
  }
  updatePassword(data: ApiTypes.PasswordParams): Promise<void> {
    return this._post<void>("/me/password", data)
  }
  searchMentionCandidates(params: { query: string; limit?: number }): Promise<ApiTypes.ListResult<ApiTypes.MentionCandidate>> {
    return this._get<ApiTypes.ListResult<ApiTypes.MentionCandidate>>("/users/mention-candidates", params)
  }
}
