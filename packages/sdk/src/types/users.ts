export interface User {
  id: number
  number: string
  username: string
  email: string
  phone: string
  avatar: string
  roles_id: number[]
  roles?: { id: number; name: string; slug: string }[]
  permissions?: string[]
  status: number
  created_at: string
  updated_at: string
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
  captcha?: string
}

export interface LoginResult {
  token: string
  user: User
}

export interface UpdateUserParams {
  username?: string
  avatar?: string
  password?: string
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
