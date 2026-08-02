import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Session extends BaseResource {
  login(data: ApiTypes.LoginParams): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/login", data)
  }
  register(data: ApiTypes.RegisterParams): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/register", data)
  }
  guest(): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/guest")
  }
  logout(): Promise<void> {
    return this._post<void>("/session/logout")
  }
  captcha(params: ApiTypes.CaptchaSendParams): Promise<void> {
    return this._post<void>("/session/captcha", params)
  }
  check(): Promise<ApiTypes.CheckResult> {
    return this._get<ApiTypes.CheckResult>("/session/check")
  }
}
