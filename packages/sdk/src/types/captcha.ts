export interface CaptchaDriver {
  slug: string
  type: string
  name: string
  icon: string
}

export interface CaptchaMeta {
  slug: string
  config_schema: Record<string, any>
}

export interface CaptchaConfig {
  [key: string]: any
}

export interface CaptchaInstallResult {
  group: string
  seeded: string[]
  skipped: string[]
}
