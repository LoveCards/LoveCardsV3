import { BaseResource } from './base'
import type { ListResult } from '../types/api'
import type { CapabilityItem } from '../types/permissions'

export class Permissions extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_value?: string }): Promise<ListResult<CapabilityItem>> {
    return this._get<ListResult<CapabilityItem>>('/permissions', params)
  }

  all(): Promise<CapabilityItem[]> {
    return this._get<CapabilityItem[]>('/permissions/all')
  }
}
