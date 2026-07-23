import { BaseResource } from './base'
import type { StorageDriver, StorageMeta, StorageChannel, ChannelStats } from '../types/storage'

export class Storage extends BaseResource {
  types(): Promise<StorageDriver[]> {
    return this._get<StorageDriver[]>('/storage/types')
  }

  meta(type: string): Promise<StorageMeta> {
    return this._get<StorageMeta>(`/storage/${type}/meta`)
  }

  install(): Promise<void> {
    return this._post<void>('/storage/install')
  }

  channels(): Promise<StorageChannel[]> {
    return this._get<StorageChannel[]>('/storage/channels')
  }

  testChannel(data: { channel: string }): Promise<{ success: boolean; message?: string }> {
    return this._post<{ success: boolean; message?: string }>('/storage/test-channel', data)
  }

  channelStats(): Promise<ChannelStats> {
    return this._get<ChannelStats>('/storage/channel-stats')
  }
}
