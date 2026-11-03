import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Permissions extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.CapabilityItem>> {
    return this._get<ApiTypes.ListResult<ApiTypes.CapabilityItem>>("/admin/permissions", params)
  }
  all(): Promise<ApiTypes.CapabilityItem[]> {
    return this._get<ApiTypes.CapabilityItem[]>("/admin/permissions/all")
  }
}
