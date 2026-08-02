import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Tags extends BaseResource {
  list(params?: ApiTypes.PaginationParams): Promise<ApiTypes.Tag[]> {
    return this._get<ApiTypes.Tag[]>("/tags", params)
  }
  listAll(params?: ApiTypes.ListParams): Promise<ApiTypes.ListResult<ApiTypes.Tag>> {
    return this._get<ApiTypes.ListResult<ApiTypes.Tag>>("/tags/all", params)
  }
  get(id: number): Promise<ApiTypes.Tag> {
    return this._get<ApiTypes.Tag>(`/tags/${id}`)
  }
  create(data: { name: string }): Promise<void> {
    return this._post<void>("/tags", data)
  }
  update(id: number, data: { name: string }): Promise<void> {
    return this._patch<void>(`/tags/${id}`, data)
  }
  delete(id: number): Promise<void> {
    return this._delete<void>(`/tags/${id}`)
  }
  batch(data: ApiTypes.BatchOperateParams): Promise<void> {
    return this._post<void>("/tags/batch", data)
  }
}
