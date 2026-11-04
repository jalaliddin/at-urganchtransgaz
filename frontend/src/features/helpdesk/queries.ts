import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as hd from './api'
import type { TicketLink, TicketStatus } from './types'

type Params = Record<string, string | number>

export const helpdeskKeys = {
  all: ['helpdesk'] as const,
  list: (params: Params) => ['helpdesk', 'tickets', params] as const,
  ticket: (id: number) => ['helpdesk', 'ticket', id] as const,
  comments: (id: number) => ['helpdesk', 'ticket', id, 'comments'] as const,
  history: (id: number) => ['helpdesk', 'ticket', id, 'history'] as const,
  attachments: (id: number) => ['helpdesk', 'ticket', id, 'attachments'] as const,
  links: (id: number) => ['helpdesk', 'ticket', id, 'links'] as const,
  assignees: (organizationId: number) => ['helpdesk', 'assignees', organizationId] as const,
  stats: (params: Params) => ['helpdesk', 'stats', params] as const,
  categories: (activeOnly: boolean) => ['helpdesk', 'categories', activeOnly] as const,
  sla: ['helpdesk', 'sla'] as const,
  calendar: ['helpdesk', 'calendar'] as const,
}

export function useTickets(params: Params) {
  return useQuery({
    queryKey: helpdeskKeys.list(params),
    queryFn: () => hd.listTickets(params),
    placeholderData: keepPreviousData,
  })
}

export function useTicket(id: number) {
  return useQuery({ queryKey: helpdeskKeys.ticket(id), queryFn: () => hd.getTicket(id) })
}

export function useTicketComments(id: number) {
  return useQuery({ queryKey: helpdeskKeys.comments(id), queryFn: () => hd.listComments(id) })
}

export function useTicketHistory(id: number) {
  return useQuery({ queryKey: helpdeskKeys.history(id), queryFn: () => hd.listHistory(id) })
}

export function useTicketAttachments(id: number) {
  return useQuery({ queryKey: helpdeskKeys.attachments(id), queryFn: () => hd.listAttachments(id) })
}

export function useTicketLinks(id: number) {
  return useQuery({ queryKey: helpdeskKeys.links(id), queryFn: () => hd.listLinks(id) })
}

export function useAssignees(organizationId: number | null) {
  return useQuery({
    queryKey: helpdeskKeys.assignees(organizationId ?? 0),
    queryFn: () => hd.listAssignees(organizationId ?? 0),
    enabled: organizationId !== null,
  })
}

export function useTicketStats(params: Params, enabled = true) {
  return useQuery({
    queryKey: helpdeskKeys.stats(params),
    queryFn: () => hd.getStats(params),
    placeholderData: keepPreviousData,
    enabled,
  })
}

export function useCategories(activeOnly = false) {
  return useQuery({
    queryKey: helpdeskKeys.categories(activeOnly),
    queryFn: () => hd.listCategories(activeOnly),
    staleTime: 5 * 60_000,
  })
}

export function useSlaPolicies() {
  return useQuery({ queryKey: helpdeskKeys.sla, queryFn: hd.listSlaPolicies })
}

export function useCalendar() {
  return useQuery({ queryKey: helpdeskKeys.calendar, queryFn: hd.getCalendar })
}

/** Everything about one ticket (and the lists/dashboard it appears in) is refreshed after a change. */
function useInvalidateTicket() {
  const queryClient = useQueryClient()
  return (id?: number) =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: id ? ['helpdesk', 'ticket', id] : helpdeskKeys.all }),
      queryClient.invalidateQueries({ queryKey: ['helpdesk', 'tickets'] }),
      queryClient.invalidateQueries({ queryKey: ['helpdesk', 'stats'] }),
      queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
    ])
}

export function useCreateTicket() {
  const invalidate = useInvalidateTicket()
  return useMutation({
    mutationFn: ({ input, files }: { input: hd.TicketInput; files: File[] }) => hd.createTicket(input, files),
    onSuccess: () => invalidate(),
  })
}

export function useUpdateTicket(id: number) {
  const invalidate = useInvalidateTicket()
  return useMutation({
    mutationFn: (input: Partial<hd.TicketInput>) => hd.updateTicket(id, input),
    onSuccess: () => invalidate(id),
  })
}

export function useTicketAction(id: number) {
  const invalidate = useInvalidateTicket()
  const onSuccess = () => invalidate(id)
  return {
    transition: useMutation({
      mutationFn: ({ status, note }: { status: TicketStatus; note?: string }) =>
        hd.transitionTicket(id, status, note),
      onSuccess,
    }),
    assign: useMutation({ mutationFn: (assigneeId: number) => hd.assignTicket(id, assigneeId), onSuccess }),
    take: useMutation({ mutationFn: () => hd.takeTicket(id), onSuccess }),
    rate: useMutation({
      mutationFn: ({ rating, comment }: { rating: number; comment?: string }) =>
        hd.rateTicket(id, rating, comment),
      onSuccess,
    }),
    logTime: useMutation({
      mutationFn: ({ minutes, note }: { minutes: number; note?: string }) => hd.logTime(id, minutes, note),
      onSuccess,
    }),
    comment: useMutation({
      mutationFn: async ({
        body,
        isInternal,
        files,
      }: {
        body: string
        isInternal: boolean
        files: File[]
      }) => {
        const comment = await hd.addComment(id, body, isInternal)
        if (files.length > 0) await hd.uploadAttachments(id, files, comment.id)
        return comment
      },
      onSuccess,
    }),
    upload: useMutation({ mutationFn: (files: File[]) => hd.uploadAttachments(id, files), onSuccess }),
    deleteAttachment: useMutation({
      mutationFn: (attachmentId: number) => hd.deleteAttachment(id, attachmentId),
      onSuccess,
    }),
    link: useMutation({
      mutationFn: ({ linkedId, type }: { linkedId: number; type: TicketLink['type'] }) =>
        hd.addLink(id, linkedId, type),
      onSuccess,
    }),
    unlink: useMutation({ mutationFn: (linkId: number) => hd.deleteLink(id, linkId), onSuccess }),
  }
}

function useInvalidateSettings() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: helpdeskKeys.all })
}

export function useSaveCategory() {
  const invalidate = useInvalidateSettings()
  return useMutation({
    mutationFn: ({ id, input }: { id?: number; input: hd.CategoryInput }) => hd.saveCategory(input, id),
    onSuccess: invalidate,
  })
}

export function useDeleteCategory() {
  const invalidate = useInvalidateSettings()
  return useMutation({ mutationFn: hd.deleteCategory, onSuccess: invalidate })
}

export function useSaveSlaPolicy() {
  const invalidate = useInvalidateSettings()
  return useMutation({
    mutationFn: ({ id, input }: { id?: number; input: hd.SlaInput }) => hd.saveSlaPolicy(input, id),
    onSuccess: invalidate,
  })
}

export function useDeleteSlaPolicy() {
  const invalidate = useInvalidateSettings()
  return useMutation({ mutationFn: hd.deleteSlaPolicy, onSuccess: invalidate })
}

export function useSaveCalendar() {
  const invalidate = useInvalidateSettings()
  return useMutation({ mutationFn: hd.saveCalendar, onSuccess: invalidate })
}
