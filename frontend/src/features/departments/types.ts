import type { Ref } from '@/api/types'

export interface Department {
  id: number
  organization?: Ref | null
  parent?: Ref | null
  name: string
  code: string | null
  phone: string | null
  is_active: boolean
  sort_order: number
  employees_count?: number
  created_at: string | null
  updated_at: string | null
  can: { update: boolean; delete: boolean }
}
