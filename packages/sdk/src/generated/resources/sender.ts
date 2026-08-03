import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Sender extends BaseResource {
  meta(type: string): Promise<ApiTypes.SenderMeta> {
    return this._get<ApiTypes.SenderMeta>(`/sender/${type}/meta`)
  }
  channels(): Promise<ApiTypes.SenderChannel[]> {
    return this._get<ApiTypes.SenderChannel[]>("/sender/channels")
  }
  install(data?: Record<string, unknown>): Promise<ApiTypes.SenderMeta[]> {
    return this._post<ApiTypes.SenderMeta[]>("/sender/install", data)
  }
  templates(): Promise<ApiTypes.SenderTemplate[]> {
    return this._get<ApiTypes.SenderTemplate[]>("/sender/templates")
  }
  testChannel(data?: Record<string, unknown>): Promise<{ success: boolean; message?: string }> {
    return this._post<{ success: boolean; message?: string }>("/sender/test-channel", data)
  }
  types(): Promise<ApiTypes.SenderType[]> {
    return this._get<ApiTypes.SenderType[]>("/sender/types")
  }
}
