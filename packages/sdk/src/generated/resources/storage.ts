import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Storage extends BaseResource {
  types(): Promise<ApiTypes.StorageDriver[]> {
    return this._get<ApiTypes.StorageDriver[]>("/storage/types")
  }
  meta(type: string): Promise<ApiTypes.StorageMeta> {
    return this._get<ApiTypes.StorageMeta>(`/storage/${type}/meta`)
  }
  install(): Promise<ApiTypes.ConfigRegisterResult[]> {
    return this._post<ApiTypes.ConfigRegisterResult[]>("/storage/install")
  }
  channels(): Promise<ApiTypes.StorageChannel[]> {
    return this._get<ApiTypes.StorageChannel[]>("/storage/channels")
  }
  testChannel(data: { channel: string }): Promise<{ success: boolean; message?: string }> {
    return this._post<{ success: boolean; message?: string }>("/storage/test-channel", data)
  }
  channelStats(): Promise<ApiTypes.ChannelStats> {
    return this._get<ApiTypes.ChannelStats>("/storage/channel-stats")
  }
}
