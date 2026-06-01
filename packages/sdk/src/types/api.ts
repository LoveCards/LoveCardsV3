// ─── 配置 ───

export interface LCClientConfig {
  apiUrl: string
  tokenStore?: TokenStore
  timeout?: number
  onAuthError?: () => void
  debug?: boolean
  retry?: RetryConfig
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
  order_desc?: 0 | 1
}

export type CardsBatchMethod = 'top' | 'unset_top' | 'approve' | 'ban' | 'hide' | 'unhide' | 'delete'
export type CommentsBatchMethod = 'approve' | 'ban' | 'hide' | 'delete'
export type UsersBatchMethod = 'approve' | 'ban' | 'hide' | 'delete'
export type TagsBatchMethod = 'approve' | 'ban' | 'hide' | 'delete'
export type FilesBatchMethod = 'delete'

export type BatchMethod = CardsBatchMethod | CommentsBatchMethod | UsersBatchMethod | TagsBatchMethod | FilesBatchMethod

export interface BatchOperateParams {
  method: BatchMethod
  ids: number[]
  value?: string | number
}
