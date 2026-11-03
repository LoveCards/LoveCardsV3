import type { FieldDefinition, JsonObject, JsonValue } from './json'

export interface SenderType {
  type: string
  channelType: string
  name: string
  icon: string
  supportedTypes: string[]
}

export interface SenderMeta {
  [key: string]: JsonValue | undefined
  type: string
  name: string
  icon: string
  schema: JsonObject
  group: string
}

export interface SenderChannel {
  slug: string
  name: string
  icon: string
  fields: FieldDefinition[]
  channelType?: string
  supportedTypes?: string[]
}

export interface SenderTemplate {
  [key: string]: JsonValue | undefined
}
