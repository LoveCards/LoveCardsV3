import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Users extends BaseResource {
  me(): Promise<ApiTypes.User> {
    return this._get<ApiTypes.User>("/users/me")
  }
  updateMe(data: ApiTypes.ProfileUpdateParams): Promise<void> {
    return this._patch<void>("/users/me", data)
  }
  updatePassword(data: ApiTypes.PasswordParams): Promise<void> {
    return this._post<void>("/users/me/password", data)
  }
  updateEmail(data: ApiTypes.EmailParams): Promise<void> {
    return this._post<void>("/users/me/email", data)
  }
  emailCaptcha(data: ApiTypes.EmailCaptchaParams): Promise<ApiTypes.EmailCaptchaResult> {
    return this._post<ApiTypes.EmailCaptchaResult>("/users/me/email-captcha", data)
  }
  list(params?: { page?: number; list_rows?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.User>> {
    return this._get<ApiTypes.ListResult<ApiTypes.User>>("/users", params)
  }
  get(id: number): Promise<ApiTypes.User> {
    return this._get<ApiTypes.User>(`/users/${id}`)
  }
  update(id: number, data: ApiTypes.AdminUserUpdateParams): Promise<void> {
    return this._patch<void>(`/users/${id}`, data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/users/${id}`)
  }
  batch(data: ApiTypes.BatchOperateParams): Promise<void> {
    return this._post<void>("/users/batch", data)
  }
}
