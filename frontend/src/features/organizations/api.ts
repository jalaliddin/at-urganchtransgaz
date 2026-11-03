import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type { ItResponsible, Organization, OrganizationTreeNode } from './types'

export type OrganizationInput = Partial<
  Pick<Organization, 'parent_id' | 'type' | 'name' | 'short_name' | 'code' | 'tin' | 'address' | 'phone' | 'email' | 'head_name' | 'is_active' | 'sort_order'>
>

export async function listOrganizations(params: Record<string, string | number>): Promise<Paginated<Organization>> {
  const { data } = await api.get<Paginated<Organization>>('/organizations', { params })
  return data
}

export async function getOrganizationTree(): Promise<OrganizationTreeNode[]> {
  const { data } = await api.get<Resource<OrganizationTreeNode[]>>('/organizations/tree')
  return data.data
}

export async function getOrganization(id: number): Promise<Organization> {
  const { data } = await api.get<Resource<Organization>>(`/organizations/${id}`)
  return data.data
}

export async function createOrganization(input: OrganizationInput): Promise<Organization> {
  const { data } = await api.post<Resource<Organization>>('/organizations', input)
  return data.data
}

export async function updateOrganization(id: number, input: OrganizationInput): Promise<Organization> {
  const { data } = await api.put<Resource<Organization>>(`/organizations/${id}`, input)
  return data.data
}

export async function deleteOrganization(id: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/organizations/${id}`)
  return data.message
}

export async function listItResponsibles(organizationId: number): Promise<ItResponsible[]> {
  const { data } = await api.get<Resource<ItResponsible[]>>(`/organizations/${organizationId}/it-responsibles`)
  return data.data
}

export async function assignItResponsible(
  organizationId: number,
  input: { employee_id: number; is_primary: boolean; assigned_at: string; notes?: string | null },
): Promise<ItResponsible> {
  const { data } = await api.post<Resource<ItResponsible>>(`/organizations/${organizationId}/it-responsibles`, input)
  return data.data
}

export async function removeItResponsible(organizationId: number, employeeId: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/organizations/${organizationId}/it-responsibles/${employeeId}`)
  return data.message
}
