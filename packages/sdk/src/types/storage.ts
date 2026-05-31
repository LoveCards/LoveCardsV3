export interface StorageDriver {
  type: string
  name: string
  description: string
}

export interface StorageChannel {
  channel: string
  driver: string
  config: Record<string, any>
  enabled: boolean
}

export interface ChannelStats {
  channel: string
  files_count: number
  total_size: number
}
