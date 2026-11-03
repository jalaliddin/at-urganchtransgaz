import type { Ref } from '@/api/types'

export type EmployeeStatus = 'active' | 'on_leave' | 'business_trip' | 'dismissed'

export interface Employee {
  id: number
  organization?: Ref | null
  department?: Ref | null
  last_name: string
  first_name: string
  middle_name: string | null
  full_name: string
  position: string | null
  personnel_number: string | null
  phone: string | null
  internal_phone: string | null
  email: string | null
  status: EmployeeStatus
  status_label: string
  hired_at: string | null
  dismissed_at: string | null
  notes: string | null
  user?: { id: number; username: string; is_active: boolean } | null
  created_at: string | null
  updated_at: string | null
  can: { update: boolean; delete: boolean }
}
