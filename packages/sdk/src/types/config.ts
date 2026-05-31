export interface ConfigItem {
  group: string
  key: string
  value: any
  label: string
  schema: Record<string, any>
}

export interface ConfigGroup {
  group: string
  label: string
  items: ConfigItem[]
}
