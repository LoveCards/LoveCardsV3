export interface SenderChannel {
  channel: string
  driver: string
  name: string
  config: Record<string, any>
  enabled: boolean
}

export interface SenderTemplate {
  id: number
  name: string
  channel: string
  subject: string
  content: string
}

export interface SenderMeta {
  type: string
  config_schema: Record<string, any>
}
