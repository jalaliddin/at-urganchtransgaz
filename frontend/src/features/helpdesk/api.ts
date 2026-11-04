import { api } from '@/api/client'
import type { MessageResponse, Paginated, Ref, Resource } from '@/api/types'
import type {
  CalendarSettings,
  SlaPolicy,
  Ticket,
  TicketAttachment,
  TicketCategory,
  TicketComment,
  TicketHistoryEntry,
  TicketLink,
  TicketListItem,
  TicketPriority,
  TicketStats,
  TicketStatus,
} from './types'

export interface TicketInput {
  title: string
  description: string
  category_id: number
  priority: TicketPriority
  organization_id?: number | null
  department_id?: number | null
  requester_employee_id?: number | null
  contact_name?: string | null
  contact_phone?: string | null
  contact_email?: string | null
  location?: string | null
}

type Params = Record<string, string | number>

/** Multipart body: Laravel reads `files[]`; empty optional values are omitted. */
function toFormData(input: Record<string, unknown>, files: File[]): FormData {
  const form = new FormData()
  for (const [key, value] of Object.entries(input)) {
    if (value !== null && value !== undefined && value !== '') form.append(key, String(value))
  }
  files.forEach((file) => form.append('files[]', file))
  return form
}

export async function listTickets(params: Params): Promise<Paginated<TicketListItem>> {
  const { data } = await api.get<Paginated<TicketListItem>>('/helpdesk/tickets', { params })
  return data
}

export async function getTicket(id: number): Promise<Ticket> {
  const { data } = await api.get<Resource<Ticket>>(`/helpdesk/tickets/${id}`)
  return data.data
}

export async function createTicket(input: TicketInput, files: File[]): Promise<Ticket> {
  const { data } = await api.post<Resource<Ticket>>('/helpdesk/tickets', toFormData({ ...input }, files))
  return data.data
}

export async function updateTicket(id: number, input: Partial<TicketInput>): Promise<Ticket> {
  const { data } = await api.patch<Resource<Ticket>>(`/helpdesk/tickets/${id}`, input)
  return data.data
}

export async function transitionTicket(id: number, status: TicketStatus, note?: string): Promise<Ticket> {
  const { data } = await api.post<Resource<Ticket>>(`/helpdesk/tickets/${id}/transition`, { status, note })
  return data.data
}

export async function assignTicket(id: number, assigneeId: number): Promise<Ticket> {
  const { data } = await api.post<Resource<Ticket>>(`/helpdesk/tickets/${id}/assign`, {
    assignee_id: assigneeId,
  })
  return data.data
}

export async function takeTicket(id: number): Promise<Ticket> {
  const { data } = await api.post<Resource<Ticket>>(`/helpdesk/tickets/${id}/take`)
  return data.data
}

export async function rateTicket(id: number, rating: number, comment?: string): Promise<Ticket> {
  const { data } = await api.post<Resource<Ticket>>(`/helpdesk/tickets/${id}/rate`, { rating, comment })
  return data.data
}

export async function logTime(id: number, minutes: number, note?: string): Promise<Ticket> {
  const { data } = await api.post<Resource<Ticket>>(`/helpdesk/tickets/${id}/time`, { minutes, note })
  return data.data
}

export async function listComments(id: number): Promise<TicketComment[]> {
  const { data } = await api.get<Resource<TicketComment[]>>(`/helpdesk/tickets/${id}/comments`)
  return data.data
}

export async function addComment(id: number, body: string, isInternal: boolean): Promise<TicketComment> {
  const { data } = await api.post<Resource<TicketComment>>(`/helpdesk/tickets/${id}/comments`, {
    body,
    is_internal: isInternal,
  })
  return data.data
}

export async function listHistory(id: number): Promise<TicketHistoryEntry[]> {
  const { data } = await api.get<Resource<TicketHistoryEntry[]>>(`/helpdesk/tickets/${id}/history`)
  return data.data
}

