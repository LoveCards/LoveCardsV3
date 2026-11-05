import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Tags extends BaseResource {
  listManaged(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Tag>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Tag>>("/admin/tags", params)
  }
  deleteManaged(id: number): Promise<void> {
    return this._delete<void>(`/admin/tags/${id}`)
  }
  getManaged(id: number): Promise<ApiTypes.Tag> {
    return this._get<ApiTypes.Tag>(`/admin/tags/${id}`)
  }
  updateManaged(id: number, data?: { name?: string; status?: number }): Promise<void> {
    return this._patch<void>(`/admin/tags/${id}`, data)
  }
  batchManaged(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/admin/tags/batch", data)
  }
  listOwn(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Tag>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Tag>>("/me/tags", params)
  }
  create(data: { name: string }): Promise<ApiTypes.Tag> {
    return this._post<ApiTypes.Tag>("/me/tags", data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/me/tags/${id}`)
  }
  update(id: number, data?: { name?: string }): Promise<void> {
    return this._patch<void>(`/me/tags/${id}`, data)
  }
  list(): Promise<ApiTypes.Tag[]> {
    return this._get<ApiTypes.Tag[]>("/tags")
  }
  get(id: number): Promise<ApiTypes.Tag> {
    return this._get<ApiTypes.Tag>(`/tags/${id}`)
  }
  policy(): Promise<Record<string, unknown>> {
    return this._get<Record<string, unknown>>("/tags/policy")
  }
  listMe(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Tag>> {
    return this.listOwn(params)
  }
}
