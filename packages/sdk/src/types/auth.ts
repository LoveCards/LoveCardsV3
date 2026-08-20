export interface AuthPolicy {
  registration: {
    enabled: boolean
    account_types: Array<'email' | 'phone' | 'username'>
    email_verification_required: boolean
    email_verification_available: boolean
  }
  guest: { enabled: boolean }
  human_verification: { enabled: boolean; driver: string }
}

export interface SessionState {
  uid: number
  visitor: boolean
  roles: number[]
  capabilities: string[]
  grants: import('./permissions').Grant[]
}
