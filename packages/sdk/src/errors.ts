import { AxiosError } from 'axios'

export class ApiError extends Error {
  constructor(
    public code: number,
    message: string,
    public status: number,
    public details?: any,
  ) {
    super(message)
    this.name = 'ApiError'
  }

  static from(error: unknown): ApiError {
    if (error instanceof ApiError) return error

    if (error instanceof AxiosError) {
      const { response } = error

      if (!response) {
        if (error.code === 'ECONNABORTED') return new ApiError(-2, '请求超时，请稍后重试', 0)
        if (error.code === 'ERR_NETWORK') return new ApiError(-3, '网络连接失败，请检查网络设置', 0)
        if (error.code === 'ERR_CANCELED') return new ApiError(-4, '请求已取消', 0)
        return new ApiError(-1, error.message || '网络错误', 0)
      }

      const { status, data } = response

      if (data?.error?.code && data?.error?.message) {
        return new ApiError(data.error.code, data.error.message, status, data.error.details)
      }
      if (data?.code && data?.message) return new ApiError(data.code, data.message, status)
      if (data?.error) return new ApiError(1, data.error, status)

      const msgs: Record<number, string> = {
        400: '请求参数错误',
        401: '未授权，请重新登录',
        403: '权限不足',
        404: '资源不存在',
        405: '请求方法不允许',
        408: '请求超时',
        409: '资源冲突',
        422: '参数验证失败',
        429: '请求过于频繁',
        500: '服务器内部错误',
        502: '网关错误',
        503: '服务不可用',
        504: '网关超时',
      }
      return new ApiError(status, msgs[status] || `请求失败 (${status})`, status)
    }

    if (error instanceof Error) return new ApiError(-1, error.message, 0)
    return new ApiError(-1, '未知错误', 0)
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError
}
