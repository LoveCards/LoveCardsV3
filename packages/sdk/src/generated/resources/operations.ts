import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Operations extends BaseResource {
  auditLogs(params?: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }): Promise<ApiTypes.ListResult<ApiTypes.OperationsAuditLog>> {
    return this._get<ApiTypes.ListResult<ApiTypes.OperationsAuditLog>>("/admin/audit-logs", params)
  }
  auditLog(id: number): Promise<ApiTypes.OperationsAuditLog> {
    return this._get<ApiTypes.OperationsAuditLog>(`/admin/audit-logs/${id}`)
  }
  deliveries(params?: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }): Promise<ApiTypes.ListResult<ApiTypes.OperationsDelivery>> {
    return this._get<ApiTypes.ListResult<ApiTypes.OperationsDelivery>>("/admin/operations/deliveries", params)
  }
  delivery(id: number): Promise<ApiTypes.OperationsDelivery> {
    return this._get<ApiTypes.OperationsDelivery>(`/admin/operations/deliveries/${id}`)
  }
  retryDelivery(id: number): Promise<void> {
    return this._post<void>(`/admin/operations/deliveries/${id}/retry`)
  }
  migrations(): Promise<ApiTypes.OperationsMigration[]> {
    return this._get<ApiTypes.OperationsMigration[]>("/admin/operations/migrations")
  }
  runMigrations(data?: Record<string, unknown>): Promise<ApiTypes.OperationsMigration[]> {
    return this._post<ApiTypes.OperationsMigration[]>("/admin/operations/migrations/run", data)
  }
  notificationStats(): Promise<ApiTypes.OperationsNotificationStats> {
    return this._get<ApiTypes.OperationsNotificationStats>("/admin/operations/notifications")
  }
  overview(): Promise<ApiTypes.OperationsOverview> {
    return this._get<ApiTypes.OperationsOverview>("/admin/operations/overview")
  }
  runtime(): Promise<ApiTypes.OperationsRuntimeStatus> {
    return this._get<ApiTypes.OperationsRuntimeStatus>("/admin/operations/runtime")
  }
  upgrades(params?: { limit?: number }): Promise<ApiTypes.OperationsUpgradePlan[]> {
    return this._get<ApiTypes.OperationsUpgradePlan[]>("/admin/operations/upgrades", params)
  }
  createUpgrade(formData: FormData): Promise<ApiTypes.OperationsUpgradePlan> {
    return this._post<ApiTypes.OperationsUpgradePlan>("/admin/operations/upgrades", formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  }
  upgrade(planId: string): Promise<ApiTypes.OperationsUpgradePlan> {
    return this._get<ApiTypes.OperationsUpgradePlan>(`/admin/operations/upgrades/${encodeURIComponent(String(planId))}`)
  }
  advanceUpgrade(planId: string): Promise<ApiTypes.OperationsUpgradePlan> {
    return this._post<ApiTypes.OperationsUpgradePlan>(`/admin/operations/upgrades/${encodeURIComponent(String(planId))}/advance`)
  }
  approveUpgradeContract(planId: string): Promise<ApiTypes.OperationsUpgradePlan> {
    return this._post<ApiTypes.OperationsUpgradePlan>(`/admin/operations/upgrades/${encodeURIComponent(String(planId))}/approve-contract`)
  }
  recoverUpgrade(planId: string): Promise<ApiTypes.OperationsUpgradePlan> {
    return this._post<ApiTypes.OperationsUpgradePlan>(`/admin/operations/upgrades/${encodeURIComponent(String(planId))}/recover`)
  }
}
