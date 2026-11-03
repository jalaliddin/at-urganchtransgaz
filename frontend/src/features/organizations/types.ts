import type { Ref } from '@/api/types'

export type OrganizationType = 'head_office' | 'subsidiary' | 'branch' | 'other'

export interface Organization {
  id: number
  parent_id: number | null
  parent?: Ref | null
  depth: number
  type: OrganizationType
  type_label: string
  name: string
  short_name: string | null
  code: string
  tin: string | null
  address: string | null
  phone: string | null
  email: string | null
  head_name: string | null
  is_active: boolean
  sort_order: number
  children_count?: number
  created_at: string | null
  updated_at: string | null
  can: { update: boolean; delete: boolean; manage_it_staff: boolean }
}

export interface OrganizationTreeNode {
  id: number
  name: string
  short_name: string | null
  type: OrganizationType
  is_active: boolean
  children: OrganizationTreeNode[]
}

export interface ItResponsible {
  id: number
  organization_id: number
  employee: {
    id: number
    full_name: string
    position: string | null
    phone: string | null
    email: string | null
    organization: Ref | null
  } | null
  is_primary: boolean
  assigned_at: string | null
  notes: string | null
}
