import { http, HttpResponse } from 'msw'
import type { Ticket, TicketCategory, TicketListItem } from './types'

export const categoriesFixture: TicketCategory[] = [
  {
    id: 1,
    code: 'computer',
    name: 'Kompyuter',
    name_ru: 'Компьютер',
    label: 'Kompyuter',
    description: null,
    is_active: true,
    sort_order: 10,
  },
  {
    id: 2,
    code: 'printer',
    name: 'Printer',
    name_ru: 'Принтер',
    label: 'Printer',
    description: null,
    is_active: true,
    sort_order: 20,
  },
]

export const categoriesHandler = http.get('*/api/v1/ticket-categories', () =>
  HttpResponse.json({ data: categoriesFixture }),
)

export function makeTicket(overrides: Partial<Ticket> = {}): Ticket {
  const base: TicketListItem = {
    id: 1,
    number: 'HD-2026-000001',
    title: 'Printer ishlamayapti',
    status: 'new',
    priority: 'normal',
    category: { id: 2, name: 'Printer' },
    organization: null,
    requester: { employee_id: 3, user_id: 5, name: 'Karimov Aziz' },
    assignee: null,
    created_at: '2099-11-02T05:00:00Z',
    response_due_at: '2099-11-02T07:00:00Z',
    resolution_due_at: '2099-11-05T05:00:00Z',
    first_response_at: null,
    paused_at: null,
    resolved_at: null,
    closed_at: null,
    response_breached: false,
    resolution_breached: false,
  }
  return {
    ...base,
    description: "Ikkinchi qavatdagi printer qog'ozni tortmayapti.",
    department: null,
    contact_name: 'Karimov Aziz',
    contact_phone: '+998 90 123-45-67',
    contact_email: null,
    location: '214-xona',
    assigned_at: null,
    sla_response_minutes: 120,
    sla_resolution_minutes: 1440,
    paused_minutes: 0,
    rejected_at: null,
    resolution: null,
    reject_reason: null,
    rating: null,
    rating_comment: null,
    reopened_count: 0,
    time_spent_minutes: 0,
    is_requester: true,
    allowed_actions: ['update', 'comment', 'attach'],
    allowed_transitions: [],
    ...overrides,
  }
}
