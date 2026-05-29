export interface User {
  id: number
  nickname: string
  email: string
  phone: string
  avatar: string
  roles_id: number
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
  nickname?: string
  avatar?: string
  email?: string
}

export interface PasswordParams {
  old_password: string
  new_password: string
  new_password_confirm: string
}

export interface EmailParams {
  email: string
  captcha: string
}

export interface CaptchaSendParams {
  account: string
  scene?: string
}
