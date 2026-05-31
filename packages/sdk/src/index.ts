export { createClient, LCApiClient } from './client'
export type { LCClientConfig } from './client'
export { ApiError, isApiError } from './errors'
export { PUBLIC_API } from './constants'
export type { PublicAPIKey } from './constants'
export * from './types'

// UMD backward compat: window.LoveCards → window.LC（下个大版本移除）
if (typeof globalThis !== 'undefined' && !(globalThis as any).LoveCards && (globalThis as any).LC) {
  ;(globalThis as any).LoveCards = (globalThis as any).LC
}
