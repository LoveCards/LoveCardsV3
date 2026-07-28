// ─── 配置 ───

export interface LCClientConfig {
  apiUrl: string
  tokenStore?: TokenStore
  defaultRole?: string
  timeout?: number
  onAuthError?: () => void
  debug?: boolean
  retry?: RetryConfig
  hooks?: {
    beforeRequest?: BeforeRequestHook
    afterResponse?: AfterResponseHook
    onError?: OnErrorHook
  }
}

export interface TokenStore {
  get(): string | null
  set(token: string): void
  clear(): void
}

export interface RetryConfig {
  maxRetries?: number
  retryOn?: number[]
  retryDelay?: number
}

// ─── Lifecycle Hooks ───

export interface RequestContext {
  requestId: string
  method: string
  url: string
  startTime: number
  retryCount: number
  /** 请求配置，可修改 headers（加 trace ID 等）。不要修改 params/data——已过内部序列化 */
  config: {
    headers: Record<string, string | string[] | undefined>
  }
}

export interface ResponseContext extends RequestContext {
  status: number
  /** 解包后的业务数据。204 时为 undefined，审核模式 201 时为 { id: null } */
  data: any
  elapsedMs: number
}

export interface ErrorContext extends RequestContext {
  status: number
  message: string
  code: number
  elapsedMs: number
  isRetryable: boolean
  willRetry: boolean
  reason: 'http' | 'timeout' | 'network' | 'cancelled'
}

/**
 * 请求前 hook。
 * - 可修改 ctx.config.headers（添加自定义 header）
 * - 不要修改 params/data——已过内部序列化
 * - 抛异常 = 中断请求（异常被 ApiError.from 包装，不触发 onError）
 */
export interface BeforeRequestHook {
  (ctx: RequestContext): void | Promise<void>
}

/** 响应后 hook。只读通知，异常被吞掉。不要修改 data。 */
export interface AfterResponseHook {
  (ctx: ResponseContext): void | Promise<void>
}

/** 错误 hook。只读通知，异常被吞掉。 */
export interface OnErrorHook {
  (ctx: ErrorContext): void | Promise<void>
}

export interface HookRegistration {
  beforeRequest(fn: BeforeRequestHook): () => void
  afterResponse(fn: AfterResponseHook): () => void
  onError(fn: OnErrorHook): () => void
}

// ─── 响应结构（内部使用，不导出给前端） ───

export interface RawApiResponse<T> {
  success: boolean
  data: T
  message: string
  timestamp: string
  pagination?: RawPagination
}

export interface RawPagination {
  currentPage: number
  totalPages: number
  totalItems: number
  itemsPerPage: number
}

export interface RawErrorResponse {
  success: false
  error: {
    code: number
    message: string
    details: any
  }
  timestamp: string
}

// ─── 解包后的公共类型 ───

export interface ListResult<T> {
  data: T[]
  pagination?: PaginationInfo
}

export interface CreateResult {
  id: string | null
}

export interface PaginationInfo {
  currentPage: number
  totalPages: number
  totalItems: number
  itemsPerPage: number
}

// ─── 请求参数 ───

export interface PaginationParams {
  page?: number
  list_rows?: number
}

export interface ListParams extends PaginationParams {
  search_value?: string
  search_keys?: string[]
  order_key?: string
  order_desc?: boolean
}

export type CardsBatchMethod = 'top' | 'unset_top' | 'approve' | 'ban' | 'hide' | 'unhide' | 'delete'
export type CommentsBatchMethod = 'approve' | 'ban' | 'hide' | 'delete'
export type UsersBatchMethod = 'approve' | 'ban' | 'hide' | 'delete'
export type TagsBatchMethod = 'approve' | 'ban' | 'hide' | 'delete'
export type FilesBatchMethod = 'approve' | 'ban' | 'toggle_public' | 'trash' | 'restore' | 'hard_delete'

export type BatchMethod = CardsBatchMethod | CommentsBatchMethod | UsersBatchMethod | TagsBatchMethod | FilesBatchMethod

export interface BatchOperateParams {
  method: BatchMethod
  ids: number[]
  value?: string | number
}

export interface FilesBatchOperateParams {
  method: FilesBatchMethod
  ids: number[]
}
