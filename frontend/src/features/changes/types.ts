interface Ref {
  id: number
  name: string
}

export const CHANGE_TYPES = ['standard', 'normal', 'emergency'] as const
export type ChangeType = (typeof CHANGE_TYPES)[number]
export const CHANGE_CATEGORIES = ['network', 'server', 'configuration', 'software', 'other'] as const
export type ChangeCategory = (typeof CHANGE_CATEGORIES)[number]
export const CHANGE_RISKS = ['low', 'medium', 'high'] as const
export type ChangeRisk = (typeof CHANGE_RISKS)[number]
export const CHANGE_STATUSES = [
  'draft',
  'pending_approval',
  'approved',
  'rejected',
  'in_progress',
  'implemented',
  'failed',
  'cancelled',
] as const
export type ChangeStatus = (typeof CHANGE_STATUSES)[number]

export interface ChangeRow {
  id: number
  number: string
  title: string
  organization: Ref
  type: ChangeType
  category: ChangeCategory
  risk: ChangeRisk
  status: ChangeStatus
  planned_start: string | null
  planned_end: string | null
  implementer: Ref | null
  creator: Ref | null
  assets_count: number
}

export interface ChangeRef {
  id: number
  number: string
  title: string
  status: ChangeStatus
  planned_start: string | null
}

export interface Change extends ChangeRow {
  description: string | null
  impact: string | null
  plan: string | null
  backout_plan: string | null
  actual_start: string | null
  actual_end: string | null
  submitted_at: string | null
  decided_by: Ref | null
  decided_at: string | null
  decision_comment: string | null
  result: string | null
  problem: { id: number; number: string; title: string } | null
  assets: { id: number; inventory_number: string; name: string; model: string | null }[]
  conflicts: ChangeRef[]
  history: {
    id: number
    event: string
    payload: Record<string, unknown> | null
    comment: string | null
    user: Ref | null
    created_at: string
  }[]
  can: {
    edit: boolean
    submit: boolean
    decide: boolean
    start: boolean
    complete: boolean
    cancel: boolean
    revise: boolean
    comment: boolean
  }
}

export type ChangeAction = 'submit' | 'approve' | 'reject' | 'start' | 'complete' | 'cancel' | 'revise'
