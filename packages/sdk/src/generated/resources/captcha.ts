import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Captcha extends BaseResource {
  meta(slug: string): Promise<ApiTypes.CaptchaDriver> {
    return this._get<ApiTypes.CaptchaDriver>(`/captcha/${slug}/meta`)
  }
  config(): Promise<ApiTypes.CaptchaConfig> {
    return this._get<ApiTypes.CaptchaConfig>("/captcha/config")
  }
  drivers(): Promise<ApiTypes.CaptchaDriver[]> {
    return this._get<ApiTypes.CaptchaDriver[]>("/captcha/drivers")
  }
  install(data?: Record<string, unknown>): Promise<ApiTypes.CaptchaMeta[]> {
    return this._post<ApiTypes.CaptchaMeta[]>("/captcha/install", data)
  }
  types(): Promise<ApiTypes.CaptchaDriver[]> {
    return this._get<ApiTypes.CaptchaDriver[]>("/captcha/types")
  }
}
