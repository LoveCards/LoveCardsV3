import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Tags extends BaseResource {
  list(): Promise<ApiTypes.Tag[]> {
    return this._get<ApiTypes.Tag[]>("/tags")
  }
  create(data: { user_id?: string; name: string }): Promise<void> {
    return this._post<void>("/tags", data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/tags/${id}`)
  }
  get(id: number): Promise<ApiTypes.Tag> {
    return this._get<ApiTypes.Tag>(`/tags/${id}`)
  }
  update(id: number, data: { aid?: number; user_id?: string; name?: string; status?: number }): Promise<void> {
    return this._patch<void>(`/tags/${id}`, data)
  }
  listAll(params?: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }): Promise<ApiTypes.ListResult<ApiTypes.Tag>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Tag>>("/tags/all", params)
  }
  batch(data: { ids: unknown[]; method: string }): Promise<void> {
    return this._post<void>("/tags/batch", data)
  }
}
