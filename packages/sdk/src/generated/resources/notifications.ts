import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Notifications extends BaseResource {
  list(params?: { cursor?: string; limit?: number; unread_only?: boolean }): Promise<ApiTypes.CursorPage> {
    return this._get<ApiTypes.CursorPage>("/me/notifications", params)
  }
  readAll(): Promise<void> {
    return this._post<void>("/me/notifications/read-all")
  }
  read(data: { ids: number[] }): Promise<void> {
    return this._post<void>("/me/notifications/read", data)
  }
  stream(params?: { after?: number }): Promise<string> {
    return this._get<string>("/me/notifications/stream", params)
  }
  unread(): Promise<Record<string, unknown>> {
    return this._get<Record<string, unknown>>("/me/notifications/unread")
  }
}
