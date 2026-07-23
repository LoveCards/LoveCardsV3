import { BaseResource } from './base'
import type { CaptchaDriver, CaptchaMeta, CaptchaConfig } from '../types/captcha'

export class Captcha extends BaseResource {
  types(): Promise<CaptchaDriver[]> {
    return this._get<CaptchaDriver[]>('/captcha/types')
  }

  drivers(): Promise<CaptchaDriver[]> {
    return this._get<CaptchaDriver[]>('/captcha/drivers')
  }

  meta(slug: string): Promise<CaptchaMeta> {
    return this._get<CaptchaMeta>(`/captcha/${slug}/meta`)
  }

  install(): Promise<void> {
    return this._post<void>('/captcha/install')
  }

  config(): Promise<CaptchaConfig> {
    return this._get<CaptchaConfig>('/captcha/config')
  }
}
