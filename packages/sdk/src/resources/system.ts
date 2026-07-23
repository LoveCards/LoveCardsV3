import { BaseResource } from './base'
import type { SystemUpdateInfo } from '../types/system'

export class System extends BaseResource {
  update(): Promise<SystemUpdateInfo> {
    return this._get<SystemUpdateInfo>('/system/update')
  }
}
