export interface AdminNavigationItem {
  path: string
  title: string
  icon?: string
  grants: readonly { capability: string; scope: 'all' | 'global' }[]
}

export interface AdminNavigationGroup {
  key: string
  title: string
  icon: string
  items: readonly AdminNavigationItem[]
}

export const adminNavigation: {
  dashboard: AdminNavigationItem
  groups: readonly AdminNavigationGroup[]
} = {
  dashboard: {
    path: '/apps/dashboard',
    title: '总览',
    icon: 'mdi-view-dashboard',
    grants: [{ capability: 'dashboard.read', scope: 'global' }],
  },
  groups: [
    {
      key: 'content',
      title: '内容与社区',
      icon: 'mdi-apps',
      items: [
        { path: '/apps/cards', title: '卡片', grants: [{ capability: 'cards.read', scope: 'all' }] },
        { path: '/apps/comments', title: '评论', grants: [{ capability: 'comments.read', scope: 'all' }] },
        { path: '/apps/tags', title: '标签', grants: [{ capability: 'tags.read', scope: 'all' }] },
        { path: '/apps/community/insights', title: '社区聚合', grants: [{ capability: 'community.insights.read', scope: 'global' }] },
      ],
    },
    {
      key: 'governance',
      title: '治理',
      icon: 'mdi-shield-account-outline',
      items: [
        { path: '/apps/governance/reports', title: '举报处理', grants: [{ capability: 'reports.read', scope: 'global' }] },
        { path: '/apps/security/recovery', title: '账号恢复', grants: [{ capability: 'users.recovery.read', scope: 'global' }] },
      ],
    },
    {
      key: 'rbac',
      title: 'RBAC',
      icon: 'mdi-shield-lock-outline',
      items: [
        { path: '/apps/users', title: '用户管理', grants: [{ capability: 'users.read', scope: 'all' }] },
        { path: '/apps/roles', title: '角色管理', grants: [{ capability: 'roles.read', scope: 'global' }] },
        { path: '/apps/permissions', title: '权限管理', grants: [{ capability: 'permissions.read', scope: 'global' }] },
      ],
    },
    {
      key: 'storage',
      title: '存储',
      icon: 'mdi-cloud-outline',
      items: [
        { path: '/apps/storage/files', title: '文件管理', grants: [{ capability: 'files.read', scope: 'all' }] },
        { path: '/apps/storage/config', title: '存储配置', grants: [{ capability: 'storage.read', scope: 'global' }] },
      ],
    },
    {
      key: 'sender',
      title: '发信',
      icon: 'mdi-email-outline',
      items: [
        { path: '/apps/sender/config', title: '发信配置', grants: [{ capability: 'sender.read', scope: 'global' }] },
      ],
    },
    {
      key: 'operations',
      title: '运维与审计',
      icon: 'mdi-console-line',
      items: [
        { path: '/apps/system/upgrade', title: '系统升级', grants: [{ capability: 'operations.read', scope: 'global' }] },
        { path: '/apps/system/runtime', title: '运行环境', grants: [{ capability: 'operations.read', scope: 'global' }] },
        { path: '/apps/operations/deliveries', title: '通知投递', grants: [{ capability: 'communications.read', scope: 'global' }] },
        { path: '/apps/operations/audit', title: '审计日志', grants: [{ capability: 'audit.read', scope: 'global' }] },
      ],
    },
    {
      key: 'captcha',
      title: '验证',
      icon: 'mdi-shield-check-outline',
      items: [
        { path: '/apps/captcha/config', title: '验证配置', grants: [{ capability: 'captcha.read', scope: 'global' }] },
      ],
    },
    {
      key: 'settings',
      title: '设置',
      icon: 'mdi-cog',
      items: [
        { path: '/apps/system', title: '系统', grants: [{ capability: 'config.read', scope: 'global' }] },
        { path: '/apps/view', title: '外观', grants: [{ capability: 'theme.read', scope: 'global' }] },
        { path: '/apps/view/theme-config', title: '外观配置', grants: [{ capability: 'theme.read', scope: 'global' }] },
      ],
    },
  ],
}

export const adminNavigationItems: readonly AdminNavigationItem[] = [
  adminNavigation.dashboard,
  ...adminNavigation.groups.flatMap((group) => group.items),
]

const routeGrantOverrides: readonly AdminNavigationItem[] = [
  { path: '/apps/users/:id/security', title: '用户安全', grants: [{ capability: 'users.security.read', scope: 'global' }] },
  { path: '/apps/storage/files/:id/usage', title: '文件使用关系', grants: [{ capability: 'files.usage.read', scope: 'global' }] },
]

function matchesPath(pattern: string, path: string): boolean {
  if (pattern === '/apps/updata') return false
  const expression = new RegExp(`^${pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/:[^/]+/g, '[^/]+')}(?:/|$)`)
  return expression.test(path)
}

export function requiredGrants(path: string): readonly { capability: string; scope: 'all' | 'global' }[] {
  if (path === '/apps/updata' || path.startsWith('/apps/updata/')) {
    return [{ capability: 'operations.read', scope: 'global' }]
  }
  const item = [...routeGrantOverrides, ...adminNavigationItems]
    .filter((candidate) => matchesPath(candidate.path, path))
    .sort((a, b) => b.path.length - a.path.length)[0]

  return item?.grants ?? []
}

export function canAccessNavigationItem(
  item: AdminNavigationItem,
  grants: readonly { capability: string; scope: string }[],
): boolean {
  return item.grants.length === 0 || item.grants.every((required) => grants.some((grant) =>
    grant.capability === required.capability && grant.scope === required.scope,
  ))
}
