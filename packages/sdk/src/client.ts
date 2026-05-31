import axios, { AxiosInstance, AxiosError } from 'axios'
import type { ApiResponse } from './types'
import type * as T from './types'

export interface LCClientConfig {
  apiUrl: string
  token?: string
  timeout?: number
  onAuthError?: () => void
}

export function createClient(config: LCClientConfig): LCApiClient {
  const instance = axios.create({
    baseURL: config.apiUrl,
    timeout: config.timeout || 10000,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  })

  instance.interceptors.request.use((cfg) => {
    const token = config.token || (typeof localStorage !== 'undefined' ? localStorage.getItem('token') : null)
    if (token) {
      cfg.headers.Authorization = `Bearer ${token}`
      cfg.headers['X-Token'] = token
    }
    return cfg
  })

  instance.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
      if (error.response?.status === 401 && config.onAuthError) config.onAuthError()
      return Promise.reject(error)
    }
  )

  return new LCApiClient(instance)
}

function rg<T>(instance: AxiosInstance, url: string, params?: any) {
  return instance.get<ApiResponse<T>>(url, { params }).then(r => r.data)
}
function rp<T>(instance: AxiosInstance, url: string, data?: any, config?: any) {
  return instance.post<ApiResponse<T>>(url, data, config).then(r => r.data)
}
function rpt<T>(instance: AxiosInstance, url: string, data?: any) {
  return instance.patch<ApiResponse<T>>(url, data).then(r => r.data)
}
function rd<T>(instance: AxiosInstance, url: string) {
  return instance.delete<ApiResponse<T>>(url).then(r => r.data)
}

export class LCApiClient {
  private _instance: AxiosInstance
  private _token: string | null = null

  constructor(instance: AxiosInstance) { this._instance = instance }

  setToken(token: string): void { this._token = token }
  clearToken(): void { this._token = null; if (typeof localStorage !== 'undefined') localStorage.removeItem('token') }
  getToken(): string | null { return this._token }
  get instance(): AxiosInstance { return this._instance }

  // Session
  get session() {
    const i = this._instance
    return {
      login: (data: T.LoginParams) => rp<T.LoginResult>(i, '/session/login', data),
      register: (data: T.RegisterParams) => rp<T.LoginResult>(i, '/session/register', data),
      guest: () => rp<T.LoginResult>(i, '/session/guest'),
      logout: () => rp<void>(i, '/session/logout'),
      captcha: (params: T.CaptchaSendParams) => rp<void>(i, '/session/captcha', params),
      check: () => rg<void>(i, '/session/check'),
    }
  }

  // Cards
  get cards() {
    const i = this._instance
    return {
      list: (params?: T.CardsListParams) => rg<T.Card[]>(i, '/cards', params),
      get: (id: number) => rg<T.Card>(i, `/cards/${id}`),
      hot: () => rg<T.Card[]>(i, '/cards/hot'),
      search: (params: T.SearchParams) => rg<T.Card[]>(i, '/cards/search', params),
      create: (data: T.CreateCardParams) => rp<{ id: string }>(i, '/cards', data),
      update: (id: number, data: T.UpdateCardParams) => rpt<void>(i, `/cards/${id}`, data),
      delete: (id: number) => rd<void>(i, `/cards/${id}`),
      like: (id: number) => rp<void>(i, `/cards/${id}/like`),
      listOwn: () => rg<T.Card[]>(i, '/users/me/cards'),
      allList: (params?: T.AdminListParams) => rg<T.Card[]>(i, '/all/cards', params),
      allGet: (id: number) => rg<T.Card>(i, `/all/cards/${id}`),
      allUpdate: (id: number, data: T.UpdateCardParams) => rpt<void>(i, `/all/cards/${id}`, data),
      allDelete: (id: number) => rd<void>(i, `/all/cards/${id}`),
      batch: (data: T.BatchOperateParams) => rp<void>(i, '/all/cards/batch', data),
    }
  }

  // Users
  get users() {
    const i = this._instance
    return {
      me: () => rg<T.User>(i, '/users/me'),
      updateMe: (data: T.UpdateUserParams) => rpt<void>(i, '/users/me', data),
      updatePassword: (data: T.PasswordParams) => rp<void>(i, '/users/me/password', data),
      updateEmail: (data: T.EmailParams) => rp<void>(i, '/users/me/email', data),
      emailCaptcha: (data: T.EmailCaptchaParams) => rp<void>(i, '/users/me/email-captcha', data),
      allList: (params?: T.AdminListParams) => rg<T.User[]>(i, '/all/users', params),
      allGet: (id: number) => rg<T.User>(i, `/all/users/${id}`),
      allUpdate: (id: number, data: T.UpdateUserParams) => rpt<void>(i, `/all/users/${id}`, data),
      allDelete: (id: number) => rd<void>(i, `/all/users/${id}`),
      batch: (data: T.BatchOperateParams) => rp<void>(i, '/all/users/batch', data),
    }
  }

