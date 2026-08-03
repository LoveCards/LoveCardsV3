import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Config extends BaseResource {
  delete(): Promise<void> {
    return this._delete<void>("/config")
  }
  list(): Promise<ApiTypes.ConfigData> {
    return this._get<ApiTypes.ConfigData>("/config")
  }
  update(data?: Record<string, unknown>): Promise<void> {
    return this._post<void>("/config", data)
  }
  groups(): Promise<ApiTypes.ConfigData> {
    return this._get<ApiTypes.ConfigData>("/config/groups")
  }
  init(data?: Record<string, unknown>): Promise<ApiTypes.ConfigData> {
    return this._post<ApiTypes.ConfigData>("/config/init", data)
  }
  deleteKey(): Promise<void> {
    return this._delete<void>("/config/key")
  }
  register(data?: Record<string, unknown>): Promise<ApiTypes.ConfigData> {
    return this._post<ApiTypes.ConfigData>("/config/register", data)
  }
  reload(data?: Record<string, unknown>): Promise<void> {
    return this._post<void>("/config/reload", data)
  }
}
