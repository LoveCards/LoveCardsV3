import type { FieldDefinition, JsonObject, JsonValue } from './json'

export interface CaptchaDriver {
  slug: string
  type: string
  name: string
  icon: string
  fields?: FieldDefinition[]
}

export interface CaptchaMeta {
  slug: string
  config_schema: JsonObject
}

export interface CaptchaConfig {
  [key: string]: JsonValue | undefined
}

export interface CaptchaChallenge {
  driver: string
  captcha_id?: string
  image_id?: string
  image_base64?: string
  status?: boolean
}

export interface CaptchaInstallResult {
  group: string
  seeded: string[]
  skipped: string[]
}
