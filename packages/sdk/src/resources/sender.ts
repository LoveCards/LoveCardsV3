import { BaseResource } from './base'
import type { SenderType, SenderMeta, SenderChannel, SenderTemplate } from '../types/sender'

export class Sender extends BaseResource {
  types(): Promise<SenderType[]> {
    return this._get<SenderType[]>('/sender/types')
  }

  meta(type: string): Promise<SenderMeta> {
    return this._get<SenderMeta>(`/sender/${type}/meta`)
  }

  install(): Promise<void> {
    return this._post<void>('/sender/install')
  }

  channels(): Promise<SenderChannel[]> {
    return this._get<SenderChannel[]>('/sender/channels')
  }

  templates(): Promise<SenderTemplate[]> {
    return this._get<SenderTemplate[]>('/sender/templates')
  }

  testChannel(data: { channel: string; to?: string }): Promise<{ success: boolean; message?: string }> {
    return this._post<{ success: boolean; message?: string }>('/sender/test-channel', data)
  }
}
