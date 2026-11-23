import type { Role, RoleInfo, User } from '@lovecards/sdk'

export type UserTargetRoleState = 'root' | 'non-root' | 'unknown'

/** 角色归属无法完整确认时保持 unknown，写入入口必须关闭。 */
export function parseUserRoleIds(rolesId: unknown): number[] | null {
  let ids: unknown = rolesId
  if (ids === undefined || ids === null) return null
  if (typeof ids === 'string') {
    try { ids = JSON.parse(ids) } catch { return null }
  }
  if (typeof ids !== 'number' && !Array.isArray(ids)) return null
  const normalized = (typeof ids === 'number' ? [ids] : ids).map(Number)
  if (normalized.some((id) => !Number.isInteger(id) || id <= 0)) return null
  return normalized
}

export function classifyUserTargetRole(user: User, catalog: Role[], catalogLoaded: boolean): UserTargetRoleState {
  const embedded: RoleInfo[] = Array.isArray(user.roles) ? user.roles : []
  if (embedded.some((role) => role.slug === 'root')) return 'root'
  const ids = parseUserRoleIds(user.roles_id)
  if (ids === null) return 'unknown'
  if (ids.length === 0) return 'non-root'
  if (catalogLoaded) {
    const roles = ids.map((id) => catalog.find((role) => role.id === id))
    if (roles.some((role) => !role)) return 'unknown'
    return roles.some((role) => role?.slug === 'root') ? 'root' : 'non-root'
  }
  const embeddedIds = new Set(embedded.map((role) => role.id))
  return embedded.length > 0 && ids.every((id) => embeddedIds.has(id)) ? 'non-root' : 'unknown'
}
