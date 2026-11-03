import { api } from '@/api/client'
import type { MessageResponse, Resource } from '@/api/types'
import type { PermissionModule, Role } from './types'

export interface RoleInput {
  name?: string
  display_name: string
  description: string | null
  permissions?: string[]
}

export async function listRoles(): Promise<Role[]> {
  const { data } = await api.get<Resource<Role[]>>('/roles')
  return data.data
}

export async function getRole(id: number): Promise<Role> {
  const { data } = await api.get<Resource<Role>>(`/roles/${id}`)
  return data.data
}

export async function listPermissions(): Promise<PermissionModule[]> {
  const { data } = await api.get<Resource<PermissionModule[]>>('/permissions')
  return data.data
}

export async function createRole(input: RoleInput): Promise<Role> {
  const { data } = await api.post<Resource<Role>>('/roles', input)
  return data.data
}

export async function updateRole(id: number, input: RoleInput): Promise<Role> {
  const { name: _immutable, ...rest } = input
  const { data } = await api.put<Resource<Role>>(`/roles/${id}`, rest)
  return data.data
}

export async function deleteRole(id: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/roles/${id}`)
  return data.message
}
