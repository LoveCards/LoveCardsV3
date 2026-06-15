export interface RoleInfo {
  id: number
  name: string
  slug: string
}

export interface User {
  id: number
  number: string
  username: string
  email: string
  phone: string
  avatar: string
  roles_id: number[]
  roles?: RoleInfo[]
  capabilities?: string[]
  status: number
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
  user?: { id: number; username: string }
  roles?: RoleInfo[]
}

export interface CheckResult {
  uid: number
  username: string
  roles: RoleInfo[]
  activeRole: string
}

export interface ProfileUpdateParams {
  username?: string
  avatar?: string
  password?: string
}

export interface AdminUserUpdateParams extends ProfileUpdateParams {
  roles_id?: number[]
  status?: number
  email?: string
  phone?: string
}

export interface PasswordParams {
  password: string
}

export interface EmailParams {
  email: string
  captcha: string
}

export interface EmailCaptchaParams {
  email: string
}

export interface CaptchaSendParams {
  account: string
  scene?: string
}
