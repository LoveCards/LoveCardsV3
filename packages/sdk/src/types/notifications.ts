import type { CursorPage } from './api'

export type NotificationType =
  | 'social.followed'
  | 'message'
  | 'content.card_liked'
  | 'content.comment_liked'
  | 'library.saved'
  | 'content.commented'
  | 'content.replied'
  | 'content.mentioned'
  | 'system.notice'
  | 'system.security'
  | 'system.moderation'

export type NotificationCategory = 'social' | 'message' | 'interaction' | 'system'
export type NotificationListCategory = NotificationCategory | 'engagement'
export type ConfigurableNotificationType = Exclude<NotificationType, `system.${string}`>
export type SystemNotificationType = Extract<NotificationType, `system.${string}`>
export type NotificationActorState = 'active' | 'unavailable' | 'system'
export type NotificationResourceState = 'available' | 'deleted' | 'restricted' | 'unavailable'

export interface NotificationActor {
  uid: string | null
  username: string
  avatar: string | null
}

export interface NotificationItem {
  id: number
  type: NotificationType
  category: NotificationCategory
  actor_id: number | null
  actor: NotificationActor | null
  actor_state: NotificationActorState
  resource_type: string | null
  resource_id: string | null
  resource_state: NotificationResourceState
  action_url: string | null
  payload: Record<string, unknown>
  read_at: string | null
  created_at: string
}

export type NotificationCursorPage = CursorPage<NotificationItem>
export interface NotificationUnread {
  unread: number
}

export interface NotificationPreferences {
  in_app: Record<ConfigurableNotificationType, boolean>
  locked_types: SystemNotificationType[]
}

export interface NotificationPreferencesUpdate {
  in_app: Partial<Record<ConfigurableNotificationType, boolean>>
}
