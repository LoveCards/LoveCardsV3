export interface ConfigData {
  [group: string]: {
    [key: string]: any
  }
}

export interface ConfigUpdateParams {
  [group: string]: {
    [key: string]: any
  }
}

export interface ConfigRegisterParams {
  group: string
  schema: Record<string, any>
}
