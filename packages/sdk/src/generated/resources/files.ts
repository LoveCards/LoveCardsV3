import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Files extends BaseResource {
  listManaged(params?: { page?: number; list_rows?: number; search_keys?: unknown[]; search_like?: number; search_value?: string; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; status?: number; upload_status?: number; scene?: string; ref_type?: string; ref_id?: number }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this._get<ApiTypes.ListResult<ApiTypes.LCFile>>("/admin/files", params)
  }
  deleteManaged(id: number): Promise<void> {
    return this._delete<void>(`/admin/files/${id}`)
  }
  getManaged(id: number): Promise<ApiTypes.LCFile> {
    return this._get<ApiTypes.LCFile>(`/admin/files/${id}`)
  }
  purge(id: number): Promise<void> {
    return this._delete<void>(`/admin/files/${id}/purge`)
  }
  usage(id: number): Promise<ApiTypes.FileUsage> {
    return this._get<ApiTypes.FileUsage>(`/admin/files/${id}/usage`)
  }
  batchManaged(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/admin/files/batch", data)
  }
  cleanup(params?: { limit?: number }): Promise<{ cleaned: number }> {
    return this._delete<{ cleaned: number }>("/admin/files/expired", params)
  }
  listOwn(params?: { page?: number; list_rows?: number; search_keys?: unknown[]; search_like?: number; search_value?: string; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; status?: number; upload_status?: number; scene?: string; ref_type?: string; ref_id?: number }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this._get<ApiTypes.ListResult<ApiTypes.LCFile>>("/me/files", params)
  }
  upload(formData: FormData): Promise<ApiTypes.UploadResult> {
    return this._post<ApiTypes.UploadResult>("/me/files", formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/me/files/${id}`)
  }
  get(id: number): Promise<ApiTypes.LCFile> {
    return this._get<ApiTypes.LCFile>(`/me/files/${id}`)
  }
  confirm(id: number): Promise<void> {
    return this._patch<void>(`/me/files/${id}/confirm`)
  }
  direct(data: { filename: string; size: number; mime: string }): Promise<ApiTypes.DirectUploadResult> {
    return this._post<ApiTypes.DirectUploadResult>("/me/files/direct", data)
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: unknown[]; search_like?: number; search_value?: string; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; status?: number; upload_status?: number; scene?: string; ref_type?: string; ref_id?: number }): Promise<ApiTypes.ListResult<ApiTypes.LCFile>> {
    return this.listOwn(params)
  }
}
