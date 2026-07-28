import { BaseResource } from './base'
import type { ListResult, FilesBatchOperateParams } from '../types/api'
import type { LCFile, UploadResult, DirectUploadResult } from '../types/files'

export type { FilesBatchOperateParams }

export class Files extends BaseResource {
  upload(formData: FormData): Promise<UploadResult> {
    return this._post<UploadResult>('/files', formData)
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

  /**
   * 获取当前用户的文件列表（严格本人）
   * @param params 分页参数
   */
  listOwn(params?: { page?: number; list_rows?: number }): Promise<ListResult<LCFile>> {
    return this._get<ListResult<LCFile>>('/users/me/files', params)
  }

  /**
   * @deprecated 请使用 listOwn() 替代
   */
  listMe(params?: { page?: number; list_rows?: number }): Promise<ListResult<LCFile>> {
    return this.listOwn(params)
  }

  batch(data: FilesBatchOperateParams): Promise<void> {
    return this._post<void>('/files/batch', data)
  }

  cleanup(): Promise<void> {
    return this._delete<void>('/files/expired')
  }

  delete(id: number): Promise<void> {
    return this._delete<void>(`/files/${id}`)
  }
}
