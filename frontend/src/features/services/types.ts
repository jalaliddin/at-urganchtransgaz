export interface Ref {
  id: number
  name: string
}

export interface Provider {
  id: number
  name: string
  tin: string | null
  phone: string | null
  email: string | null
  address: string | null
  is_mobile_operator: boolean
  is_active: boolean
  note: string | null
}

export type ContractStatus = 'active' | 'expired' | 'terminated'

export interface Contract {
  id: number
  number: string
  subject: string | null
  provider: Ref
  organization: Ref
  signed_at: string | null
  valid_from: string | null
  valid_to: string | null
  terminated_at: string | null
  amount: string | null
  note: string | null
  status: ContractStatus
  services_count: number | null
}

export const SERVICE_TYPES = ['internet', 'telephony', 'mobile', 'data_center', 'software', 'other'] as const
export type ServiceType = (typeof SERVICE_TYPES)[number]
export const BILLING_CYCLES = ['monthly', 'quarterly', 'yearly'] as const
export type BillingCycle = (typeof BILLING_CYCLES)[number]

export interface ItService {
  id: number
  type: ServiceType
  name: string
  provider: Ref
  organization: Ref
  contract: { id: number; number: string } | null
  address: string | null
  identifier: string | null
  tariff: string | null
  fee: string
  billing_cycle: BillingCycle
  due_day: number
  started_at: string | null
  ended_at: string | null
  is_active: boolean
  note: string | null
}

export const INVOICE_STATUSES = [
  'planned',
  'awaiting_invoice',
  'due',
  'partially_paid',
  'paid',
  'overdue',
] as const
export type InvoiceStatus = (typeof INVOICE_STATUSES)[number]

export interface StoredFileRef {
  uuid: string
  name: string
  mime: string
  size: number
}

export interface Invoice {
  id: number
  kind: 'regular' | 'extra'
  status: InvoiceStatus
  service: { id: number; name: string; type: ServiceType; provider: Ref }
  organization: Ref
  period_start: string
  period_end: string
  planned_amount: string
  invoice_number: string | null
  invoice_date: string | null
  amount: string | null
  due_date: string
  paid_amount: string
  approved_by: Ref | null
  approved_at: string | null
  file: StoredFileRef | null
  description: string | null
  note: string | null
}

export interface Payment {
  id: number
  amount: string
  paid_at: string
  document_number: string | null
  note: string | null
  file: StoredFileRef | null
  user: Ref | null
}

export interface InvoiceDetail extends Invoice {
  payments: Payment[]
}

export interface InvoiceSummary {
  planned: string
  invoiced: string
  paid: string
}

export type CostGroup = 'organization' | 'provider' | 'type'

export interface CostCell {
  planned: string
  invoiced: string
  paid: string
}

export interface CostReport {
  year: number
  rows: { key: { id: number | string; name: string }; months: CostCell[]; total: CostCell }[]
  total: CostCell
}
