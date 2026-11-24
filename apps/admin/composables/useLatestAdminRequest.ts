import { onScopeDispose } from 'vue'

export function useLatestAdminRequest() {
  let generation = 0
  onScopeDispose(() => { generation += 1 })

  return function beginRequest() {
    const request = ++generation
    return () => request === generation
  }
}
