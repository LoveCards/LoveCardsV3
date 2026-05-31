export interface CaptchaDriver {
  slug: string
  name: string
  description: string
  enabled: boolean
}

export interface CaptchaMeta {
  slug: string
  config_schema: Record<string, any>
}

export interface CaptchaConfig {
  driver: string
  config: Record<string, any>
}
