import { canAccessNavigationItem, requiredGrants } from '~/config/navigation'
import type { Grant } from '@lovecards/sdk'

export { requiredGrants }

export function canAccessPath(path: string, grants: readonly Grant[]): boolean {
    const required = requiredGrants(path)
    return required.length === 0 || required.every((item) => grants.some((grant) =>
        grant.capability === item.capability && grant.scope === item.scope,
    ))
}

export { canAccessNavigationItem }
