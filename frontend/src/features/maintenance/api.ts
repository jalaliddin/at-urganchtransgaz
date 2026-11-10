import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type { Plan, PlanRow, WorkItem } from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)

export const listPlans = (params: Params) =>
  api.get<Paginated<PlanRow>>('/maintenance-plans', { params }).then((r) => r.data)
export const getPlan = (id: number) => data<Plan>(api.get(`/maintenance-plans/${id}`))
export const savePlan = (values: Values, id?: number) =>
  data<Plan>(id ? api.put(`/maintenance-plans/${id}`, values) : api.post('/maintenance-plans', values))
export const deletePlan = (id: number) =>
  api.delete<MessageResponse>(`/maintenance-plans/${id}`).then((r) => r.data.message)
export const listWork = (params: Params) =>
  api.get<Paginated<WorkItem>>('/maintenance-tasks', { params }).then((r) => r.data)
export const completeWork = (id: number, values: Values) =>
  data<WorkItem>(api.post(`/maintenance-tasks/${id}/complete`, values))
export const skipWork = (id: number, reason: string) =>
  data<WorkItem>(api.post(`/maintenance-tasks/${id}/skip`, { reason }))
