import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Files extends BaseResource {
  upload(formData: FormData): Promise<ApiTypes.UploadResult> {
    return this._post<ApiTypes.UploadResult>("/files", formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  }
  list(params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this._get<ApiTypes.ListResult<ApiTypes.LCFile>>("/files", params)
  }
  get(id: number): Promise<ApiTypes.LCFile> {
    return this._get<ApiTypes.LCFile>(`/files/${id}`)
  }
  direct(data?: { filename?: string; size?: number; mime?: string }): Promise<ApiTypes.DirectUploadResult> {
    return this._post<ApiTypes.DirectUploadResult>("/files/direct", data)
  }
  confirm(id: number): Promise<void> {
    return this._patch<void>(`/files/${id}/confirm`)
  }
  listOwn(params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this._get<ApiTypes.ListResult<ApiTypes.LCFile>>("/users/me/files", params)
  }
  batch(data: ApiTypes.FilesBatchOperateParams): Promise<void> {
    return this._post<void>("/files/batch", data)
  }
  cleanup(): Promise<{ cleaned: number }> {
    return this._delete<{ cleaned: number }>("/files/expired")
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/files/${id}`)
  }
  listMe(params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this.listOwn(params)
  }
}
