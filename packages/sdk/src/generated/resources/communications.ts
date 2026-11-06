import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Communications extends BaseResource {
  policies(): Promise<ApiTypes.NotificationPolicyCatalog> {
    return this._get<ApiTypes.NotificationPolicyCatalog>("/admin/communications/policies")
  }
  applyPolicies(data: ApiTypes.NotificationPolicyUpdate): Promise<ApiTypes.NotificationPolicyApplyResult> {
    return this._put<ApiTypes.NotificationPolicyApplyResult>("/admin/communications/policies", data)
  }
}
