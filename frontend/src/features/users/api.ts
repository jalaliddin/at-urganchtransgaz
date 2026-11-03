import { api } from '@/api/client'
import type { Paginated, Resource } from '@/api/types'
import type { AccessRow, User } from './types'

export interface CreateUserInput {
  username: string
  name: string
  email: string | null
  organization_id: number | null
  employee_id: number | null
  roles: string[]
}

export interface UpdateUserInput {
  name: string
  email: string | null
  locale: string
  organization_id?: number | null
  employee_id: number | null
}

export async function listUsers(params: Record<string, string | number>): Promise<Paginated<User>> {
  const { data } = await api.get<Paginated<User>>('/users', { params })
  return data
}

export async function getUser(id: number): Promise<User> {
  const { data } = await api.get<Resource<User>>(`/users/${id}`)
  return data.data
}

export async function createUser(input: CreateUserInput): Promise<{ user: User; temporaryPassword: string }> {
  const { data } = await api.post<Resource<User> & { meta: { temporary_password: string } }>('/users', input)
  return { user: data.data, temporaryPassword: data.meta.temporary_password }
}

export async function updateUser(id: number, input: UpdateUserInput): Promise<User> {
  const { data } = await api.put<Resource<User>>(`/users/${id}`, input)
  return data.data
}

export async function setUserActive(id: number, active: boolean): Promise<User> {
  const { data } = await api.post<Resource<User>>(`/users/${id}/${active ? 'activate' : 'deactivate'}`)
  return data.data
}

export async function resetUserPassword(id: number): Promise<string> {
  const { data } = await api.post<Resource<{ temporary_password: string }>>(`/users/${id}/reset-password`)
  return data.data.temporary_password
}

export async function syncUserRoles(id: number, roles: string[]): Promise<User> {
  const { data } = await api.put<Resource<User>>(`/users/${id}/roles`, { roles })
  return data.data
}

export async function syncUserAccess(id: number, access: AccessRow[]): Promise<User> {
  const payload = access.map(({ organization_id, include_descendants }) => ({
    organization_id,
    include_descendants,
  }))
  const { data } = await api.put<Resource<User>>(`/users/${id}/organization-access`, { access: payload })
  return data.data
}
