import { BaseResource } from './base'
import type { ListResult } from '../types/api'
import type { Role, CreateRoleParams, UpdateRoleParams, AssignCapabilitiesParams, ReseedResult } from '../types/roles'

export class Roles extends BaseResource {
  list(params?: { page?: number; list_rows?: number }): Promise<ListResult<Role>> {
    return this._get<ListResult<Role>>('/roles', params)
  }

  get(id: number): Promise<Role> {
    return this._get<Role>(`/roles/${id}`)
  }

  create(data: CreateRoleParams): Promise<{ id: string }> {
    return this._post<{ id: string }>('/roles', data)
  }

  update(id: number, data: UpdateRoleParams): Promise<void> {
    return this._patch<void>(`/roles/${id}`, data)
  }

  delete(id: number): Promise<void> {
    return this._delete<void>(`/roles/${id}`)
  }

  getCapabilities(id: number): Promise<string[]> {
    return this._get<string[]>(`/roles/${id}/capabilities`)
  }

  assignCapabilities(id: number, data: AssignCapabilitiesParams): Promise<void> {
    return this._post<void>(`/roles/${id}/capabilities`, data)
  }

  reseed(): Promise<ReseedResult> {
    return this._post<ReseedResult>('/roles/reseed')
  }
}
