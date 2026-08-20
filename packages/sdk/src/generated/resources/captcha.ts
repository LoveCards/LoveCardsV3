import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Captcha extends BaseResource {
  meta(slug: string): Promise<ApiTypes.CaptchaDriver> {
    return this._get<ApiTypes.CaptchaDriver>(`/admin/captcha/${slug}/meta`)
  }
  drivers(): Promise<ApiTypes.CaptchaDriver[]> {
    return this._get<ApiTypes.CaptchaDriver[]>("/admin/captcha/drivers")
  }
  install(): Promise<ApiTypes.CaptchaMeta[]> {
    return this._post<ApiTypes.CaptchaMeta[]>("/admin/captcha/install")
  }
  types(): Promise<ApiTypes.CaptchaDriver[]> {
    return this._get<ApiTypes.CaptchaDriver[]>("/admin/captcha/types")
  }
  challenge(): Promise<ApiTypes.CaptchaChallenge> {
    return this._get<ApiTypes.CaptchaChallenge>("/captcha/challenge")
  }
  config(): Promise<ApiTypes.CaptchaConfig> {
    return this._get<ApiTypes.CaptchaConfig>("/captcha/config")
  }
}
