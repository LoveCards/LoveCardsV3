import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Theme extends BaseResource {
  activate(data?: Record<string, unknown>): Promise<void> {
    return this._post<void>("/theme/activate", data)
  }
  publicConfig(): Promise<ApiTypes.ThemeConfigData> {
    return this._get<ApiTypes.ThemeConfigData>("/theme/config")
  }
  updateConfig(data?: Record<string, unknown>): Promise<void> {
    return this._put<void>("/theme/config", data)
  }
  delete(): Promise<void> {
    return this._delete<void>("/theme/delete")
  }
  freeze(data?: Record<string, unknown>): Promise<void> {
    return this._post<void>("/theme/freeze", data)
  }
  list(): Promise<ApiTypes.ThemeItem[]> {
    return this._get<ApiTypes.ThemeItem[]>("/theme/list")
  }
  upload(formData: FormData): Promise<ApiTypes.ThemeItem> {
    return this._post<ApiTypes.ThemeItem>("/theme/upload", formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  }
  config(): Promise<ApiTypes.ThemeConfigData> {
    return this._get<ApiTypes.ThemeConfigData>("/theme/config")
  }
  tags(): Promise<ApiTypes.Tag[]> {
    return this._get<ApiTypes.Tag[]>("/tags")
  }
}