  // Comments
  get comments() {
    const i = this._instance
    return {
      cardList: (cardId: number, params?: T.PaginationParams) => rg<T.Comment[]>(i, `/cards/${cardId}/comments`, params),
      create: (cardId: number, data: T.CreateCommentParams) => rp<T.Comment>(i, `/cards/${cardId}/comments`, data),
      get: (id: number) => rg<T.Comment>(i, `/comments/${id}`),
      update: (id: number, data: { content: string }) => rpt<void>(i, `/comments/${id}`, data),
      delete: (id: number) => rd<void>(i, `/comments/${id}`),
      listOwn: () => rg<T.Comment[]>(i, '/users/me/comments'),
      allList: (params?: T.AdminListParams) => rg<T.Comment[]>(i, '/all/comments', params),
      allGet: (id: number) => rg<T.Comment>(i, `/all/comments/${id}`),
      allUpdate: (id: number, data: { content: string }) => rpt<void>(i, `/all/comments/${id}`, data),
      allDelete: (id: number) => rd<void>(i, `/all/comments/${id}`),
      batch: (data: T.BatchOperateParams) => rp<void>(i, '/all/comments/batch', data),
    }
  }

  // Tags
  get tags() {
    const i = this._instance
    return {
      list: (params?: T.PaginationParams) => rg<T.Tag[]>(i, '/tags', params),
      get: (id: number) => rg<T.Tag>(i, `/tags/${id}`),
      create: (data: { name: string }) => rp<void>(i, '/tags', data),
      update: (id: number, data: { name: string }) => rpt<void>(i, `/tags/${id}`, data),
      delete: (id: number) => rd<void>(i, `/tags/${id}`),
      allList: (params?: T.AdminListParams) => rg<T.Tag[]>(i, '/all/tags', params),
      allCreate: (data: { name: string }) => rp<void>(i, '/all/tags', data),
      allUpdate: (id: number, data: { name: string }) => rpt<void>(i, `/all/tags/${id}`, data),
      allDelete: (id: number) => rd<void>(i, `/all/tags/${id}`),
      batch: (data: T.BatchOperateParams) => rp<void>(i, '/all/tags/batch', data),
    }
  }

  // Likes
  get likes() {
    const i = this._instance
    return {
      list: () => rg<T.LikeItem[]>(i, '/likes'),
      unlike: (id: number) => rd<void>(i, `/likes/${id}`),
    }
  }

  // Files
  get files() {
    const i = this._instance
    return {
      upload: (formData: FormData) => rp<T.FileItem>(i, '/files', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
      list: (params?: T.AdminListParams) => rg<T.FileItem[]>(i, '/files', params),
      get: (id: number) => rg<T.FileItem>(i, `/files/${id}`),
      direct: () => rp<T.DirectUploadResult>(i, '/files/direct'),
      confirm: (id: number) => rpt<void>(i, `/files/${id}/confirm`),
      batch: (data: T.BatchOperateParams) => rp<void>(i, '/files/batch', data),
      cleanup: () => rd<void>(i, '/files/expired'),
      allDelete: (id: number) => rd<void>(i, `/all/files/${id}`),
    }
  }

  // Theme
  get theme() {
    const i = this._instance
    return {
      list: () => rg<T.ThemeItem[]>(i, '/all/theme/list'),
      upload: (formData: FormData) => rp<void>(i, '/all/theme/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } }),
      activate: (data: { theme: string }) => rp<void>(i, '/all/theme/activate', data),
      config: () => rg<T.ThemeConfigData>(i, '/all/theme/config'),
      updateConfig: (data: Record<string, any>) => i.put<ApiResponse<void>>('/all/theme/config', data).then(r => r.data),
      freeze: () => rp<void>(i, '/all/theme/freeze'),
      delete: (data: { theme: string }) => i.delete<ApiResponse<void>>('/all/theme/delete', { data }).then(r => r.data),
      publicConfig: () => rg<{ theme: string; config: Record<string, any> }>(i, '/theme/config'),
    }
  }
}
