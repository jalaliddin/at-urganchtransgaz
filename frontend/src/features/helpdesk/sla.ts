import type { TicketListItem } from './types'
import { OPEN_STATUSES } from './types'

export type SlaState = 'ok' | 'soon' | 'breached' | 'paused' | 'done'

/** Remaining share of the resolution window below which a ticket counts as "soon". */
const SOON_SHARE = 0.25

/**
 * Traffic-light state of a ticket's resolution SLA for list/detail indicators.
 * The server is the source of truth for breaches (flags set by the scheduler);
 * between scheduler runs a passed due date is also shown as breached.
 */
export function slaState(ticket: TicketListItem, now: Date = new Date()): SlaState {
  if (!OPEN_STATUSES.includes(ticket.status)) {
    if (ticket.resolved_at === null) return 'done'
    return new Date(ticket.resolved_at) > new Date(ticket.resolution_due_at) ? 'breached' : 'done'
  }
  if (ticket.paused_at !== null) return 'paused'
  const due = new Date(ticket.resolution_due_at).getTime()
  if (ticket.resolution_breached || due <= now.getTime()) return 'breached'
  const created = new Date(ticket.created_at).getTime()
  const total = Math.max(due - created, 1)
  return (due - now.getTime()) / total < SOON_SHARE ? 'soon' : 'ok'
}
