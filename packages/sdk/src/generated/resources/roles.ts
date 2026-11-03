import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Roles extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Role>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Role>>("/admin/roles", params)
  }
  create(data: { description?: string; name: string; slug: string }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/admin/roles", data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/admin/roles/${id}`)
  }
  get(id: number): Promise<ApiTypes.Role> {
    return this._get<ApiTypes.Role>(`/admin/roles/${id}`)
  }
  update(id: number, data?: { name?: string; slug?: string; description?: string }): Promise<void> {
    return this._patch<void>(`/admin/roles/${id}`, data)
  }
  getGrants(id: number): Promise<ApiTypes.Grant[]> {
    return this._get<ApiTypes.Grant[]>(`/admin/roles/${id}/grants`)
  }
  assignGrants(id: number, data: { grants: { capability: string; scope: string }[] }): Promise<void> {
    return this._post<void>(`/admin/roles/${id}/grants`, data)
  }
  batch(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/admin/roles/batch", data)
  }
  reseed(): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/admin/roles/reseed")
  }
}
