import type { JsonObject } from './json'

export interface ConfigData {
    [group: string]: JsonObject
}

export interface ConfigUpdateParams {
    [group: string]: JsonObject
}

export interface ConfigRegisterParams {
  group: string
  schema: JsonObject
}

export interface ConfigRegisterResult {
  group: string
  seeded: string[]
  skipped: string[]
}
