import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as orgApi from './api'

export const organizationKeys = {
  all: ['organizations'] as const,
  list: (params: Record<string, string | number>) => ['organizations', 'list', params] as const,
  tree: ['organizations', 'tree'] as const,
  detail: (id: number) => ['organizations', 'detail', id] as const,
  it: (id: number) => ['organizations', 'it', id] as const,
}

export function useOrganizations(params: Record<string, string | number>) {
  return useQuery({
    queryKey: organizationKeys.list(params),
    queryFn: () => orgApi.listOrganizations(params),
    placeholderData: keepPreviousData,
  })
}

export function useOrganizationTree() {
  return useQuery({ queryKey: organizationKeys.tree, queryFn: orgApi.getOrganizationTree })
}

export function useOrganization(id: number) {
  return useQuery({ queryKey: organizationKeys.detail(id), queryFn: () => orgApi.getOrganization(id) })
}

function useInvalidateOrganizations() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: organizationKeys.all }),
      queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
    ])
}

export function useSaveOrganization() {
  const invalidate = useInvalidateOrganizations()
  return useMutation({
    mutationFn: ({ id, input }: { id?: number; input: orgApi.OrganizationInput }) =>
      id ? orgApi.updateOrganization(id, input) : orgApi.createOrganization(input),
    onSuccess: invalidate,
  })
}

export function useDeleteOrganization() {
  const invalidate = useInvalidateOrganizations()
  return useMutation({ mutationFn: orgApi.deleteOrganization, onSuccess: invalidate })
}

export function useItResponsibles(organizationId: number) {
  return useQuery({
    queryKey: organizationKeys.it(organizationId),
    queryFn: () => orgApi.listItResponsibles(organizationId),
  })
}

export function useAssignItResponsible(organizationId: number) {
  const invalidate = useInvalidateOrganizations()
  return useMutation({
    mutationFn: (input: Parameters<typeof orgApi.assignItResponsible>[1]) =>
      orgApi.assignItResponsible(organizationId, input),
    onSuccess: invalidate,
  })
}

export function useRemoveItResponsible(organizationId: number) {
  const invalidate = useInvalidateOrganizations()
  return useMutation({
    mutationFn: (employeeId: number) => orgApi.removeItResponsible(organizationId, employeeId),
    onSuccess: invalidate,
  })
}
