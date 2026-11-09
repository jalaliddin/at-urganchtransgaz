import type { Ref } from '@/features/services/types'

export const SIM_STATUSES = ['in_stock', 'active', 'blocked', 'returned', 'cancelled'] as const
export type SimStatus = (typeof SIM_STATUSES)[number]
export type SimAction = 'assign' | 'return' | 'block' | 'unblock' | 'transfer' | 'cancel' | 'replace'

export interface SimCard {
  id: number
  phone_number: string
  provider: Ref
  organization: Ref
  department: Ref | null
  employee: { id: number; full_name: string } | null
  status: SimStatus
  previous_status: SimStatus | null
  tariff: string | null
  account_number: string | null
  monthly_fee: string | null
  iccid_masked: string | null
  has_iccid: boolean
  allocated_at: string | null
  returned_at: string | null
  note: string | null
}

export interface SimAssignment {
  id: number
  employee: { id: number; full_name: string } | null
  department: Ref | null
  assigned_at: string
  returned_at: string | null
  note: string | null
}

export interface SimHistory {
  id: number
  event: string
  payload: Record<string, string | number | null>
  user: Ref | null
  requisition_id: number | null
  created_at: string | null
}

export interface SimDetail extends SimCard {
  assignments: SimAssignment[]
  history: SimHistory[]
}

export interface SimReportRow {
  organization: Ref
  provider: Ref
  total: number
  in_stock: number
  active: number
  blocked: number
  returned: number
  monthly_fee: string
}

export interface SimReport {
  rows: SimReportRow[]
  total: Omit<SimReportRow, 'organization' | 'provider'>
}

/** Which actions a status allows (the API checks the same rules). */
export const SIM_ACTIONS: Record<SimAction, (s: SimCard) => boolean> = {
  assign: (s) => s.status === 'in_stock' || s.status === 'returned',
  return: (s) => s.status === 'active' || (s.status === 'blocked' && s.employee !== null),
  block: (s) => s.status === 'in_stock' || s.status === 'active' || s.status === 'returned',
  unblock: (s) => s.status === 'blocked',
  transfer: (s) => s.status === 'in_stock' || s.status === 'returned',
  cancel: (s) => s.status !== 'cancelled',
  replace: (s) => s.status !== 'cancelled',
}

/** 998901234567 → +998 90 123 45 67 */
export function formatPhone(value: string): string {
  const m = /^998(\d{2})(\d{3})(\d{2})(\d{2})$/.exec(value)
  return m ? `+998 ${m[1]} ${m[2]} ${m[3]} ${m[4]}` : value
}
