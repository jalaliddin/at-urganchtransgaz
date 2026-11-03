import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type { Department } from './types'

export interface DepartmentInput {
  organization_id?: number
  parent_id: number | null
  name: string
  code: string | null
  phone: string | null
  is_active: boolean
  sort_order: number
}

export async function listDepartments(
  params: Record<string, string | number>,
): Promise<Paginated<Department>> {
  const { data } = await api.get<Paginated<Department>>('/departments', { params })
  return data
}

export async function createDepartment(input: DepartmentInput): Promise<Department> {
  const { data } = await api.post<Resource<Department>>('/departments', input)
  return data.data
}

export async function updateDepartment(id: number, input: DepartmentInput): Promise<Department> {
  const { organization_id: _ignored, ...rest } = input
  const { data } = await api.put<Resource<Department>>(`/departments/${id}`, rest)
  return data.data
}

export async function deleteDepartment(id: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/departments/${id}`)
  return data.message
}
