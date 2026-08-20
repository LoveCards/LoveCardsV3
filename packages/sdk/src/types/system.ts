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
  latest: {
    tag_name?: string
    name?: string
    url?: string
    [key: string]: unknown
  }
  verlog: string
}
