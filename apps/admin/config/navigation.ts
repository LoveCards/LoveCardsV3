/**
 * 管理端导航契约。
 *
 * `path` 与 `canonicalPath` 始终指向任务型 IA 约定的规范路径；旧版书签只通过
 * `legacyAliases` 对应的轻量重定向页面继续工作，不得重新成为导航主入口。
 *
 * 新增字段全部为可选字段（除 group 的 domain 外），这样旧的调用方只读取
 * path/title/icon/grants 时无需任何改动。权限判断始终使用 grants，导航元数据
 * 不能替代后端授权。
 */

export type AdminGrantScope = 'all' | 'global'

export interface AdminGrantRequirement {
  capability: string
  scope: AdminGrantScope
}

export type AdminRiskLevel = 'normal' | 'sensitive' | 'high'
export type AdminDataScope = 'global' | 'resource' | 'self'

export type AdminNavigationDomain =
  | 'workbench'
  | 'content-community'
  | 'user-security'
  | 'system-config'
  | 'operations-audit'
  | 'access-control'

export interface AdminNavigationItem {
  /** 当前部署页面的规范路径。 */
  path: string
  title: string
  icon?: string
  grants: readonly AdminGrantRequirement[]
  /** 页面搜索使用的旧名称或同义词，不作为菜单标签显示。 */
  searchAliases?: readonly string[]

  /** 任务域及页面上下文元数据，供 shell、面包屑和工作台使用。 */
  domain?: AdminNavigationDomain
  section?: string
  parentRoute?: string
  canonicalPath?: string

  /** 规范路径之外的兼容入口；新链接不得使用这些地址。 */
  aliases?: readonly string[]
  legacyAliases?: readonly string[]

  /** 页面读取范围、风险级别和动作级 capability 提示。 */
  dataScope?: AdminDataScope
  riskLevel?: AdminRiskLevel
  actionCapabilities?: readonly string[]
}

export interface AdminNavigationGroup {
  key: string
  title: string
  icon: string
  /** 一级域标识；旧调用方可以继续只使用 key/title/icon/items。 */
  domain?: AdminNavigationDomain
  items: readonly AdminNavigationItem[]
}

const dashboardGrant: AdminGrantRequirement = { capability: 'dashboard.read', scope: 'global' }
const operationsGrant: AdminGrantRequirement = { capability: 'operations.read', scope: 'global' }
const auditGrant: AdminGrantRequirement = { capability: 'audit.read', scope: 'global' }

function item(
  definition: Omit<AdminNavigationItem, 'grants'> & { grants?: readonly AdminGrantRequirement[] },
): AdminNavigationItem {
  return {
    grants: [],
    dataScope: 'global',
    riskLevel: 'normal',
    ...definition,
  }
}

/**
 * 工作台是侧栏唯一的一级直达入口，其余页面按任务组展开。
 */
const dashboard = item({
  path: '/apps/dashboard',
  canonicalPath: '/apps/dashboard',
  title: '工作台',
  icon: 'mdi-view-dashboard',
  domain: 'workbench',
  section: '工作台',
  grants: [dashboardGrant],
})

