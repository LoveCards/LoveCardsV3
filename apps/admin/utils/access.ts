const routeCapabilities: Record<string, string[]> = {
    '/apps/storage/files': ['files.read', 'files.read.all'],
    '/apps/storage/config': ['storage.read'],
    '/apps/sender/config': ['sender.read'],
    '/apps/captcha/config': ['captcha.read'],
    '/apps/view/theme-config': ['theme.read'],
    '/apps/view': ['theme.read'],
    '/apps/system': ['config.read'],
    '/apps/updata': ['system.update'],
    '/apps/permissions': ['permissions.read'],
    '/apps/roles': ['roles.read'],
    '/apps/users': ['users.read', 'users.read.all'],
    '/apps/comments': ['comments.read', 'comments.read.all'],
    '/apps/tags': ['tags.read', 'tags.read.all'],
    '/apps/cards': ['cards.read', 'cards.read.all'],
    '/apps/dashboard': ['dashboard.read'],
}

export function requiredCapabilities(path: string): string[] {
    const match = Object.keys(routeCapabilities)
        .filter((prefix) => path === prefix || path.startsWith(`${prefix}/`))
        .sort((a, b) => b.length - a.length)[0]
    return match ? (routeCapabilities[match] ?? []) : []
}

export function canAccessPath(path: string, capabilities: string[], isRoot = false): boolean {
    if (isRoot) return true
    const required = requiredCapabilities(path)
    return required.length === 0 || required.some((capability) => capabilities.includes(capability))
}
