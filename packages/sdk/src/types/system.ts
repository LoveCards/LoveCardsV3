export interface SystemVersionInfo {
  app_name: string
  homepage: string
  version: string
  build: number
  github: string
  qgroup: string
}

export interface SystemUpdateInfo {
  ver: SystemVersionInfo
  latest: Record<string, any>
  verlog: string
}
