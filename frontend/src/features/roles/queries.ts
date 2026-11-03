import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { meQueryKey } from '@/features/auth/queries'
import * as rolesApi from './api'

export const roleKeys = {
  all: ['roles'] as const,
  list: ['roles', 'list'] as const,
  detail: (id: number) => ['roles', 'detail', id] as const,
  permissions: ['roles', 'permissions'] as const,
}

export function useRoles(enabled = true) {
  return useQuery({ queryKey: roleKeys.list, queryFn: rolesApi.listRoles, enabled, staleTime: 60_000 })
}

export function useRole(id: number | null) {
  return useQuery({ queryKey: roleKeys.detail(id ?? 0), queryFn: () => rolesApi.getRole(id as number), enabled: id !== null })
}

export function usePermissionModules() {
  return useQuery({ queryKey: roleKeys.permissions, queryFn: rolesApi.listPermissions, staleTime: Infinity })
}

function useInvalidate() {
  const queryClient = useQueryClient()
  // Role permissions change what the signed-in user may do, too.
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: roleKeys.all }),
      queryClient.invalidateQueries({ queryKey: meQueryKey }),
    ])
}

export function useSaveRole() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: ({ id, input }: { id?: number; input: rolesApi.RoleInput }) =>
      id ? rolesApi.updateRole(id, input) : rolesApi.createRole(input),
    onSuccess: invalidate,
  })
}

export function useDeleteRole() {
  const invalidate = useInvalidate()
  return useMutation({ mutationFn: rolesApi.deleteRole, onSuccess: invalidate })
}
