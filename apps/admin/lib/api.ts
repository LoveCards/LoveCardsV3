import { createClient, isApiError, type LCClient } from '@lovecards/sdk'
import { showErrorNotification } from '~/api/utils/notifier'

let client: LCClient

export function useApi(): LCClient {
  if (!client) {
    client = createClient({
      apiUrl: '/api',
      onAuthError: () => {
        localStorage.removeItem('token')
        window.location.href = '/admin/dev/login'
      },
      hooks: {
        onError: (ctx) => {
          if (ctx.status === 403) showErrorNotification('权限不足，无法执行此操作')
          else if (ctx.reason === 'timeout') showErrorNotification('请求超时，请稍后重试')
          else if (ctx.reason === 'network') showErrorNotification('网络连接失败，请检查网络设置')
          else if (ctx.status !== 401) showErrorNotification(ctx.message)
        },
      },
    })
  }
  return client
}

export { isApiError }
