import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Likes extends BaseResource {
  list(): Promise<ApiTypes.Like[]> {
    return this._get<ApiTypes.Like[]>("/likes")
  }
  unlike(id: number): Promise<void> {
    return this._delete<void>(`/likes/${id}`)
  }
}
