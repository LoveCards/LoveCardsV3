import axios, { AxiosInstance } from 'axios'
import type { LCClientConfig, TokenStore, RetryConfig } from './types/api'
export type { LCClientConfig } from './types/api'
import { ApiError } from './errors'
import { Deduplicator } from './dedupe'
import { defaultTokenStore, defaultConfig } from './config/defaults'
import type { ResourceOptions } from './resources/base'

import { Session } from './resources/session'
import { Cards } from './resources/cards'
import { Users } from './resources/users'
import { Comments } from './resources/comments'
import { Tags } from './resources/tags'
import { Likes } from './resources/likes'
import { Files } from './resources/files'
import { Theme } from './resources/theme'
import { Roles } from './resources/roles'
import { Permissions } from './resources/permissions'
import { Config } from './resources/config'
import { Dashboard } from './resources/dashboard'
import { Storage } from './resources/storage'
import { Sender } from './resources/sender'
import { Captcha } from './resources/captcha'
import { System } from './resources/system'

export interface LCClient {
  readonly session: Session
  readonly cards: Cards
  readonly users: Users
  readonly comments: Comments
  readonly tags: Tags
  readonly likes: Likes
  readonly files: Files
  readonly theme: Theme
  readonly roles: Roles
  readonly permissions: Permissions
  readonly config: Config
  readonly dashboard: Dashboard
  readonly storage: Storage
  readonly sender: Sender
  readonly captcha: Captcha
  readonly system: System

  setToken(token: string): void
  clearToken(): void
  getToken(): string | null
}

export function createClient(config: LCClientConfig): LCClient {
  const tokenStore = config.tokenStore ?? defaultTokenStore
  const timeout = config.timeout ?? defaultConfig.timeout
  const debug = config.debug ?? false
  const retry: RetryConfig = config.retry ?? {}

  const instance = axios.create({
    baseURL: config.apiUrl,
    timeout,
    headers: { 'Content-Type': 'application/json' },
  })

  // 请求拦截：注入 token
  instance.interceptors.request.use((cfg) => {
    const token = tokenStore.get()
    if (token) {
      cfg.headers.Authorization = `Bearer ${token}`
      cfg.headers['X-Token'] = token
    }
    return cfg
  })

  // 响应拦截：401 回调 + 错误转换
  instance.interceptors.response.use(
    (response) => response,
    (error) => {
      const apiErr = ApiError.from(error)
      if (apiErr.status === 401 && config.onAuthError) config.onAuthError()
      return Promise.reject(apiErr)
    }
  )

  const opts: ResourceOptions = {
    tokenStore,
    debug,
    retry,
    dedupe: new Deduplicator(),
  }

  return new LCClientImpl(instance, opts, tokenStore)
}

class LCClientImpl implements LCClient {
  readonly session: Session
  readonly cards: Cards
  readonly users: Users
  readonly comments: Comments
  readonly tags: Tags
  readonly likes: Likes
  readonly files: Files
  readonly theme: Theme
  readonly roles: Roles
  readonly permissions: Permissions
  readonly config: Config
  readonly dashboard: Dashboard
  readonly storage: Storage
  readonly sender: Sender
  readonly captcha: Captcha
  readonly system: System

  private _tokenStore: TokenStore

  constructor(instance: AxiosInstance, opts: ResourceOptions, tokenStore: TokenStore) {
    this._tokenStore = tokenStore
    this.session = new Session(instance, opts)
    this.cards = new Cards(instance, opts)
    this.users = new Users(instance, opts)
    this.comments = new Comments(instance, opts)
    this.tags = new Tags(instance, opts)
    this.likes = new Likes(instance, opts)
    this.files = new Files(instance, opts)
    this.theme = new Theme(instance, opts)
    this.roles = new Roles(instance, opts)
    this.permissions = new Permissions(instance, opts)
    this.config = new Config(instance, opts)
    this.dashboard = new Dashboard(instance, opts)
    this.storage = new Storage(instance, opts)
    this.sender = new Sender(instance, opts)
    this.captcha = new Captcha(instance, opts)
    this.system = new System(instance, opts)
  }

  setToken(token: string): void {
    this._tokenStore.set(token)
  }

  clearToken(): void {
    this._tokenStore.clear()
  }

  getToken(): string | null {
    return this._tokenStore.get()
  }
}
