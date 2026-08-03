import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Storage extends BaseResource {
  meta(type: string): Promise<ApiTypes.StorageMeta> {
    return this._get<ApiTypes.StorageMeta>(`/storage/${type}/meta`)
  }
  channelStats(): Promise<ApiTypes.ChannelStats> {
    return this._get<ApiTypes.ChannelStats>("/storage/channel-stats")
  }
  channels(): Promise<ApiTypes.StorageChannel[]> {
    return this._get<ApiTypes.StorageChannel[]>("/storage/channels")
  }
  install(data?: Record<string, unknown>): Promise<ApiTypes.StorageMeta[]> {
    return this._post<ApiTypes.StorageMeta[]>("/storage/install", data)
  }
  testChannel(data?: Record<string, unknown>): Promise<{ success: boolean; message?: string }> {
    return this._post<{ success: boolean; message?: string }>("/storage/test-channel", data)
  }
  types(): Promise<ApiTypes.StorageDriver[]> {
    return this._get<ApiTypes.StorageDriver[]>("/storage/types")
  }
}
