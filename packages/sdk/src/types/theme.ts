export interface ThemeItem {
  name: string
  title: string
  description: string
  version: string
  author: string
  active: boolean
}

export interface ThemeConfigData {
  name: string
  mode: string
  config_schema: Record<string, any>
  config_values: Record<string, any>
}

export interface ThemeActivateParams {
  name: string
}

export interface ThemeDeleteParams {
  name: string
}
