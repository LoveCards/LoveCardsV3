import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Dashboard extends BaseResource {
  index(): Promise<ApiTypes.DashboardData> {
    return this._get<ApiTypes.DashboardData>("/dashboard")
  }
}
