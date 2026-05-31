export interface Role {
  id: number
  name: string
  slug: string
  pid: number
  status: number
  created_at: string
  updated_at: string
}

export interface AssignPermissionsParams {
  permission_ids: number[]
}
