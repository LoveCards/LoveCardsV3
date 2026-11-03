export interface ContentMention {
  start: number
  end: number
  username: string
  uid: string | null
  state: 'active' | 'unavailable'
  action_url: string | null
}
