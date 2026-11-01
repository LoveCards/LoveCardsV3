import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import type { MaybeRefOrGetter } from 'vue'

export function useAdminUnsavedChanges(dirty: MaybeRefOrGetter<boolean>) {
  const dialogOpen = ref(false)
  let resolveNavigation: ((allow: boolean) => void) | null = null

  const hasUnsavedChanges = () => Boolean(toValue(dirty))

  const settleNavigation = (allow: boolean) => {
    const resolve = resolveNavigation
    resolveNavigation = null
    dialogOpen.value = false
    resolve?.(allow)
  }

  const confirmNavigation = () => {
    if (!hasUnsavedChanges()) return true

    resolveNavigation?.(false)
    dialogOpen.value = true
    return new Promise<boolean>((resolve) => {
      resolveNavigation = resolve
    })
  }
  onBeforeRouteLeave(confirmNavigation)
  onBeforeRouteUpdate((to, from) => {
    if (JSON.stringify(to.params) !== JSON.stringify(from.params)) return confirmNavigation()
    return true
  })

  const handleBeforeUnload = (event: BeforeUnloadEvent) => {
    if (!hasUnsavedChanges()) return
    event.preventDefault()
    event.returnValue = ''
  }

  onMounted(() => window.addEventListener('beforeunload', handleBeforeUnload))
  onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    resolveNavigation?.(false)
    resolveNavigation = null
  })

  watch(() => hasUnsavedChanges(), (isDirty) => {
    if (!isDirty && resolveNavigation) settleNavigation(true)
  })

  return {
    unsavedChangesDialog: readonly(dialogOpen),
    continueEditing: () => settleNavigation(false),
    discardChanges: () => settleNavigation(true),
  }
}
