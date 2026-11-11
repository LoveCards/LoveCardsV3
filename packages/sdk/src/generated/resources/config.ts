import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Config extends BaseResource {
  delete(params: { group: string }): Promise<void> {
    return this._delete<void>("/admin/config", params)
  }
  list(params?: { group?: string }): Promise<ApiTypes.ConfigData> {
    return this._get<ApiTypes.ConfigData>("/admin/config", params)
  }
  update(data?: Record<string, unknown>): Promise<ApiTypes.ConfigData> {
    return this._post<ApiTypes.ConfigData>("/admin/config", data)
  }
  groups(): Promise<ApiTypes.ConfigData> {
    return this._get<ApiTypes.ConfigData>("/admin/config/groups")
  }
  init(): Promise<ApiTypes.ConfigData> {
    return this._post<ApiTypes.ConfigData>("/admin/config/init")
  }
  deleteKey(params: { group: string; key: string }): Promise<void> {
    return this._delete<void>("/admin/config/key", params)
  }
  redisTest(): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/admin/config/redis/test")
  }
  register(data: { group: string; schema: Record<string, unknown> }): Promise<ApiTypes.ConfigData> {
    return this._post<ApiTypes.ConfigData>("/admin/config/register", data)
  }
  reload(data?: { group?: string }): Promise<void> {
    return this._post<void>("/admin/config/reload", data)
  }
}
