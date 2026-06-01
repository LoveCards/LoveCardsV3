import { BaseResource } from './base'
import type { ConfigData, ConfigUpdateParams, ConfigRegisterParams } from '../types/config'

export class Config extends BaseResource {
  list(): Promise<ConfigData> {
    return this._get<ConfigData>('/config')
  }

  update(data: ConfigUpdateParams): Promise<void> {
    return this._post<void>('/config', data)
  }

  groups(): Promise<string[]> {
    return this._get<string[]>('/config/groups')
  }

  init(): Promise<void> {
    return this._post<void>('/config/init')
  }

  register(data: ConfigRegisterParams): Promise<void> {
    return this._post<void>('/config/register', data)
  }

  reload(): Promise<void> {
    return this._post<void>('/config/reload')
  }

  deleteGroup(group: string): Promise<void> {
    return this._delete<void>('/config', { group })
  }

  deleteKey(group: string, key: string): Promise<void> {
    return this._delete<void>('/config/key', { group, key })
  }
}
