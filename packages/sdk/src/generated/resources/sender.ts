import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Sender extends BaseResource {
  types(): Promise<ApiTypes.SenderType[]> {
    return this._get<ApiTypes.SenderType[]>("/sender/types")
  }
  meta(type: string): Promise<ApiTypes.SenderMeta> {
    return this._get<ApiTypes.SenderMeta>(`/sender/${type}/meta`)
  }
  install(): Promise<ApiTypes.ConfigRegisterResult[]> {
    return this._post<ApiTypes.ConfigRegisterResult[]>("/sender/install")
  }
  channels(): Promise<ApiTypes.SenderChannel[]> {
    return this._get<ApiTypes.SenderChannel[]>("/sender/channels")
  }
  templates(): Promise<ApiTypes.SenderTemplate[]> {
    return this._get<ApiTypes.SenderTemplate[]>("/sender/templates")
  }
  testChannel(data: { channel: string; to?: string }): Promise<{ success: boolean; message?: string }> {
    return this._post<{ success: boolean; message?: string }>("/sender/test-channel", data)
  }
}
