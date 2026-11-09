export interface Ref {
  id: number
  name: string
}

/** Someone who can open a board; only those who can work on tasks may be responsible. */
export interface Member extends Ref {
  can_be_assignee: boolean
}

export type ColumnCategory = 'open' | 'done' | 'cancelled'
export const PRIORITIES = ['low', 'normal', 'high', 'urgent'] as const
export type Priority = (typeof PRIORITIES)[number]
export const LABEL_COLORS = [
  'gray',
  'red',
  'orange',
  'yellow',
  'green',
  'teal',
  'blue',
  'indigo',
  'violet',
  'pink',
] as const

export interface Board {
  id: number
  name: string
  description: string | null
  is_archived: boolean
  organization: Ref
  open_tasks: number | null
  overdue_tasks: number | null
}

export interface Column {
  id: number
  name: string
  category: ColumnCategory
  position: number
  wip_limit: number | null
}

export interface Label {
  id: number
  name: string
  color: string
}

export interface BoardDetail extends Board {
  columns: Column[]
  labels: Label[]
}

export interface Card {
  id: number
  number: string
  title: string
  board_id: number
  column_id: number
  position: number
  priority: Priority
  project: string | null
  organization: Ref
  assignee: Ref | null
  start_date: string | null
  due_date: string | null
  completed_at: string | null
  is_overdue: boolean
  labels: Label[]
  checklist_total: number | null
  checklist_done: number | null
  comments_count: number | null
}

export interface BoardCard extends Card {
  can_move: boolean
}

export interface BoardColumn extends Column {
  cards: BoardCard[]
  hidden_done: number
}

export interface TaskDetail extends Card {
  description: string | null
  board: { id: number; name: string; is_archived: boolean }
  column: Column
  creator: Ref | null
  ticket: { id: number; number: string; title: string } | null
  watchers: Ref[]
  checklist: { id: number; title: string; is_done: boolean; position: number; done_at: string | null }[]
  comments: { id: number; body: string; user: Ref | null; created_at: string }[]
  attachments: {
    id: number
    file: { uuid: string; name: string; mime: string; size: number }
    user: Ref | null
    created_at: string
  }[]
  history: {
    id: number
    event: string
    payload: Record<string, unknown>
    user: Ref | null
    created_at: string | null
  }[]
  created_at: string
  can: { edit: boolean; comment: boolean; delete: boolean }
}

export interface Recurrence {
  id: number
  board_id: number
  title: string
  description: string | null
  column: Column | null
  organization_id: number
  assignee: Ref | null
  priority: Priority
  label_ids: number[]
  frequency: 'daily' | 'weekly' | 'monthly'
  interval: number
  weekday: number | null
  month_day: number | null
  due_in_days: number | null
  next_run_on: string
  is_active: boolean
}

export interface WorkloadCounts {
  open: number
  overdue: number
  due_week: number
  done_30: number
}

export interface Workload {
  by_assignee: ({ user: Ref | null } & WorkloadCounts)[]
  by_organization: ({ organization: Ref } & WorkloadCounts)[]
}
