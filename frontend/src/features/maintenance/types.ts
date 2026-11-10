interface Ref {
  id: number
  name: string
}

export const MAINTENANCE_UNITS = ['week', 'month'] as const
export type MaintenanceUnit = (typeof MAINTENANCE_UNITS)[number]
export const TASK_STATUSES = ['planned', 'done', 'skipped', 'cancelled'] as const
export type TaskStatus = (typeof TASK_STATUSES)[number]
export type TaskResult = 'ok' | 'issues'

export interface PlanRow {
  id: number
  title: string
  organization: Ref
  interval_count: number
  interval_unit: MaintenanceUnit
  start_on: string
  assignee: Ref | null
  lead_days: number
  is_active: boolean
  assets_count: number
  next_due: string | null
  overdue_count: number
}

export interface Plan extends PlanRow {
  description: string | null
  checklist: string[]
  assets: { id: number; inventory_number: string; name: string; category: string | null; status: string }[]
}

export interface WorkItem {
  id: number
  plan: { id: number; title: string; checklist: string[]; assignee: Ref | null }
  asset: { id: number; inventory_number: string; name: string; category: string | null }
  organization: Ref
  due_on: string
  status: TaskStatus
  overdue: boolean
  completed_on: string | null
  completed_by: Ref | null
  result: TaskResult | null
  checklist: { step: string; done: boolean; note: string | null }[] | null
  note: string | null
  can: { perform: boolean }
}
