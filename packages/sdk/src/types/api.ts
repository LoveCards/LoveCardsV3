/** 标准成功响应 */
export interface ApiResponse<T> {
  success: true
  data: T
  message: string
  timestamp: string
  pagination?: PaginationInfo
}

/** 分页元信息 */
export interface PaginationInfo {
  currentPage: number
  totalPages: number
  totalItems: number
  itemsPerPage: number
}

/** 标准错误响应 */
export interface ErrorResponse {
  success: false
  error: {
    code: number
    message: string
    details: any[] | null
  }
  timestamp: string
}

/** 后端原始分页结构（旧格式，渐进过渡用） */
export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginationParams {
  page?: number
  list_rows?: number
}

export interface AdminListParams extends PaginationParams {
  search_value?: string
  search_keys?: string
  order_key?: string
  order_desc?: 0 | 1
}

export interface BatchOperateParams {
  operation: 'top' | 'unset_top' | 'ban' | 'unban' | 'hide' | 'unhide'
    | 'approve' | 'unapprove' | 'delete' | 'restore'
  ids: number[]
  value?: string | number
}
