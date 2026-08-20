import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Session extends BaseResource {
  captcha(data: { account: string }): Promise<void> {
    return this._post<void>("/session/captcha", data)
  }
  check(): Promise<ApiTypes.SessionState> {
    return this._get<ApiTypes.SessionState>("/session/check")
  }
  guest(): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/guest")
  }
  login(data: { account: string; password: string }): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/login", data)
  }
  logout(): Promise<void> {
    return this._post<void>("/session/logout")
  }
  policy(): Promise<ApiTypes.AuthPolicy> {
    return this._get<ApiTypes.AuthPolicy>("/session/policy")
  }
  recovery_request(data: { account: string }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/session/recovery/request", data)
  }
  recovery_reset(data: { challenge_id: number; code: string; password: string }): Promise<void> {
    return this._post<void>("/session/recovery/reset", data)
  }
  register(data: { account: string; password: string; code?: string; captcha_id?: string; captcha_code?: string; lot_number?: string; captcha_output?: string; pass_token?: string; gen_time?: string }): Promise<ApiTypes.LoginResult> {
    return this._post<ApiTypes.LoginResult>("/session/register", data)
  }
}
