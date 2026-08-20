import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Storage extends BaseResource {
  meta(type: string): Promise<ApiTypes.StorageMeta> {
    return this._get<ApiTypes.StorageMeta>(`/admin/storage/${type}/meta`)
  }
  channelStats(): Promise<ApiTypes.ChannelStats> {
    return this._get<ApiTypes.ChannelStats>("/admin/storage/channel-stats")
  }
  channels(): Promise<ApiTypes.StorageChannel[]> {
    return this._get<ApiTypes.StorageChannel[]>("/admin/storage/channels")
  }
  install(): Promise<ApiTypes.StorageMeta[]> {
    return this._post<ApiTypes.StorageMeta[]>("/admin/storage/install")
  }
  testChannel(data: { channel: string; to?: string }): Promise<{ success: boolean; message?: string }> {
    return this._post<{ success: boolean; message?: string }>("/admin/storage/test-channel", data)
  }
  types(): Promise<ApiTypes.StorageDriver[]> {
    return this._get<ApiTypes.StorageDriver[]>("/admin/storage/types")
  }
}
