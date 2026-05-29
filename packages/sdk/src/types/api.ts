export interface ApiResponse<T> {
  code: number
  message: string
  data: T
}

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
