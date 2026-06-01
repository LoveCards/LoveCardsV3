export interface StorageDriver {
  type: string
  name: string
  icon: string
}

export interface StorageMeta {
  type: string
  name: string
  icon: string
  schema: Record<string, any>
  group: string
}

export interface StorageChannel {
  slug: string
  name: string
  icon: string
  fields: any[]
}

export interface ChannelStats {
  [key: string]: any
}
