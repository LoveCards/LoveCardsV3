import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Config extends BaseResource {
  list(): Promise<ApiTypes.ConfigData> {
    return this._get<ApiTypes.ConfigData>("/config")
  }
  update(data: ApiTypes.ConfigUpdateParams): Promise<void> {
    return this._post<void>("/config", data)
  }
  groups(): Promise<string[]> {
    return this._get<string[]>("/config/groups")
  }
  init(): Promise<ApiTypes.ConfigData> {
    return this._post<ApiTypes.ConfigData>("/config/init")
  }
  register(data: ApiTypes.ConfigRegisterParams): Promise<ApiTypes.ConfigRegisterResult> {
    return this._post<ApiTypes.ConfigRegisterResult>("/config/register", data)
  }
  reload(): Promise<void> {
    return this._post<void>("/config/reload")
  }
  deleteGroup(group: string): Promise<void> {
    return this._delete<void>("/config", group)
  }
  deleteKey(group: string, key: string): Promise<void> {
    return this._delete<void>("/config/key", key)
  }
}
