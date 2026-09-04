import type { CursorPage } from './api'

export type ProfileVisibility = 'public' | 'followers' | 'private'
export type MessagesFrom = 'everyone' | 'following' | 'none'
export type FieldVisibility = 'public' | 'followers' | 'private'

export interface PrivacyPreferences {
  profile_visibility: ProfileVisibility
  messages_from: MessagesFrom
  show_relations: boolean
  gender_visibility: FieldVisibility
  birth_date_visibility: FieldVisibility
}

export interface BlockedUserItem {
  user: {
    uid: string
    username: string
    avatar: string | null
  }
  blocked_at: string
}

export type BlockedUserCursorPage = CursorPage<BlockedUserItem>
