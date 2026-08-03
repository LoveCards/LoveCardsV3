import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Users extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.User>> {
    return this._get<ApiTypes.ListResult<ApiTypes.User>>("/users", params)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/users/${id}`)
  }
  get(id: number): Promise<ApiTypes.User> {
    return this._get<ApiTypes.User>(`/users/${id}`)
  }
  update(id: number, data: { avatar?: string; roles_id?: unknown[]; email?: string; phone?: string; username?: string; status?: number }): Promise<void> {
    return this._patch<void>(`/users/${id}`, data)
  }
  batch(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/users/batch", data)
  }
  me(): Promise<ApiTypes.User> {
    return this._get<ApiTypes.User>("/users/me")
  }
  updateMe(data: { avatar?: string; username?: string; password?: string }): Promise<void> {
    return this._patch<void>("/users/me", data)
  }
  emailCaptcha(data: { email: string }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/users/me/email-captcha", data)
  }
  updateEmail(data: { email: string; captcha?: string }): Promise<void> {
    return this._post<void>("/users/me/email", data)
  }
  updatePassword(data: { password: string }): Promise<void> {
    return this._post<void>("/users/me/password", data)
  }
}
