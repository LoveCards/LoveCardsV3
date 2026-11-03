import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Reports extends BaseResource {
  listManaged(params?: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }): Promise<ApiTypes.ListResult<ApiTypes.AdminReport>> {
    return this._get<ApiTypes.ListResult<ApiTypes.AdminReport>>("/admin/reports", params)
  }
  getManaged(id: number): Promise<ApiTypes.AdminReport> {
    return this._get<ApiTypes.AdminReport>(`/admin/reports/${id}`)
  }
  transition(id: number, data: { note?: string; resolution?: string; status: string }): Promise<ApiTypes.AdminReport> {
    return this._post<ApiTypes.AdminReport>(`/admin/reports/${id}/transition`, data)
  }
}
