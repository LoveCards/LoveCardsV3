import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Likes extends BaseResource {
  list(params?: { type?: string }): Promise<ApiTypes.Like[]> {
    return this._get<ApiTypes.Like[]>("/me/likes", params)
  }
  unlike(id: number, params?: { type?: string }): Promise<void> {
    return this._delete<void>(`/me/likes/${id}`, params)
  }
}
