import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type { Decision, Requisition, RequisitionInput, RequisitionRow } from './types'

type Params = Record<string, string | number>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)
const card = (path: string, body: Record<string, unknown> = {}) => data<Requisition>(api.post(path, body))

export const listRequisitions = (params: Params) =>
  api.get<Paginated<RequisitionRow>>('/requisitions', { params }).then((r) => r.data)
export const getRequisition = (id: number) => data<Requisition>(api.get(`/requisitions/${id}`))
export const createRequisition = (input: RequisitionInput) =>
  data<Requisition>(api.post('/requisitions', input))
export const updateRequisition = (id: number, input: Partial<RequisitionInput>) =>
  data<Requisition>(api.put(`/requisitions/${id}`, input))
export const deleteRequisition = (id: number) =>
  api.delete<MessageResponse>(`/requisitions/${id}`).then((r) => r.data.message)

export const submitRequisition = (id: number) => card(`/requisitions/${id}/submit`)
export const orgDecision = (id: number, decision: Decision, comment?: string) =>
  card(`/requisitions/${id}/org-decision`, { decision, comment })
export const takeRequisition = (id: number) => card(`/requisitions/${id}/take`)
export const requestInfo = (id: number, comment: string) =>
  card(`/requisitions/${id}/request-info`, { comment })
export const provideInfo = (id: number, comment: string) =>
  card(`/requisitions/${id}/provide-info`, { comment })
export const centralDecision = (
  id: number,
  decision: Decision,
  comment: string | undefined,
  items: { id: number; approved_quantity: string }[],
) => card(`/requisitions/${id}/decision`, { decision, comment, items })
export const markReady = (id: number) => card(`/requisitions/${id}/ready`)
export const allocate = (id: number, body: Record<string, unknown>) =>
  card(`/requisitions/${id}/allocations`, body)
export const completeAllocation = (id: number, comment: string) =>
  card(`/requisitions/${id}/complete`, { comment })
export const confirmReceipt = (id: number, comment?: string) =>
  card(`/requisitions/${id}/receive`, { comment })
export const closeRequisition = (id: number) => card(`/requisitions/${id}/close`)
export const addComment = (id: number, body: string) => api.post(`/requisitions/${id}/comments`, { body })
export function uploadAttachments(id: number, files: File[]) {
  const form = new FormData()
  files.forEach((f) => form.append('files[]', f))
  return api.post(`/requisitions/${id}/attachments`, form)
}
