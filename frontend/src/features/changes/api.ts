import { api } from '@/api/client'
import type { Paginated, Resource } from '@/api/types'
import type { Change, ChangeAction, ChangeRow } from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)

export const listChanges = (params: Params) =>
  api.get<Paginated<ChangeRow>>('/changes', { params }).then((r) => r.data)
export const getChange = (id: number) => data<Change>(api.get(`/changes/${id}`))
export const saveChange = (values: Values, id?: number) =>
  data<Change>(id ? api.put(`/changes/${id}`, values) : api.post('/changes', values))
export const changeAction = (id: number, action: ChangeAction, values: Values = {}) =>
  data<Change>(api.post(`/changes/${id}/${action}`, values))
export const commentChange = (id: number, comment: string) =>
  data<Change>(api.post(`/changes/${id}/comments`, { comment }))
