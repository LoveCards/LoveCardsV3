import { BaseResource } from './base'
import type { ThemeItem, ThemeConfigData, ThemeActivateParams, ThemeDeleteParams } from '../types/theme'
import type { Tag } from '../types/tags'

export class Theme extends BaseResource {
  tags(): Promise<Tag[]> {
    return this._get<Tag[]>('/theme/tags')
  }

  list(): Promise<ThemeItem[]> {
    return this._get<ThemeItem[]>('/theme/list')
  }

  upload(formData: FormData): Promise<void> {
    return this._post<void>('/theme/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  }

  activate(data: ThemeActivateParams): Promise<void> {
    return this._post<void>('/theme/activate', data)
  }

  config(): Promise<ThemeConfigData> {
    return this._get<ThemeConfigData>('/theme/config')
  }

  updateConfig(data: Record<string, any>): Promise<void> {
    return this._patch<void>('/theme/config', data)
  }

  freeze(): Promise<void> {
    return this._post<void>('/theme/freeze')
  }

  delete(data: ThemeDeleteParams): Promise<void> {
    return this._delete<void>('/theme/delete', data)
  }

  publicConfig(): Promise<ThemeConfigData> {
    return this._get<ThemeConfigData>('/theme/config')
  }
}
