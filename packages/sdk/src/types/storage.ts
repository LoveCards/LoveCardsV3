import type { FieldDefinition, JsonObject, JsonValue } from './json'

export interface StorageDriver {
  type: string
  name: string
  icon: string
}

export interface StorageMeta {
  [key: string]: JsonValue | undefined
  type: string
  name: string
  icon: string
  schema: JsonObject
  group: string
}

export interface StorageChannel {
  slug: string
  name: string
  icon: string
  fields: FieldDefinition[]
  configured?: boolean
  available?: boolean
}

export interface ChannelStats {
  [key: string]: { file_count: number; total_size: number }
}
