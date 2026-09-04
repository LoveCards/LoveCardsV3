import { adminNavigationItems, canAccessNavigationItem, requiredGrants } from '~/config/navigation'
import type { Grant } from '@lovecards/sdk'

export { requiredGrants }

export function canAccessPath(path: string, grants: readonly Grant[]): boolean {
    const required = requiredGrants(path)
    return required.length === 0 || required.every((item) => grants.some((grant) =>
        grant.capability === item.capability && grant.scope === item.scope,
    ))
}

/**
 * Pick a deterministic landing page from the same permission-aware navigation
 * contract used by the drawer and route middleware. A management account is
 * not required to have dashboard access.
 */
export function firstAccessibleAdminPath(grants: readonly Grant[]): string | null {
    return adminNavigationItems.find((item) => canAccessNavigationItem(item, grants))?.path ?? null
}

export { canAccessNavigationItem }
