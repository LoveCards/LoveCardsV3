import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Permissions extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.CapabilityItem>> {
    return this._get<ApiTypes.ListResult<ApiTypes.CapabilityItem>>("/permissions", params)
  }
  all(): Promise<ApiTypes.CapabilityItem[]> {
    return this._get<ApiTypes.CapabilityItem[]>("/permissions/all")
  }
}
