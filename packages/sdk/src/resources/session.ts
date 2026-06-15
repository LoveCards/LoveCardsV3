import { BaseResource } from './base'
import type { LoginParams, RegisterParams, LoginResult, CheckResult, CaptchaSendParams } from '../types/users'

export class Session extends BaseResource {
  login(data: LoginParams): Promise<LoginResult> {
    return this._post<LoginResult>('/session/login', data)
  }

  register(data: RegisterParams): Promise<LoginResult> {
    return this._post<LoginResult>('/session/register', data)
  }

  guest(): Promise<LoginResult> {
    return this._post<LoginResult>('/session/guest')
  }

  logout(): Promise<void> {
    return this._post<void>('/session/logout')
  }

  captcha(params: CaptchaSendParams): Promise<void> {
    return this._post<void>('/session/captcha', params)
  }

  check(): Promise<CheckResult> {
    return this._get<CheckResult>('/session/check')
  }
}
