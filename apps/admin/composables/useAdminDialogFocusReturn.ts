import { watch, type WatchSource } from 'vue'

/** 记录外部状态打开的弹窗入口，并在 Vuetify 退出过渡结束后恢复焦点。 */
export function useAdminDialogFocusReturn(open: WatchSource<boolean>) {
  const route = useRoute()
  let trigger: HTMLElement | null = null
  let sourcePath = ''

  watch(open, (visible) => {
    if (!visible || !import.meta.client) return
    const active = document.activeElement
    trigger = active instanceof HTMLElement && active !== document.body ? active : null
    sourcePath = route.fullPath
  }, { flush: 'sync' })

  return () => {
    if (import.meta.client && route.fullPath === sourcePath && trigger?.isConnected) {
      trigger.focus()
    }
    trigger = null
  }
}
