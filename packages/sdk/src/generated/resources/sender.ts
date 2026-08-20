import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Sender extends BaseResource {
  meta(type: string): Promise<ApiTypes.SenderMeta> {
    return this._get<ApiTypes.SenderMeta>(`/admin/sender/${type}/meta`)
  }
  channels(): Promise<ApiTypes.SenderChannel[]> {
    return this._get<ApiTypes.SenderChannel[]>("/admin/sender/channels")
  }
  install(): Promise<ApiTypes.SenderMeta[]> {
    return this._post<ApiTypes.SenderMeta[]>("/admin/sender/install")
  }
  templates(): Promise<ApiTypes.SenderTemplate[]> {
    return this._get<ApiTypes.SenderTemplate[]>("/admin/sender/templates")
  }
  testChannel(data: { channel: string; to?: string }): Promise<{ success: boolean; message?: string }> {
    return this._post<{ success: boolean; message?: string }>("/admin/sender/test-channel", data)
  }
  types(): Promise<ApiTypes.SenderType[]> {
    return this._get<ApiTypes.SenderType[]>("/admin/sender/types")
  }
}
