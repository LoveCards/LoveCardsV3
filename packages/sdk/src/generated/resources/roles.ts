import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Roles extends BaseResource {
  list(params?: { page?: number; list_rows?: number }): Promise<ApiTypes.ListResult<ApiTypes.Role>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Role>>("/roles", params)
  }
  get(id: number): Promise<ApiTypes.Role> {
    return this._get<ApiTypes.Role>(`/roles/${id}`)
  }
  create(data: ApiTypes.CreateRoleParams): Promise<{ id: string }> {
    return this._post<{ id: string }>("/roles", data)
  }
  update(id: number, data: ApiTypes.UpdateRoleParams): Promise<void> {
    return this._patch<void>(`/roles/${id}`, data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/roles/${id}`)
  }
  getCapabilities(id: number): Promise<string[]> {
    return this._get<string[]>(`/roles/${id}/capabilities`)
  }
  assignCapabilities(id: number, data: ApiTypes.AssignCapabilitiesParams): Promise<void> {
    return this._post<void>(`/roles/${id}/capabilities`, data)
  }
  reseed(): Promise<ApiTypes.ReseedResult> {
    return this._post<ApiTypes.ReseedResult>("/roles/reseed")
  }
}
