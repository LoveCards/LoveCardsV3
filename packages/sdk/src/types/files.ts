export interface FileItem {
  id: number
  user_id: number
  name: string
  path: string
  url: string
  mime: string
  size: number
  channel: string
  scene: string
  ref_type: string
  ref_id: number
  is_public: number
  status: number
  created_at: string
}

export interface DirectUploadResult {
  upload_url: string
  file_id: number
  credentials: Record<string, any>
}
