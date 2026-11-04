import { describe, expect, it } from 'vitest'
import { slaState } from './sla'
import type { TicketListItem } from './types'

function ticket(overrides: Partial<TicketListItem> = {}): TicketListItem {
  return {
    id: 1,
    number: 'HD-2026-000001',
    title: 'Printer',
    status: 'in_progress',
    priority: 'normal',
    category: null,
    organization: null,
    requester: { employee_id: null, user_id: null, name: null },
    assignee: null,
    created_at: '2026-11-02T05:00:00Z',
    response_due_at: '2026-11-02T07:00:00Z',
    resolution_due_at: '2026-11-02T13:00:00Z',
    first_response_at: null,
    paused_at: null,
    resolved_at: null,
    closed_at: null,
    response_breached: false,
    resolution_breached: false,
    ...overrides,
  }
}

describe('slaState', () => {
  const at = (iso: string) => new Date(iso)

  it('is ok early in the window and soon in its last quarter', () => {
    expect(slaState(ticket(), at('2026-11-02T06:00:00Z'))).toBe('ok')
    expect(slaState(ticket(), at('2026-11-02T11:30:00Z'))).toBe('soon')
  })

  it('is breached after the due date or when the server flagged it', () => {
    expect(slaState(ticket(), at('2026-11-02T13:00:01Z'))).toBe('breached')
    expect(slaState(ticket({ resolution_breached: true }), at('2026-11-02T06:00:00Z'))).toBe('breached')
  })

  it('is paused while waiting for the user', () => {
    expect(
      slaState(
        ticket({ status: 'waiting_user', paused_at: '2026-11-02T08:00:00Z' }),
        at('2026-11-03T00:00:00Z'),
      ),
    ).toBe('paused')
  })

  it('finished tickets are done, or breached when resolved late', () => {
    expect(slaState(ticket({ status: 'closed', resolved_at: '2026-11-02T12:00:00Z' }))).toBe('done')
    expect(slaState(ticket({ status: 'resolved', resolved_at: '2026-11-02T14:00:00Z' }))).toBe('breached')
    expect(slaState(ticket({ status: 'rejected' }))).toBe('done')
  })
})
