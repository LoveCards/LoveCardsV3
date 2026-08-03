import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Session extends BaseResource {
  captcha(data: { account: string }): Promise<void> {
    return this._post<void>("/session/captcha", data)
  }
  check(): Promise<Record<string, unknown>> {
    return this._get<Record<string, unknown>>("/session/check")
  }
  guest(data?: Record<string, unknown>): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/guest", data)
  }
  login(data: { account: string; password: string }): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/login", data)
  }
  logout(data?: Record<string, unknown>): Promise<void> {
    return this._post<void>("/session/logout", data)
  }
  register(data: { account: string; password: string; code?: string }): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/register", data)
  }
}
