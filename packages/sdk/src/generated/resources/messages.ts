import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Messages extends BaseResource {
  conversations(): Promise<Record<string, unknown>[]> {
    return this._get<Record<string, unknown>[]>("/me/conversations")
  }
  list(id: number, params?: { cursor?: string; limit?: number }): Promise<ApiTypes.CursorPage> {
    return this._get<ApiTypes.CursorPage>(`/me/conversations/${id}/messages`, params)
  }
  send(data: { recipient_id: number; body: string; client_message_id?: string }): Promise<Record<string, unknown>> {
    return this._post<Record<string, unknown>>("/me/messages", data)
  }
}
