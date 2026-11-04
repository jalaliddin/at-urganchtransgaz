import type { Ref } from '@/api/types'

export const TICKET_STATUSES = [
  'new',
  'in_review',
  'assigned',
  'in_progress',
  'waiting_user',
  'resolved',
  'closed',
  'rejected',
] as const
export type TicketStatus = (typeof TICKET_STATUSES)[number]

export const TICKET_PRIORITIES = ['low', 'normal', 'high', 'critical'] as const
export type TicketPriority = (typeof TICKET_PRIORITIES)[number]

export const OPEN_STATUSES: TicketStatus[] = ['new', 'in_review', 'assigned', 'in_progress', 'waiting_user']

export type TicketAction =
  'update' | 'comment' | 'comment_internal' | 'assign' | 'take' | 'log_time' | 'link' | 'attach' | 'rate'

export interface TicketListItem {
  id: number
  number: string
  title: string
  status: TicketStatus
  priority: TicketPriority
  category: Ref | null
  organization: Ref | null
  requester: { employee_id: number | null; user_id: number | null; name: string | null }
  assignee: Ref | null
  created_at: string
  response_due_at: string
  resolution_due_at: string
  first_response_at: string | null
  paused_at: string | null
  resolved_at: string | null
  closed_at: string | null
  response_breached: boolean
  resolution_breached: boolean
}

export interface Ticket extends TicketListItem {
  description: string
  department: Ref | null
  contact_name: string | null
  contact_phone: string | null
  contact_email: string | null
  location: string | null
  assigned_at: string | null
  sla_response_minutes: number
  sla_resolution_minutes: number
  paused_minutes: number
  rejected_at: string | null
  resolution: string | null
  reject_reason: string | null
  rating: number | null
  rating_comment: string | null
  reopened_count: number
  time_spent_minutes: number
  is_requester: boolean
  allowed_actions: TicketAction[]
  allowed_transitions: TicketStatus[]
}

export interface FileRef {
  uuid: string
  name: string
  mime: string
  size: number
  is_image?: boolean
}

export interface TicketAttachment {
  id: number
  comment_id: number | null
  file: FileRef
  user: Ref | null
  created_at: string
}

export interface TicketComment {
  id: number
  body: string
  is_internal: boolean
  user: Ref | null
  attachments: TicketAttachment[]
  created_at: string
}

export interface TicketHistoryEntry {
  id: number
  action: string
  user: Ref | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  note: string | null
  is_internal: boolean
  created_at: string
}

export interface TicketLink {
  id: number
  type: 'relates' | 'duplicates'
  ticket: { id: number; number: string; title: string; status: TicketStatus }
}

export interface TicketCategory {
  id: number
  code: string | null
  name: string
  name_ru: string | null
  label: string
  description: string | null
  is_active: boolean
  sort_order: number
}

export interface SlaPolicy {
  id: number
  priority: TicketPriority
  category: Ref | null
  response_minutes: number
  resolution_minutes: number
}

export interface CalendarSettings {
  work_days: number[]
  work_start: string
  work_end: string
  lunch_start: string
  lunch_end: string
  holidays: string[]
  auto_close_days: number
  reopen_days: number
  due_soon_minutes: number
  timezone: string
}

export interface TicketStats {
  total: number
  open: number
  overdue: number
  by_status: Record<TicketStatus, number>
  by_priority: Record<TicketPriority, number>
  by_category: { id: number; name: string; total: number }[]
  by_organization: { id: number; name: string; total: number; open: number; overdue: number }[]
  sla: { response_met: number; response_total: number; resolution_met: number; resolution_total: number }
  avg_resolution_minutes: number | null
}
