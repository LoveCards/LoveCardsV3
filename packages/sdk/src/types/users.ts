export interface RoleInfo {
  id: number
  name: string
  slug: string
}

export interface UserStats {
  received_likes: number
}
export type Gender = 'male' | 'female' | 'non_binary' | 'prefer_not_to_say'

/** Minimal, privacy-filtered user shape returned by @ mention autocomplete. */
export interface MentionCandidate {
  uid: string
  username: string
  avatar: string | null
}

export interface User {
  id: number
  uid: string
  number: string
  username: string
  email: string
  phone: string
  avatar: string
  bio: string | null
  location: string | null
  gender: Gender | null
  birth_date: string | null
  roles_id: number[]
  roles?: RoleInfo[]
  capabilities?: string[]
  grants?: import('./permissions').Grant[]
  status: number
  stats?: UserStats
}

export interface CurrentUser extends User {
  cover_url: string | null
  stats: UserStats
}

export interface PublicUserProfile {
  uid: string
  username: string
  avatar: string | null
  cover_url: string | null
  bio: string | null
  location: string | null
  gender: Gender | null
  birth_date: string | null
  followers: number
  following: number
  can_view_relations: boolean
  stats: UserStats
}

export interface SocialRelation {
  following: boolean
  followed_by: boolean
  blocked_by_me: boolean
  counts: { followers: number; following: number }
}

export interface LoginParams {
  account: string
  password: string
  captcha?: string
}

export interface RegisterParams {
  account: string
  password: string
  password_confirm: string
  code?: string
}

export interface LoginResult {
  token: string
  user?: { uid: string; username: string }
  roles?: RoleInfo[]
}

export interface CheckResult {
  uid: string
  username: string
  roles: RoleInfo[]
  activeRole: string
}

export interface ProfileUpdateParams {
  username?: string
  avatar?: string
  bio?: string | null
  location?: string | null
  gender?: Gender | null
  birth_date?: string | null
  cover_file_id?: number | null
}

export interface AdminUserUpdateParams extends ProfileUpdateParams {
  status?: number
  email?: string
  phone?: string
}

export interface PasswordParams {
  current_password: string
  new_password: string
}

export interface EmailParams {
  current_password: string
  email: string
  captcha: string
}

export interface EmailCaptchaParams {
  email: string
}

export interface EmailCaptchaResult {
  sent: true
  expires_in: number
}

export interface CurrentUserSession {
  id: string
  current: boolean
  status: 'active' | 'revoked' | 'expired'
  created_at: string
  expires_at: string
  last_seen_at: string | null
  device_name: string | null
  ip_address: string | null
  location: string | null
}

export interface CaptchaSendParams {
  account: string
  scene?: string
}
