import axios, { AxiosInstance, AxiosError } from 'axios'
import type { ApiResponse } from './types'

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
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  })

  // Request interceptor: auto-attach token
  instance.interceptors.request.use((cfg) => {
    const token = config.token || (typeof localStorage !== 'undefined' ? localStorage.getItem('token') : null)
    if (token) {
      cfg.headers.Authorization = `Bearer ${token}`
      cfg.headers['X-Token'] = token
    }
    return cfg
  })

  // Response interceptor: handle 401
  instance.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
      if (error.response?.status === 401 && config.onAuthError) {
        config.onAuthError()
      }
      return Promise.reject(error)
    }
  )

  return new LCApiClient(instance)
}

export class LCApiClient {
  private _instance: AxiosInstance
  private _token: string | null = null

  constructor(instance: AxiosInstance) {
    this._instance = instance
  }

  setToken(token: string): void {
    this._token = token
  }

  clearToken(): void {
    this._token = null
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem('token')
    }
  }

  getToken(): string | null {
    return this._token
  }

  get instance(): AxiosInstance {
    return this._instance
  }

  // Cards API
  get cards() {
    return {
      list: (params?: import('./types').CardsListParams) =>
        this._instance.get<ApiResponse<import('./types').Paginated<import('./types').Card>>>('/cards', { params }).then(r => r.data),
      get: (id: number) =>
        this._instance.get<ApiResponse<import('./types').Card>>(`/cards/${id}`).then(r => r.data),
      hot: (params?: import('./types').PaginationParams) =>
        this._instance.get<ApiResponse<import('./types').Paginated<import('./types').Card>>>('/cards/hot', { params }).then(r => r.data),
      search: (params: import('./types').SearchParams) =>
        this._instance.get<ApiResponse<import('./types').Paginated<import('./types').Card>>>('/cards/search', { params }).then(r => r.data),
      create: (data: import('./types').CreateCardParams) =>
        this._instance.post<ApiResponse<import('./types').Card>>('/cards', data).then(r => r.data),
      update: (id: number, data: import('./types').UpdateCardParams) =>
        this._instance.patch<ApiResponse<import('./types').Card>>(`/cards/${id}`, data).then(r => r.data),
      delete: (id: number) =>
        this._instance.delete<ApiResponse<void>>(`/cards/${id}`).then(r => r.data),
      like: (id: number) =>
        this._instance.post<ApiResponse<void>>(`/cards/${id}/like`).then(r => r.data),
    }
  }

  // Users API
  get users() {
    return {
      login: (data: import('./types').LoginParams) =>
        this._instance.post<ApiResponse<import('./types').LoginResult>>('/session/login', data).then(r => r.data),
      register: (data: import('./types').RegisterParams) =>
        this._instance.post<ApiResponse<import('./types').LoginResult>>('/session/register', data).then(r => r.data),
      guest: () =>
        this._instance.post<ApiResponse<import('./types').LoginResult>>('/session/guest').then(r => r.data),
      logout: () =>
        this._instance.post<ApiResponse<void>>('/session/logout').then(r => r.data),
      me: () =>
        this._instance.get<ApiResponse<import('./types').User>>('/users/me').then(r => r.data),
      updateMe: (data: import('./types').UpdateUserParams) =>
        this._instance.patch<ApiResponse<import('./types').User>>('/users/me', data).then(r => r.data),
      updatePassword: (data: import('./types').PasswordParams) =>
        this._instance.post<ApiResponse<void>>('/users/me/password', data).then(r => r.data),
      updateEmail: (data: import('./types').EmailParams) =>
        this._instance.post<ApiResponse<void>>('/users/me/email', data).then(r => r.data),
      captcha: (params: import('./types').CaptchaSendParams) =>
        this._instance.post<ApiResponse<void>>('/session/captcha', params).then(r => r.data),
    }
  }

  // Comments API
  get comments() {
    return {
      listByCard: (cardId: number, params?: import('./types').PaginationParams) =>
        this._instance.get<ApiResponse<import('./types').Paginated<import('./types').Comment>>>(`/comments/card/${cardId}`, { params }).then(r => r.data),
      create: (data: import('./types').CreateCommentParams) =>
        this._instance.post<ApiResponse<import('./types').Comment>>('/comments', data).then(r => r.data),
      delete: (id: number) =>
        this._instance.delete<ApiResponse<void>>(`/comments/${id}`).then(r => r.data),
    }
  }

  // Tags API
  get tags() {
    return {
      list: (params?: import('./types').PaginationParams) =>
        this._instance.get<ApiResponse<import('./types').Paginated<import('./types').Tag>>>('/tags', { params }).then(r => r.data),
      get: (id: number) =>
        this._instance.get<ApiResponse<import('./types').Tag>>(`/tags/${id}`).then(r => r.data),
    }
  }

  // Theme API
  get theme() {
    return {
      config: () =>
        this._instance.get<ApiResponse<{ theme: string; config: Record<string, any> }>>('/theme/config').then(r => r.data),
    }
  }
}
