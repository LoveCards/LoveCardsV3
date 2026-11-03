import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Theme extends BaseResource {
  activate(data: { id: string; release: string }): Promise<void> {
    return this._post<void>("/admin/theme/activate", data)
  }
  updateConfig(data: { id: string; release: string; values: Record<string, unknown> }): Promise<void> {
    return this._put<void>("/admin/theme/config", data)
  }
  delete(params: { id: string; release: string }): Promise<void> {
    return this._delete<void>("/admin/theme/delete", params)
  }
  list(): Promise<ApiTypes.ThemeItem[]> {
    return this._get<ApiTypes.ThemeItem[]>("/admin/theme/list")
  }
  upload(formData: FormData): Promise<ApiTypes.ThemeItem> {
    return this._post<ApiTypes.ThemeItem>("/admin/theme/upload", formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  }
  publicConfig(): Promise<ApiTypes.ThemeConfigData> {
    return this._get<ApiTypes.ThemeConfigData>("/theme/config")
  }
  config(): Promise<ApiTypes.ThemeConfigData> {
    return this._get<ApiTypes.ThemeConfigData>("/theme/config")
  }
  tags(): Promise<ApiTypes.Tag[]> {
    return this._get<ApiTypes.Tag[]>("/tags")
  }
}
