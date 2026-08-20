import { useNotificationStore, type Notification } from '~/stores/notificationStore'

type ToastInput = {
  text: string
  type?: Notification['type']
  timeout?: number
}

export function useAdminNotifier() {
  const notificationStore = useNotificationStore()

  return {
    toast(input: ToastInput) {
      notificationStore.add({
        message: input.text,
        type: input.type ?? 'info',
        timeout: input.timeout,
      })
    },
    success(message: string, timeout?: number) {
      notificationStore.add({ message, type: 'success', timeout })
    },
    error(message: string, timeout?: number) {
      notificationStore.add({ message, type: 'error', timeout })
    },
    info(message: string, timeout?: number) {
      notificationStore.add({ message, type: 'info', timeout })
    },
    warning(message: string, timeout?: number) {
      notificationStore.add({ message, type: 'warning', timeout })
    },
  }
}

export function notifyError(message: string, timeout = 5000) {
  useNotificationStore().add({ message, type: 'error', timeout })
}
