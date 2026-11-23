import type { JsonObject } from './json'

export interface LCFile {
  url?: string
  id: number
  hash: string
  channel_slug: string
  user_id: number | null
  is_public: number
  scene: string | null
  ref_type: string | null
  ref_id: number | null
  original_name: string | null
  file_path: string
  file_url: string
  file_size: number
  file_ext: string
  mime_type: string | null
  metadata: JsonObject | null
  status: number
  upload_status: number
  expire_at: string | null
  created_at: string
  updated_at: string
}

export interface UploadResult {
  id: number
  url: string
  path: string
  size: number
  mime_type: string
  original_name: string
  channel_slug: string
}

export interface DirectUploadResult {
  record_id: number
  upload_url: string
  method: string
  headers: Record<string, string>
  form_data: Record<string, string>
  expire: number
}

export interface UploadScenePolicy {
  available: boolean
  extensions: string[]
  mime_patterns: string[]
  site_max_bytes: number
  storage_max_bytes: number | null
  max_bytes: number
}

export interface UploadPolicy {
  scenes: Record<'avatar' | 'profile_cover' | 'card_image' | 'card_video' | 'comment' | 'direct', UploadScenePolicy>
  card_media_limit: number
}
