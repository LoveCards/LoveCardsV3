import { BaseResource } from './base'
import type { ListResult, BatchOperateParams, PaginationParams, ListParams } from '../types/api'
import type { Tag } from '../types/tags'

export class Tags extends BaseResource {
  list(params?: PaginationParams): Promise<Tag[]> {
    return this._get<Tag[]>('/tags', params)
  }

  listAll(params?: ListParams): Promise<ListResult<Tag>> {
    return this._get<ListResult<Tag>>('/tags/all', params)
  }

  get(id: number): Promise<Tag> {
    return this._get<Tag>(`/tags/${id}`)
  }

  create(data: { name: string }): Promise<void> {
    return this._post<void>('/tags', data)
  }

  update(id: number, data: { name: string }): Promise<void> {
    return this._patch<void>(`/tags/${id}`, data)
  }

  delete(id: number): Promise<void> {
    return this._delete<void>(`/tags/${id}`)
  }

  batch(data: BatchOperateParams): Promise<void> {
    return this._post<void>('/tags/batch', data)
  }
}
