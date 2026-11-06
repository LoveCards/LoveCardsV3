import { BaseResource } from '../../resources/base'
import type * as ApiTypes from '../../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export class Communication extends BaseResource {
  settings(): Promise<ApiTypes.CommunicationSettingsSnapshot> {
    return this._get<ApiTypes.CommunicationSettingsSnapshot>("/me/communication/settings")
  }
}
