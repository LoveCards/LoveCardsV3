import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Notifications extends BaseResource {
  prepareBatch(data: { request_key: string; audience: string; recipient_ids?: number[]; message: string; action_url?: string; severity?: string }): Promise<ApiTypes.SystemNoticeBatch> {
    return this._post<ApiTypes.SystemNoticeBatch>("/admin/notifications/batches", data)
  }
  getBatch(id: number): Promise<ApiTypes.SystemNoticeBatch> {
    return this._get<ApiTypes.SystemNoticeBatch>(`/admin/notifications/batches/${id}`)
  }
  confirmBatch(id: number, data?: Record<string, unknown>): Promise<ApiTypes.SystemNoticeBatch> {
    return this._post<ApiTypes.SystemNoticeBatch>(`/admin/notifications/batches/${id}/confirm`, data)
  }
  retryBatch(id: number, data: { expected_generation: number; reason: string }): Promise<ApiTypes.SystemNoticeBatch> {
    return this._post<ApiTypes.SystemNoticeBatch>(`/admin/notifications/batches/${id}/retry`, data)
  }
  latestBatch(params?: { request_key?: string }): Promise<ApiTypes.SystemNoticeLatestBatch> {
    return this._get<ApiTypes.SystemNoticeLatestBatch>("/admin/notifications/batches/latest", params)
  }
  recipientCandidates(params: { query: string; limit?: number }): Promise<ApiTypes.SystemNoticeRecipient[]> {
    return this._get<ApiTypes.SystemNoticeRecipient[]>("/admin/notifications/recipients", params)
  }
  list(params?: { cursor?: string; limit?: number; unread_only?: boolean; type?: "social.followed" | "message" | "content.card_liked" | "content.comment_liked" | "library.saved" | "content.commented" | "content.replied" | "content.mentioned" | "system.notice" | "system.security" | "system.moderation"; category?: ApiTypes.NotificationListCategory }): Promise<ApiTypes.NotificationCursorPage> {
    return this._get<ApiTypes.NotificationCursorPage>("/me/notifications", params)
  }
  preferences(): Promise<ApiTypes.NotificationPreferences> {
    return this._get<ApiTypes.NotificationPreferences>("/me/notifications/preferences")
  }
  updatePreferences(data: ApiTypes.NotificationPreferencesUpdate): Promise<ApiTypes.NotificationPreferences> {
    return this._post<ApiTypes.NotificationPreferences>("/me/notifications/preferences", data)
  }
  readAll(data?: { category?: ApiTypes.NotificationListCategory }): Promise<void> {
    return this._post<void>("/me/notifications/read-all", data)
  }
  read(data: { ids: number[] }): Promise<void> {
    return this._post<void>("/me/notifications/read", data)
  }
  streamTicket(): Promise<ApiTypes.StreamTicket> {
    return this._post<ApiTypes.StreamTicket>("/me/notifications/stream-ticket")
  }
  unread(params?: { category?: ApiTypes.NotificationListCategory }): Promise<ApiTypes.NotificationUnread> {
    return this._get<ApiTypes.NotificationUnread>("/me/notifications/unread", params)
  }
}
