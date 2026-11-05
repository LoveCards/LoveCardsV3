import type { JsonObject } from './json'

export interface OperationsFacility {
  name: string
  desired_mode: string
  configured: boolean
  supported: boolean
  reachable: boolean
  healthy: boolean
  enabled: boolean
  effective_provider?: string | null
  fallback_provider?: string | null
  reason_code: string
  checked_at: string
  ready: boolean
}

export interface OperationsOverview {
  users: number
  cards: number
  comments: number
  reports_open: number
  notifications_unread: number
  outbox_pending: number
  files_pending: number
  runtime: OperationsFacility[]
}

export interface OperationsMigrationProgress {
  id: string
  checksum: string
  phase: string
  status: string
  cursor?: string | null
  revision: number
  attempts: number
  failure_code?: string | null
}

export interface OperationsMigration {
  migration?: { id?: string; checksum?: string; description?: string; has_contract?: boolean }
  state?: string
  progress?: OperationsMigrationProgress | null
  id?: string
  phase?: string
  status?: string
}

export interface OperationsWorker {
  worker_id: string
  worker_type: string
  state: string
  release_id: string
  schema_version: string
  handler_catalog_hash: string
  jobs_processed: number
  heartbeat_at: string
  created_at: string
  updated_at: string
  online: boolean
}

export interface OperationsRuntimeStatus {
  facilities: OperationsFacility[]
  workers: OperationsWorker[]
  eventing: {
    pending: number
    processing: number
    dead: number
    oldest_pending_at?: string | null
    oldest_pending_age_seconds?: number | null
    expired_processing?: number
    queues?: Array<{ queue_name: string; execution_mode: string; pending: number; processing: number; dead: number }>
    last_updated?: string | null
  }
}

export interface OperationsDelivery {
  id: number
  event_id: number
  event_type: string
  handler_key: string
  queue_name: string
  execution_mode: string
  status: string
  attempt_count: number
  available_at: string
  last_error?: string | null
  correlation_id?: string | null
  created_at: string
  updated_at: string
}

export interface OperationsAuditLog {
  id: number
  actor_id?: number | null
  action: string
  resource_type?: string | null
  resource_id?: string | null
  request_id?: string | null
  correlation_id?: string | null
  payload: JsonObject
  created_at: string
}

export interface OperationsNotificationStats {
  total: number
  unread: number
  recipients: number
  types: Array<{ type: string; count: number }>
}

export interface OperationsCommunityInsights {
  drafts: { total: number; active: number }
  library: { total: number; users: number }
  history: { total: number; users: number }
  privacy: { mode: 'aggregate_only'; private_content_exposed: false }
}

export interface OperationsUpgradeStep {
  name: string
  state: string
  attempts: number
  cursor: string | null
  failure_code: string | null
}

export interface OperationsUpgradePlan {
  plan_id: string
  release_id: string
  previous_release_id: string | null
  revision: number
  status: string
  current_step: string | null
  contract_approved: boolean
  created_by: number | null
  package: {
    checksum: string | null
    size_bytes: number | null
  }
  milestones: Record<string, unknown>
  steps: OperationsUpgradeStep[]
}

export interface AdminReportEvent {
  id: number
  report_id: number
  actor_id?: number | null
  event_type: string
  payload: JsonObject
  created_at: string
}

/** 举报提交时刻的目标证据快照（内容截断存储，哈希对应全文）。 */
export interface AdminReportEvidence {
  type: string
  captured: {
    id?: number
    uid?: string
    number?: string
    username?: string
    user_id?: number
    pid?: number
    conversation_id?: number
    sender_id?: number
    status?: number
    upload_status?: number | string
    content?: string | null
    content_sha256?: string
    body?: string | null
    body_sha256?: string
    created_at?: string | null
    hash?: string
    mime_type?: string
    file_url?: string | null
  }
}

export interface AdminReport {
  id: number
  reporter_id: number
  target_type: string
  target_id: string
  reason: string
  details?: string | null
  evidence_snapshot?: AdminReportEvidence | null
  status: string
  assignee_id?: number | null
  resolution?: string | null
  created_at: string
  updated_at: string
  resolved_at?: string | null
  events?: AdminReportEvent[]
}

export interface UserSecurityOverview {
  user: {
    id: number
    number: string
    username: string
    email: string
    phone: string
    status: number
    security_version: number
    credentials_changed_at?: string | null
    created_at: string
    updated_at: string
  }
  privacy?: { profile_visibility: string; messages_from: string; show_relations: number; updated_at: string } | null
  sessions: { active: number; total: number }
  recovery_challenges: number
  session_mutation_allowed: boolean
}

export interface UserSession {
  id: number
  user_id: number
  token_expires_at: string
  renewable_until: string
  security_version: number
  revoked_at?: string | null
  revoked_reason?: string | null
  rotated_to_session_id?: number | null
  created_at: string
  active: boolean
}

export interface RecoveryChallenge {
  id: number
  user_id?: number | null
  purpose: string
  expires_at: string
  consumed_at?: string | null
  attempts: number
  created_at: string
  state: 'active' | 'expired' | 'consumed'
}

export interface FileUsage {
  file: JsonObject
  references: Array<{ resource_type?: string | null; resource_id?: string | null }>
  orphaned: boolean
}
