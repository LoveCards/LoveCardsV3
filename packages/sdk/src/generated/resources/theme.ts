import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Theme extends BaseResource {
  tags(): Promise<ApiTypes.Tag[]> {
    return this._get<ApiTypes.Tag[]>("/tags")
  }
  list(): Promise<ApiTypes.ThemeItem[]> {
    return this._get<ApiTypes.ThemeItem[]>("/theme/list")
  }
  upload(formData: FormData): Promise<ApiTypes.ThemeItem> {
    return this._post<ApiTypes.ThemeItem>("/theme/upload", formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  }
  activate(data: ApiTypes.ThemeActivateParams): Promise<void> {
    return this._post<void>("/theme/activate", data)
  }
  config(): Promise<ApiTypes.ThemeConfigData> {
    return this._get<ApiTypes.ThemeConfigData>("/theme/config")
  }
  updateConfig(data: Record<string, any>): Promise<void> {
    return this._put<void>("/theme/config", data)
  }
  freeze(): Promise<void> {
    return this._post<void>("/theme/freeze")
  }
  delete(data: ApiTypes.ThemeDeleteParams): Promise<void> {
    return this._delete<void>("/theme/delete", data)
  }
  publicConfig(): Promise<ApiTypes.ThemeConfigData> {
    return this._get<ApiTypes.ThemeConfigData>("/theme/config")
  }
}