const contentItems: readonly AdminNavigationItem[] = [
  item({
    path: '/apps/content/cards',
    canonicalPath: '/apps/content/cards',
    legacyAliases: ['/apps/cards'],
    title: '卡片',
    icon: 'mdi-card-text-outline',
    domain: 'content-community',
    section: '内容管理',
    parentRoute: '/apps/content/cards',
    grants: [{ capability: 'cards.read', scope: 'all' }],
    dataScope: 'global',
    actionCapabilities: ['cards.update', 'cards.update.all', 'cards.delete', 'cards.delete.all', 'cards.approve', 'cards.approve.all', 'cards.pin', 'cards.pin.all'],
  }),
  item({
    path: '/apps/content/comments',
    canonicalPath: '/apps/content/comments',
    legacyAliases: ['/apps/comments'],
    title: '评论',
    icon: 'mdi-comment-text-outline',
    domain: 'content-community',
    section: '内容管理',
    parentRoute: '/apps/content/comments',
    grants: [{ capability: 'comments.read', scope: 'all' }],
    dataScope: 'global',
    actionCapabilities: ['comments.update.all', 'comments.moderate', 'comments.moderate.all', 'comments.pin.all', 'comments.delete', 'comments.delete.all'],
  }),
  item({
    path: '/apps/content/tags',
    canonicalPath: '/apps/content/tags',
    legacyAliases: ['/apps/tags'],
    title: '标签',
    icon: 'mdi-tag-multiple-outline',
    domain: 'content-community',
    section: '内容管理',
    parentRoute: '/apps/content/tags',
    grants: [{ capability: 'tags.read', scope: 'all' }],
    dataScope: 'global',
    actionCapabilities: ['tags.create', 'tags.update', 'tags.update.all', 'tags.delete', 'tags.delete.all'],
  }),
  item({
    path: '/apps/settings/storage/files',
    canonicalPath: '/apps/settings/storage/files',
    legacyAliases: ['/apps/storage/files'],
    title: '文件管理',
    icon: 'mdi-file-multiple-outline',
    domain: 'content-community',
    section: '内容管理',
    parentRoute: '/apps/settings/storage/files',
    grants: [{ capability: 'files.read', scope: 'all' }],
    dataScope: 'global',
    actionCapabilities: ['files.update', 'files.update.all', 'files.delete', 'files.delete.all', 'files.restore', 'files.restore.all', 'files.purge', 'files.purge.all', 'files.cleanup', 'files.usage.read'],
  }),
  item({
    path: '/apps/community/reports',
    canonicalPath: '/apps/community/reports',
    legacyAliases: ['/apps/governance/reports'],
    title: '举报处理',
    icon: 'mdi-flag-outline',
    domain: 'content-community',
    section: '社区治理',
    parentRoute: '/apps/community/reports',
    grants: [{ capability: 'reports.read', scope: 'global' }],
    riskLevel: 'sensitive',
    actionCapabilities: ['reports.manage'],
  }),
  item({
    path: '/apps/community/insights',
    canonicalPath: '/apps/community/insights',
    title: '社区洞察',
    icon: 'mdi-chart-line',
    domain: 'content-community',
    section: '社区治理',
    parentRoute: '/apps/community/insights',
    grants: [{ capability: 'community.insights.read', scope: 'global' }],
    actionCapabilities: [],
  }),
]

const userSecurityItems: readonly AdminNavigationItem[] = [
  item({
    path: '/apps/users',
    canonicalPath: '/apps/users',
    title: '用户管理',
    icon: 'mdi-account-multiple-outline',
    domain: 'user-security',
    section: '用户管理',
    parentRoute: '/apps/users',
    grants: [{ capability: 'users.read', scope: 'all' }],
    dataScope: 'global',
    actionCapabilities: ['users.update', 'users.update.all', 'users.delete', 'users.delete.all', 'users.roles.assign', 'users.security.read', 'users.sessions.revoke'],
  }),
  item({
    path: '/apps/security/recovery',
    canonicalPath: '/apps/security/recovery',
    title: '账号恢复记录',
    searchAliases: ['账号恢复', '密码恢复'],
    icon: 'mdi-account-lock-outline',
    domain: 'user-security',
    section: '账号安全',
    parentRoute: '/apps/security/recovery',
    grants: [{ capability: 'users.recovery.read', scope: 'global' }],
    riskLevel: 'high',
    actionCapabilities: ['users.recovery.read'],
  }),
]

