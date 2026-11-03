import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class System extends BaseResource {
  update(): Promise<ApiTypes.SystemUpdateInfo> {
    return this._get<ApiTypes.SystemUpdateInfo>("/admin/system/update")
  }
}
