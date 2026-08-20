import type { FieldDefinition, JsonObject } from './json'

export type ThemeMode = 'spa'

export interface ThemeItem {
  id: string
  name: string
  version: string
  mode: ThemeMode
  release: string
  author: string
  description: string
  active: boolean
}

export interface ThemeConfigData {
  id: string
  name: string
  version: string
  mode: ThemeMode
  release: string
  config_schema: Record<string, FieldDefinition>
  config_values: JsonObject
}

export interface ThemeActivateParams {
  id: string
  release: string
}

export interface ThemeDeleteParams {
  id: string
  release: string
}
