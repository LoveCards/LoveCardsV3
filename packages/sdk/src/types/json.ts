export type JsonPrimitive = string | number | boolean | null

export type JsonValue = JsonPrimitive | JsonObject | JsonValue[]

export interface JsonObject {
  [key: string]: JsonValue | undefined
}

export interface FieldOption {
  value: string | number
  label: string
}

export interface FieldDefinition {
  key?: string
  label?: string
  type: string
  title?: string
  default?: JsonValue
  options?: FieldOption[]
}
