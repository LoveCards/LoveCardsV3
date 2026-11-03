import { createClient, isApiError, type LCClient } from '@lovecards/sdk'
import { notifyError } from '~/composables/useAdminNotifier'

let client: LCClient

export function useApi(): LCClient {
  if (!client) {
    const runtimeConfig = useRuntimeConfig()
    client = createClient({
      apiUrl: runtimeConfig.public.apiBase || '/api',
      onAuthError: () => {
        localStorage.removeItem('token')
        window.location.href = '/admin/login'
      },
      hooks: {
        onError: (ctx) => {
          if (ctx.status === 403) notifyError('权限不足，无法执行此操作')
          else if (ctx.reason === 'timeout') notifyError('请求超时，请稍后重试')
          else if (ctx.reason === 'network') notifyError('网络连接失败，请检查网络设置')
          else if (ctx.status >= 500) notifyError(ctx.message)
        },
      },
    })
  }
  return client
}

export { isApiError }
