import { BaseResource } from './base'
import type { DashboardData } from '../types/dashboard'

export class Dashboard extends BaseResource {
  index(): Promise<DashboardData> {
    return this._get<DashboardData>('/dashboard')
  }
}
