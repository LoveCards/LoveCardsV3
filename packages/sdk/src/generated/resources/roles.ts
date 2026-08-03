import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Roles extends BaseResource {
  list(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Role>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Role>>("/roles", params)
  }
  create(data: { description?: string; name: string; slug: string }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/roles", data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/roles/${id}`)
  }
  get(id: number): Promise<ApiTypes.Role> {
    return this._get<ApiTypes.Role>(`/roles/${id}`)
  }
  update(id: number, data: { name?: string; slug?: string; description?: string }): Promise<void> {
    return this._patch<void>(`/roles/${id}`, data)
  }
  getRoleCapabilities(id: number): Promise<string[]> {
    return this._get<string[]>(`/roles/${id}/capabilities`)
  }
  assignCapabilities(id: number, data: { capabilities: unknown[] }): Promise<void> {
    return this._post<void>(`/roles/${id}/capabilities`, data)
  }
  reseed(data?: Record<string, unknown>): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/roles/reseed", data)
  }
  getCapabilities(id: number): Promise<string[]> {
    return this._get<string[]>(`/roles/${id}/capabilities`)
  }
}
