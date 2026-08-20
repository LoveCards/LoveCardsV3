import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Operations extends BaseResource {
  auditLogs(params?: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }): Promise<ApiTypes.ListResult<ApiTypes.OperationsAuditLog>> {
    return this._get<ApiTypes.ListResult<ApiTypes.OperationsAuditLog>>("/admin/audit-logs", params)
  }
  deliveries(params?: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }): Promise<ApiTypes.ListResult<ApiTypes.OperationsDelivery>> {
    return this._get<ApiTypes.ListResult<ApiTypes.OperationsDelivery>>("/admin/operations/deliveries", params)
  }
  retryDelivery(id: number, data: { reason: string }): Promise<void> {
    return this._post<void>(`/admin/operations/deliveries/${id}/retry`, data)
  }
  migrations(): Promise<ApiTypes.OperationsMigration[]> {
    return this._get<ApiTypes.OperationsMigration[]>("/admin/operations/migrations")
  }
  runMigrations(data?: { reason?: string }): Promise<ApiTypes.OperationsMigration[]> {
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
    return this._get<ApiTypes.OperationsUpgradePlan>(`/admin/operations/upgrades/${planId}`)
  }
  advanceUpgrade(planId: string, data: { reason: string }): Promise<ApiTypes.OperationsUpgradePlan> {
    return this._post<ApiTypes.OperationsUpgradePlan>(`/admin/operations/upgrades/${planId}/advance`, data)
  }
  approveUpgradeContract(planId: string, data: { reason: string }): Promise<ApiTypes.OperationsUpgradePlan> {
    return this._post<ApiTypes.OperationsUpgradePlan>(`/admin/operations/upgrades/${planId}/approve-contract`, data)
  }
  recoverUpgrade(planId: string, data: { reason: string }): Promise<ApiTypes.OperationsUpgradePlan> {
    return this._post<ApiTypes.OperationsUpgradePlan>(`/admin/operations/upgrades/${planId}/recover`, data)
  }
}
