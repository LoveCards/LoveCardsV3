export interface CapabilityItem {
  capability: string
  scope: AuthorizationScope
  description: string
}

export type AuthorizationScope = 'own' | 'all' | 'global'

export interface Grant {
  capability: string
  scope: AuthorizationScope
}
