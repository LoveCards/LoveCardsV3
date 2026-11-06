import axios, { AxiosInstance } from 'axios'
import type { LCClientConfig, TokenStore, RetryConfig, HookRegistration } from './types/api'
export type { LCClientConfig, HookRegistration } from './types/api'
import { ApiError } from './errors'
import { Deduplicator } from './dedupe'
import { defaultTokenStore, defaultConfig } from './config/defaults'
import type { ResourceOptions } from './resources/base'

import { Session } from './generated/resources/session'
import { Cards } from './generated/resources/cards'
import { Users } from './generated/resources/users'
import { Comments } from './generated/resources/comments'
import { Tags } from './generated/resources/tags'
import { Likes } from './generated/resources/likes'
import { Files } from './generated/resources/files'
import { Theme } from './generated/resources/theme'
import { Roles } from './generated/resources/roles'
import { Permissions } from './generated/resources/permissions'
import { Config } from './generated/resources/config'
import { Dashboard } from './generated/resources/dashboard'
import { Storage } from './generated/resources/storage'
import { Sender } from './generated/resources/sender'
import { Captcha } from './generated/resources/captcha'
import { System } from './generated/resources/system'
import { Drafts } from './generated/resources/drafts'
import { Library } from './generated/resources/library'
import { Messages } from './generated/resources/messages'
import { Notifications } from './generated/resources/notifications'
import { Safety } from './generated/resources/safety'
import { Social } from './generated/resources/social'
import { Operations } from './generated/resources/operations'
import { Reports } from './generated/resources/reports'
import { Community } from './generated/resources/community'
import { Communication } from './generated/resources/communication'
import { Communications } from './generated/resources/communications'

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
  readonly drafts: Drafts
  readonly library: Library
  readonly messages: Messages
  readonly notifications: Notifications
  readonly safety: Safety
  readonly social: Social
  readonly operations: Operations
  readonly reports: Reports
  readonly community: Community
  readonly communication: Communication
  readonly communications: Communications
  readonly hooks: HookRegistration

  setToken(token: string): void
  clearToken(): void
  getToken(): string | null
  setRole(slug: string): void
  getRole(): string | null
}

export function createClient(config: LCClientConfig): LCClient {
  const tokenStore = config.tokenStore ?? defaultTokenStore
  const timeout = config.timeout ?? defaultConfig.timeout
  const debug = config.debug ?? false
  const retry: RetryConfig = config.retry ?? {}

  const instance = axios.create({
    baseURL: config.apiUrl.trim(),
    timeout,
    headers: { 'Content-Type': 'application/json' },
  })

  // 当前角色（可变）
  let currentRole: string | null = config.defaultRole ?? null

  // 请求拦截：注入 token + X-Role
  instance.interceptors.request.use((cfg) => {
    const token = tokenStore.get()
    if (token) {
      cfg.headers.Authorization = `Bearer ${token}`
      cfg.headers['X-Token'] = token
    }
    if (currentRole) {
      cfg.headers['X-Role'] = currentRole
    }
    return cfg
  })

  // 响应拦截：401 回调 + 错误转换
  instance.interceptors.response.use(
    (response) => response,
    (error) => {
      const apiErr = ApiError.from(error)
      const headers = error?.config?.headers
      const sentWithToken = Boolean(
        headers?.Authorization
        || headers?.authorization
        || headers?.['X-Token']
        || headers?.['x-token'],
      )
      if (apiErr.status === 401 && sentWithToken && config.onAuthError) config.onAuthError()
      return Promise.reject(apiErr)
    }
  )

  const opts: ResourceOptions = {
    tokenStore,
    debug,
    retry,
    dedupe: new Deduplicator(),
    hooks: {
      beforeRequest: config.hooks?.beforeRequest ? [config.hooks.beforeRequest] : [],
      afterResponse: config.hooks?.afterResponse ? [config.hooks.afterResponse] : [],
      onError: config.hooks?.onError ? [config.hooks.onError] : [],
    },
  }

  return new LCClientImpl(instance, opts, tokenStore, () => currentRole, (r: string) => { currentRole = r })
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
  readonly drafts: Drafts
  readonly library: Library
  readonly messages: Messages
  readonly notifications: Notifications
  readonly safety: Safety
  readonly social: Social
  readonly operations: Operations
  readonly reports: Reports
  readonly community: Community
  readonly communication: Communication
  readonly communications: Communications
  readonly hooks: HookRegistration

  private _tokenStore: TokenStore
  private _getRole: () => string | null
  private _setRole: (r: string) => void

  constructor(
    instance: AxiosInstance,
    opts: ResourceOptions,
    tokenStore: TokenStore,
    getRole: () => string | null,
    setRole: (r: string) => void,
  ) {
    this._tokenStore = tokenStore
    this._getRole = getRole
    this._setRole = setRole
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
    this.drafts = new Drafts(instance, opts)
    this.library = new Library(instance, opts)
    this.messages = new Messages(instance, opts)
    this.notifications = new Notifications(instance, opts)
    this.safety = new Safety(instance, opts)
    this.social = new Social(instance, opts)
    this.operations = new Operations(instance, opts)
    this.reports = new Reports(instance, opts)
    this.community = new Community(instance, opts)
    this.communication = new Communication(instance, opts)
    this.communications = new Communications(instance, opts)

    // 运行时 hook 注册
    const hookStore = opts.hooks
    this.hooks = {
      beforeRequest(fn) {
        hookStore.beforeRequest.push(fn)
        return () => {
          const idx = hookStore.beforeRequest.indexOf(fn)
          if (idx >= 0) hookStore.beforeRequest.splice(idx, 1)
        }
      },
      afterResponse(fn) {
        hookStore.afterResponse.push(fn)
        return () => {
          const idx = hookStore.afterResponse.indexOf(fn)
          if (idx >= 0) hookStore.afterResponse.splice(idx, 1)
        }
      },
      onError(fn) {
        hookStore.onError.push(fn)
        return () => {
          const idx = hookStore.onError.indexOf(fn)
          if (idx >= 0) hookStore.onError.splice(idx, 1)
        }
      },
    }
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

  setRole(slug: string): void {
    this._setRole(slug)
  }

  getRole(): string | null {
    return this._getRole()
  }
}
