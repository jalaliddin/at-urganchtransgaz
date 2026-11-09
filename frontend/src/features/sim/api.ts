import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type { LinkedDocument } from '@/components/LinkedDocuments'
import type { SimAction, SimCard, SimDetail, SimReport } from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)

export const listSims = (params: Params) =>
  api.get<Paginated<SimCard>>('/sim-cards', { params }).then((r) => r.data)
export const getSim = (id: number) => data<SimDetail>(api.get(`/sim-cards/${id}`))
export const saveSim = (values: Values, id?: number) =>
  data<SimDetail>(id ? api.put(`/sim-cards/${id}`, values) : api.post('/sim-cards', values))
export const deleteSim = (id: number) =>
  api.delete<MessageResponse>(`/sim-cards/${id}`).then((r) => r.data.message)
export const revealIccid = (id: number) => data<{ iccid: string | null }>(api.post(`/sim-cards/${id}/reveal`))
export const simAction = (id: number, action: SimAction, values: Values) =>
  data<SimDetail>(api.post(`/sim-cards/${id}/${action}`, values))
export const simDocuments = (id: number) => data<LinkedDocument[]>(api.get(`/sim-cards/${id}/documents`))
export const employeeSims = (employeeId: number) =>
  data<SimCard[]>(api.get(`/employees/${employeeId}/sim-cards`))
export const simReport = (params: Params) => data<SimReport>(api.get('/sim-report', { params }))