const systemConfigItems: readonly AdminNavigationItem[] = [
  item({
    path: '/apps/settings/site',
    canonicalPath: '/apps/settings/site',
    legacyAliases: ['/apps/system'],
    title: '站点设置',
    searchAliases: ['站点信息', '基本设置', '备案信息'],
    icon: 'mdi-tune-variant',
    domain: 'system-config',
    section: '站点设置',
    parentRoute: '/apps/settings/site',
    grants: [{ capability: 'config.read', scope: 'global' }],
    riskLevel: 'sensitive',
    actionCapabilities: ['config.update'],
  }),
  item({
    path: '/apps/settings/community-rules',
    canonicalPath: '/apps/settings/community-rules',
    title: '社区规则',
    searchAliases: ['上传限制', '内容审核', '卡片审核', '评论审核'],
    icon: 'mdi-shield-check-outline',
    domain: 'system-config',
    section: '社区规则',
    parentRoute: '/apps/settings/community-rules',
    grants: [{ capability: 'config.read', scope: 'global' }],
    riskLevel: 'sensitive',
    actionCapabilities: ['config.update'],
  }),
  item({
    path: '/apps/settings/appearance',
    canonicalPath: '/apps/settings/appearance',
    legacyAliases: ['/apps/view'],
    title: '主题外观',
    searchAliases: ['主题列表', '外观'],
    icon: 'mdi-palette-outline',
    domain: 'system-config',
    section: '外观',
    parentRoute: '/apps/settings/appearance',
    grants: [{ capability: 'theme.read', scope: 'global' }],
    actionCapabilities: ['theme.activate'],
  }),
  item({
    path: '/apps/settings/storage',
    canonicalPath: '/apps/settings/storage',
    legacyAliases: ['/apps/storage/config'],
    title: '存储配置',
    icon: 'mdi-database-cog-outline',
    domain: 'system-config',
    section: '存储',
    parentRoute: '/apps/settings/storage',
    grants: [
      { capability: 'storage.read', scope: 'global' },
      { capability: 'config.read', scope: 'global' },
    ],
    riskLevel: 'high',
    actionCapabilities: ['storage.install', 'storage.test', 'config.update'],
  }),
  item({
    path: '/apps/settings/messaging',
    canonicalPath: '/apps/settings/messaging',
    legacyAliases: ['/apps/sender/config'],
    title: '消息渠道',
    icon: 'mdi-email-outline',
    domain: 'system-config',
    section: '消息渠道',
    parentRoute: '/apps/settings/messaging',
    grants: [
      { capability: 'sender.read', scope: 'global' },
      { capability: 'config.read', scope: 'global' },
    ],
    riskLevel: 'high',
    actionCapabilities: ['sender.install', 'sender.test', 'config.update'],
  }),
  item({
    path: '/apps/settings/verification',
    canonicalPath: '/apps/settings/verification',
    legacyAliases: ['/apps/captcha/config'],
    title: '验证配置',
    icon: 'mdi-shield-check-outline',
    domain: 'system-config',
    section: '验证',
    parentRoute: '/apps/settings/verification',
    grants: [
      { capability: 'captcha.read', scope: 'global' },
      { capability: 'config.read', scope: 'global' },
    ],
    riskLevel: 'sensitive',
    actionCapabilities: ['captcha.install', 'config.update'],
  }),
]

const operationsItems: readonly AdminNavigationItem[] = [
  item({
    path: '/apps/operations/runtime',
    canonicalPath: '/apps/operations/runtime',
    legacyAliases: ['/apps/system/runtime'],
    title: '运行状态',
    icon: 'mdi-heart-pulse',
    domain: 'operations-audit',
    section: '运行状态',
    parentRoute: '/apps/operations/runtime',
    grants: [operationsGrant],
    actionCapabilities: ['operations.read'],
  }),
  item({
    path: '/apps/operations/deliveries',
    canonicalPath: '/apps/operations/deliveries',
    title: '通知投递',
    icon: 'mdi-send-check-outline',
    domain: 'operations-audit',
    section: '通知投递',
    parentRoute: '/apps/operations/deliveries',
    grants: [{ capability: 'communications.read', scope: 'global' }],
    riskLevel: 'sensitive',
    actionCapabilities: ['operations.retry'],
  }),
  item({
    path: '/apps/operations/upgrade',
    canonicalPath: '/apps/operations/upgrade',
    legacyAliases: ['/apps/system/upgrade'],
    title: '升级与迁移',
    icon: 'mdi-package-up',
    domain: 'operations-audit',
    section: '升级与迁移',
    parentRoute: '/apps/operations/upgrade',
    grants: [operationsGrant],
    riskLevel: 'high',
    actionCapabilities: ['system.update', 'system.update.contract', 'system.recover', 'operations.migrate'],
  }),
  item({
    path: '/apps/operations/audit',
    canonicalPath: '/apps/operations/audit',
    title: '审计日志',
    icon: 'mdi-clipboard-text-clock-outline',
    domain: 'operations-audit',
    section: '审计日志',
    parentRoute: '/apps/operations/audit',
    grants: [auditGrant],
    actionCapabilities: ['audit.read'],
  }),
]

