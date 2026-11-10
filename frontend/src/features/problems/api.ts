import { api } from '@/api/client'
import type { Paginated, Resource } from '@/api/types'
import type { Candidate, Problem, ProblemRow } from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)

export const listProblems = (params: Params) =>
  api.get<Paginated<ProblemRow>>('/problems', { params }).then((r) => r.data)
export const getProblem = (id: number) => data<Problem>(api.get(`/problems/${id}`))
export const saveProblem = (values: Values, id?: number) =>
  data<Problem>(id ? api.put(`/problems/${id}`, values) : api.post('/problems', values))
export const transitionProblem = (id: number, values: Values) =>
  data<Problem>(api.post(`/problems/${id}/transition`, values))
export const linkTickets = (id: number, ticketIds: number[]) =>
  data<Problem>(api.post(`/problems/${id}/tickets`, { ticket_ids: ticketIds }))
export const unlinkTicket = (id: number, ticketId: number) =>
  data<Problem>(api.delete(`/problems/${id}/tickets/${ticketId}`))
export const commentProblem = (id: number, comment: string) =>
  data<Problem>(api.post(`/problems/${id}/comments`, { comment }))
export const problemCandidates = (params: Params) =>
  data<Candidate[]>(api.get('/problems/candidates', { params }))
export const ticketProblems = (ticketId: number) =>
  data<ProblemRow[]>(api.get(`/tickets/${ticketId}/problems`))
