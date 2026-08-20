import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Community extends BaseResource {
  insights(): Promise<ApiTypes.OperationsCommunityInsights> {
    return this._get<ApiTypes.OperationsCommunityInsights>("/admin/community/insights")
  }
}
