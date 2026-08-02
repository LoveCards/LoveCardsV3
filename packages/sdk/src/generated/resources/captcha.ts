import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Captcha extends BaseResource {
  types(): Promise<ApiTypes.CaptchaDriver[]> {
    return this._get<ApiTypes.CaptchaDriver[]>("/captcha/types")
  }
  drivers(): Promise<ApiTypes.CaptchaDriver[]> {
    return this._get<ApiTypes.CaptchaDriver[]>("/captcha/drivers")
  }
  meta(slug: string): Promise<ApiTypes.CaptchaMeta> {
    return this._get<ApiTypes.CaptchaMeta>(`/captcha/${slug}/meta`)
  }
  install(): Promise<ApiTypes.ConfigRegisterResult[]> {
    return this._post<ApiTypes.ConfigRegisterResult[]>("/captcha/install")
  }
  config(): Promise<ApiTypes.CaptchaConfig> {
    return this._get<ApiTypes.CaptchaConfig>("/captcha/config")
  }
}
