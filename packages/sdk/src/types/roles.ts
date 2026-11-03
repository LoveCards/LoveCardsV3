export interface Role {
  id: number
  name: string
  slug: string
  description: string | null
  is_system: number
  created_at: string | null
  updated_at: string | null
}

export interface CreateRoleParams {
  name: string
  slug: string
  description?: string
}

export interface UpdateRoleParams {
  name?: string
  slug?: string
  description?: string
}

import type { Grant } from './permissions'

export interface AssignGrantsParams {
  grants: Grant[]
}

export interface ReseedResult {
  total: number
  guest: number
  user: number
  admin: number
  root: number
}
