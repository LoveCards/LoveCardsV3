export interface AuthPolicy {
  registration: {
    enabled: boolean
    account_types: Array<'email' | 'phone'>
    email_verification_required: boolean
    email_verification_available: boolean
    sms_verification_required: boolean
    sms_verification_available: boolean
    resend_after: number
  }
  guest: { enabled: boolean }
  human_verification: { enabled: boolean; driver: string; available: boolean }
}

export interface SessionState {
  uid: string
  visitor: boolean
  roles: number[]
  capabilities: string[]
  grants: import('./permissions').Grant[]
}
