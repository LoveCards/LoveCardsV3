import { BaseResource } from './base'
import type { ListResult, BatchOperateParams } from '../types/api'
import type { User, ProfileUpdateParams, AdminUserUpdateParams, PasswordParams, EmailParams, EmailCaptchaParams } from '../types/users'

export class Users extends BaseResource {
  me(): Promise<User> {
    return this._get<User>('/users/me')
  }

  updateMe(data: ProfileUpdateParams): Promise<void> {
    return this._patch<void>('/users/me', data)
  }

  updatePassword(data: PasswordParams): Promise<void> {
    return this._post<void>('/users/me/password', data)
  }

  updateEmail(data: EmailParams): Promise<void> {
    return this._post<void>('/users/me/email', data)
  }

  emailCaptcha(data: EmailCaptchaParams): Promise<void> {
    return this._post<void>('/users/me/email-captcha', data)
  }

  list(params?: { page?: number; list_rows?: number; search_value?: string }): Promise<ListResult<User>> {
    return this._get<ListResult<User>>('/users', params)
  }

  get(id: number): Promise<User> {
    return this._get<User>(`/users/${id}`)
  }

  update(id: number, data: AdminUserUpdateParams): Promise<void> {
    return this._patch<void>(`/users/${id}`, data)
  }

  delete(id: number): Promise<void> {
    return this._delete<void>(`/users/${id}`)
  }

  batch(data: BatchOperateParams): Promise<void> {
    return this._post<void>('/users/batch', data)
  }
}
