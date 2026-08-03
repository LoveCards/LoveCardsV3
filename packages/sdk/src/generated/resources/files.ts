import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Files extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this._get<ApiTypes.ListResult<ApiTypes.LCFile>>("/files", params)
  }
  upload(formData: FormData): Promise<ApiTypes.UploadResult> {
    return this._post<ApiTypes.UploadResult>("/files", formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  }
  allDelete(id: number): Promise<void> {
    return this._delete<void>(`/files/${id}`)
  }
  get(id: number): Promise<ApiTypes.LCFile> {
    return this._get<ApiTypes.LCFile>(`/files/${id}`)
  }
  confirm(id: number, data?: Record<string, unknown>): Promise<void> {
    return this._patch<void>(`/files/${id}/confirm`, data)
  }
  batch(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/files/batch", data)
  }
  direct(data?: Record<string, unknown>): Promise<ApiTypes.DirectUploadResult> {
    return this._post<ApiTypes.DirectUploadResult>("/files/direct", data)
  }
  cleanup(): Promise<{ cleaned: number }> {
    return this._delete<{ cleaned: number }>("/files/expired")
  }
  listOwn(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this._get<ApiTypes.ListResult<ApiTypes.LCFile>>("/users/me/files", params)
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this.listOwn(params)
  }
}
