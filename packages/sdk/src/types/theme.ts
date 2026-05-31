export interface ThemeItem {
  name: string
  title: string
  description: string
  version: string
  author: string
  active: boolean
}

export interface ThemeConfigData {
  theme: string
  config: Record<string, any>
}