const accessControlItems: readonly AdminNavigationItem[] = [
  item({
    path: '/apps/access/roles',
    canonicalPath: '/apps/access/roles',
    legacyAliases: ['/apps/roles'],
    title: '角色管理',
    icon: 'mdi-account-key-outline',
    domain: 'access-control',
    section: '角色',
    parentRoute: '/apps/access/roles',
    grants: [{ capability: 'roles.read', scope: 'global' }],
    riskLevel: 'high',
    actionCapabilities: ['roles.create', 'roles.update', 'roles.delete', 'roles.assign'],
  }),
  item({
    path: '/apps/access/permissions',
    canonicalPath: '/apps/access/permissions',
    legacyAliases: ['/apps/permissions'],
    title: '权限目录',
    searchAliases: ['权限管理'],
    icon: 'mdi-key-chain-variant',
    domain: 'access-control',
    section: '权限',
    parentRoute: '/apps/access/permissions',
    grants: [{ capability: 'permissions.read', scope: 'global' }],
    riskLevel: 'high',
    actionCapabilities: ['permissions.read'],
  }),
]

/**
 * 工作台单独直达，其余入口收敛为四个任务组。页面 capability、规范路径与
 * 兼容别名保持不变，分组只改变发现路径，不扩大任何访问范围。
 */
export const adminNavigation: {
  dashboard: AdminNavigationItem
  groups: readonly AdminNavigationGroup[]
} = {
  dashboard,
  groups: [
    {
      key: 'content-community',
      title: '内容与社区',
      icon: 'mdi-forum-outline',
      domain: 'content-community',
      items: contentItems,
    },
    {
      key: 'user-access',
      title: '用户与权限',
      icon: 'mdi-shield-account-outline',
      domain: 'user-security',
      items: [...userSecurityItems, ...accessControlItems],
    },
    {
      key: 'system-config',
      title: '站点设置',
      icon: 'mdi-cog-outline',
      domain: 'system-config',
      items: systemConfigItems,
    },
    {
      key: 'operations-audit',
      title: '系统运维',
      icon: 'mdi-console-line',
      domain: 'operations-audit',
      items: operationsItems,
    },
  ],
}

/** Flat list retained for route permission lookup and existing consumers. */
export const adminNavigationItems: readonly AdminNavigationItem[] = [
  adminNavigation.dashboard,
  ...adminNavigation.groups.flatMap((group) => group.items),
]

const routeGrantOverrides: readonly AdminNavigationItem[] = [
  item({
    path: '/apps/users/:id/security',
    canonicalPath: '/apps/users/:id/security',
    title: '用户安全',
    domain: 'user-security',
    section: '用户详情',
    parentRoute: '/apps/users',
    grants: [{ capability: 'users.security.read', scope: 'global' }],
    riskLevel: 'high',
  }),
  item({
    path: '/apps/settings/appearance/themes/:slug',
    canonicalPath: '/apps/settings/appearance/themes/:slug',
    aliases: ['/apps/settings/appearance/themes'],
    legacyAliases: ['/apps/view/theme-config'],
    title: '主题配置',
    domain: 'system-config',
    section: '外观',
    parentRoute: '/apps/settings/appearance',
    grants: [{ capability: 'theme.read', scope: 'global' }],
    riskLevel: 'sensitive',
    actionCapabilities: ['theme.update'],
  }),
  item({
    path: '/apps/settings/storage/files/:id/usage',
    canonicalPath: '/apps/settings/storage/files/:id/usage',
    legacyAliases: ['/apps/storage/files/:id/usage'],
    title: '文件使用关系',
    domain: 'content-community',
    section: '内容管理',
    parentRoute: '/apps/settings/storage/files',
    grants: [{ capability: 'files.usage.read', scope: 'global' }],
    riskLevel: 'sensitive',
  }),
  item({
    path: '/apps/settings/storage/:slug',
    canonicalPath: '/apps/settings/storage/:slug',
    legacyAliases: ['/apps/storage/config/:slug'],
    title: '存储驱动详情',
    domain: 'system-config',
    section: '存储',
    parentRoute: '/apps/settings/storage',
    grants: [
      { capability: 'storage.read', scope: 'global' },
      { capability: 'config.read', scope: 'global' },
    ],
    riskLevel: 'high',
  }),
  item({
    path: '/apps/settings/messaging/:slug',
    canonicalPath: '/apps/settings/messaging/:slug',
    legacyAliases: ['/apps/sender/config/:slug'],
    title: '消息渠道详情',
    domain: 'system-config',
    section: '消息渠道',
    parentRoute: '/apps/settings/messaging',
    grants: [
      { capability: 'sender.read', scope: 'global' },
      { capability: 'config.read', scope: 'global' },
    ],
    riskLevel: 'high',
  }),
  item({
    path: '/apps/settings/verification/:slug',
    canonicalPath: '/apps/settings/verification/:slug',
    legacyAliases: ['/apps/captcha/config/:slug'],
    title: '验证器详情',
    domain: 'system-config',
    section: '验证',
    parentRoute: '/apps/settings/verification',
    grants: [
      { capability: 'captcha.read', scope: 'global' },
      { capability: 'config.read', scope: 'global' },
    ],
    riskLevel: 'sensitive',
  }),
  item({
    path: '/apps/operations/upgrade',
    canonicalPath: '/apps/operations/upgrade',
    legacyAliases: ['/apps/system/upgrade'],
    title: '系统升级',
    domain: 'operations-audit',
    section: '升级与迁移',
    parentRoute: '/apps/operations/upgrade',
    grants: [operationsGrant],
    riskLevel: 'high',
  }),
]

