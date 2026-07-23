export interface DashboardData {
  count: {
    cards: number
    comments: number
    good: number
  }
  chart: ChartDataset[]
  ver: VersionInfo
  notice: any[]
}

export interface ChartDataset {
  label: string
  data: {
    x: string[]
    y: number[]
  }
}

export interface VersionInfo {
  app_name: string
  homepage: string
  version: string
  build: number
  github: string
  qgroup: string
}
