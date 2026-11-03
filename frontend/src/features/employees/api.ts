import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type { Employee } from './types'

export type EmployeeInput = Partial<
  Pick<
    Employee,
    | 'last_name'
    | 'first_name'
    | 'middle_name'
    | 'position'
    | 'personnel_number'
    | 'phone'
    | 'internal_phone'
    | 'email'
    | 'status'
    | 'hired_at'
    | 'dismissed_at'
    | 'notes'
  > & { organization_id: number; department_id: number | null }
>

export async function listEmployees(params: Record<string, string | number>): Promise<Paginated<Employee>> {
  const { data } = await api.get<Paginated<Employee>>('/employees', { params })
  return data
}

export async function getEmployee(id: number): Promise<Employee> {
  const { data } = await api.get<Resource<Employee>>(`/employees/${id}`)
  return data.data
}

export async function createEmployee(input: EmployeeInput): Promise<Employee> {
  const { data } = await api.post<Resource<Employee>>('/employees', input)
  return data.data
}

export async function updateEmployee(id: number, input: EmployeeInput): Promise<Employee> {
  const { data } = await api.put<Resource<Employee>>(`/employees/${id}`, input)
  return data.data
}

export async function deleteEmployee(id: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/employees/${id}`)
  return data.message
}