function pathWithoutQuery(path: string): string {
  return path.split(/[?#]/, 1)[0] || '/'
}

function pathPatterns(entry: AdminNavigationItem): readonly string[] {
  return [
    entry.path,
    entry.canonicalPath,
    ...(entry.aliases ?? []),
    ...(entry.legacyAliases ?? []),
  ].filter((value): value is string => Boolean(value))
}

function matchesPath(pattern: string, path: string): boolean {
  if (pattern === '/apps/updata') return false

  const normalizedPattern = pathWithoutQuery(pattern)
  const normalizedPath = pathWithoutQuery(path)
  const expression = new RegExp(`^${normalizedPattern.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&').replace(/:[^/]+/g, '[^/]+')}(?:/|$)`)
  return expression.test(normalizedPath)
}

function patternSpecificity(pattern: string): number {
  const segments = pathWithoutQuery(pattern).split('/').filter(Boolean)
  const staticSegments = segments.filter(segment => !segment.startsWith(':'))
  const literalLength = staticSegments.reduce((total, segment) => total + segment.length, 0)
  return segments.length * 10_000 + staticSegments.length * 100 + literalLength
}

function matchingPathSpecificity(entry: AdminNavigationItem, path: string): number {
  return pathPatterns(entry).reduce((highest, pattern) => (
    matchesPath(pattern, path)
      ? Math.max(highest, patternSpecificity(pattern))
      : highest
  ), -1)
}

/**
 * 以最长匹配路径确定唯一的当前导航项。
 *
 * 例如 `/apps/settings/appearance/themes/:slug` 同时位于 `/apps/settings/appearance` 的路径前缀下，
 * 但只能激活更具体的“当前主题配置”。同一规则也适用于 canonical path、
 * legacy alias 与资源详情页。
 */
export function activeNavigationItemForPath(
  path: string,
  items: readonly AdminNavigationItem[] = adminNavigationItems,
): AdminNavigationItem | undefined {
  return items.reduce<{
    item?: AdminNavigationItem
    specificity: number
  }>((active, candidate) => {
    const specificity = matchingPathSpecificity(candidate, path)
    return specificity > active.specificity
      ? { item: candidate, specificity }
      : active
  }, { specificity: -1 }).item
}

/**
 * Return grants for a concrete route, including detail routes and canonical or
 * legacy aliases. Unknown routes retain the old empty-grant behavior.
 */
export function requiredGrants(path: string): readonly AdminGrantRequirement[] {
  if (path === '/apps/updata' || path.startsWith('/apps/updata/')) {
    return [operationsGrant]
  }

  const entry = [...routeGrantOverrides, ...adminNavigationItems]
    .map(candidate => ({ candidate, specificity: matchingPathSpecificity(candidate, path) }))
    .filter(match => match.specificity >= 0)
    .sort((a, b) => b.specificity - a.specificity)[0]?.candidate

  return entry?.grants ?? []
}

export function canAccessNavigationItem(
  item: AdminNavigationItem,
  grants: readonly { capability: string; scope: string }[],
): boolean {
  return item.grants.length === 0 || item.grants.every((required) => grants.some((grant) =>
    grant.capability === required.capability && grant.scope === required.scope,
  ))
}
