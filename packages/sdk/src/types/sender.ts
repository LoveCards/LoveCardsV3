export interface SenderType {
  type: string
  channelType: string
  name: string
  icon: string
  supportedTypes: string[]
}

export interface SenderMeta {
  [key: string]: any
  type: string
  name: string
  icon: string
  schema: Record<string, any>
  group: string
}

export interface SenderChannel {
  [key: string]: any
}

export interface SenderTemplate {
  [key: string]: any
}