export async function listAttachments(id: number): Promise<TicketAttachment[]> {
  const { data } = await api.get<Resource<TicketAttachment[]>>(`/helpdesk/tickets/${id}/attachments`)
  return data.data
}

export async function uploadAttachments(
  id: number,
  files: File[],
  commentId?: number,
): Promise<TicketAttachment[]> {
  const { data } = await api.post<Resource<TicketAttachment[]>>(
    `/helpdesk/tickets/${id}/attachments`,
    toFormData({ comment_id: commentId }, files),
  )
  return data.data
}

export async function deleteAttachment(ticketId: number, attachmentId: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(
    `/helpdesk/tickets/${ticketId}/attachments/${attachmentId}`,
  )
  return data.message
}

export async function listLinks(id: number): Promise<TicketLink[]> {
  const { data } = await api.get<Resource<TicketLink[]>>(`/helpdesk/tickets/${id}/links`)
  return data.data
}

export async function addLink(
  id: number,
  linkedTicketId: number,
  type: TicketLink['type'],
): Promise<TicketLink> {
  const { data } = await api.post<Resource<TicketLink>>(`/helpdesk/tickets/${id}/links`, {
    linked_ticket_id: linkedTicketId,
    type,
  })
  return data.data
}

export async function deleteLink(ticketId: number, linkId: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/helpdesk/tickets/${ticketId}/links/${linkId}`)
  return data.message
}

export async function listAssignees(organizationId: number): Promise<Ref[]> {
  const { data } = await api.get<Resource<Ref[]>>('/helpdesk/assignees', {
    params: { organization_id: organizationId },
  })
  return data.data
}

export async function getStats(params: Params): Promise<TicketStats> {
  const { data } = await api.get<Resource<TicketStats>>('/helpdesk/stats', { params })
  return data.data
}

export async function listCategories(activeOnly = false): Promise<TicketCategory[]> {
  const { data } = await api.get<Resource<TicketCategory[]>>('/ticket-categories', {
    params: activeOnly ? { 'filter[active]': 1 } : {},
  })
  return data.data
}

export type CategoryInput = Pick<
  TicketCategory,
  'name' | 'name_ru' | 'description' | 'is_active' | 'sort_order'
>

export async function saveCategory(input: CategoryInput, id?: number): Promise<TicketCategory> {
  const { data } = id
    ? await api.put<Resource<TicketCategory>>(`/ticket-categories/${id}`, input)
    : await api.post<Resource<TicketCategory>>('/ticket-categories', input)
  return data.data
}

export async function deleteCategory(id: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/ticket-categories/${id}`)
  return data.message
}

export async function listSlaPolicies(): Promise<SlaPolicy[]> {
  const { data } = await api.get<Resource<SlaPolicy[]>>('/ticket-sla-policies')
  return data.data
}

export interface SlaInput {
  priority?: TicketPriority
  category_id?: number | null
  response_minutes: number
  resolution_minutes: number
}

export async function saveSlaPolicy(input: SlaInput, id?: number): Promise<SlaPolicy> {
  const { data } = id
    ? await api.put<Resource<SlaPolicy>>(`/ticket-sla-policies/${id}`, {
        response_minutes: input.response_minutes,
        resolution_minutes: input.resolution_minutes,
      })
    : await api.post<Resource<SlaPolicy>>('/ticket-sla-policies', input)
  return data.data
}

export async function deleteSlaPolicy(id: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/ticket-sla-policies/${id}`)
  return data.message
}

export async function getCalendar(): Promise<CalendarSettings> {
  const { data } = await api.get<Resource<CalendarSettings>>('/helpdesk/calendar')
  return data.data
}

export async function saveCalendar(input: Omit<CalendarSettings, 'timezone'>): Promise<CalendarSettings> {
  const { data } = await api.put<Resource<CalendarSettings>>('/helpdesk/calendar', input)
  return data.data
}
