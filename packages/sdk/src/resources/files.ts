import { BaseResource } from './base'
import type { ListResult, BatchOperateParams } from '../types/api'
import type { LCFile, UploadResult, DirectUploadResult } from '../types/files'

export class Files extends BaseResource {
  upload(formData: FormData): Promise<UploadResult> {
    return this._post<UploadResult>('/files', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  }

  list(params?: { page?: number; list_rows?: number }): Promise<ListResult<LCFile>> {
    return this._get<ListResult<LCFile>>('/files', params)
  }

  get(id: number): Promise<LCFile> {
    return this._get<LCFile>(`/files/${id}`)
  }

  direct(data?: { filename?: string; size?: number; mime?: string }): Promise<DirectUploadResult> {
    return this._post<DirectUploadResult>('/files/direct', data)
  }

  confirm(id: number): Promise<void> {
    return this._patch<void>(`/files/${id}/confirm`)
  }

  listMe(params?: { page?: number; list_rows?: number }): Promise<ListResult<LCFile>> {
    return this._get<ListResult<LCFile>>('/files/me', params)
  }

  batch(data: BatchOperateParams): Promise<void> {
    return this._post<void>('/files/batch', data)
  }

  cleanup(): Promise<void> {
    return this._post<void>('/files/cleanup')
  }

  delete(id: number): Promise<void> {
    return this._delete<void>(`/files/${id}`)
  }
}
