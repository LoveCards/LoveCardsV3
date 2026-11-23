import type { Router } from 'vue-router'

/** 只接受当前资源列表的站内相对路径，避免详情页返回到错误资源或外部地址。 */
export function validateAdminListSource(value: unknown, listPath: string): string | null {
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || value.includes('\\')) return null
  try {
    const url = new URL(value, 'https://admin.local')
    if (url.origin !== 'https://admin.local' || url.pathname !== listPath) return null
    return `${url.pathname}${url.search}${url.hash}`
  } catch {
    return null
  }
}

/** 只在确认上一条历史就是来源列表时回退，以恢复滚动；否则显式导航。 */
export function returnToAdminList(router: Router, listPath: string): void {
  if (typeof window === 'undefined') return
  const source = validateAdminListSource(window.history.state?.listSource, listPath)
  if (source && window.history.state?.back === source) {
    router.back()
    return
  }
  void router.push(source ?? listPath)
}

/** 跨资源详情只接受同站管理端路径；路由 middleware 仍负责目标页面授权。 */
export function validateAdminSource(value: unknown): string | null {
  if (typeof value !== 'string' || !value.startsWith('/apps/') || value.startsWith('//') || value.includes('\\')) return null
  try {
    const url = new URL(value, 'https://admin.local')
    if (url.origin !== 'https://admin.local' || !url.pathname.startsWith('/apps/')) return null
    return `${url.pathname}${url.search}${url.hash}`
  } catch {
    return null
  }
}

/** 来源与上一条历史一致时回退，否则显式导航到来源或资源列表。 */
export function returnToAdminSource(router: Router, fallbackPath: string): void {
  if (typeof window === 'undefined') return
  const source = validateAdminSource(window.history.state?.adminSource)
  if (source && window.history.state?.back === source) {
    router.back()
    return
  }
  void router.push(source ?? fallbackPath)
}
