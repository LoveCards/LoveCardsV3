import type { RouterConfig } from '@nuxt/schema'

const hashTarget = (hash: string): HTMLElement | null => {
  if (!hash || typeof document === 'undefined') return null
  try {
    return document.getElementById(decodeURIComponent(hash.slice(1)))
  } catch {
    return null
  }
}

export default <RouterConfig>{
  async scrollBehavior(to, _from, savedPosition) {
    if (savedPosition) return savedPosition

    if (to.hash && typeof requestAnimationFrame !== 'undefined') {
      for (let attempt = 0; attempt < 3; attempt += 1) {
        const target = hashTarget(to.hash)
        if (target) return { el: target, top: 16 }
        await new Promise<void>(resolve => requestAnimationFrame(() => resolve()))
      }
    }

    return { left: 0, top: 0 }
  },
}
