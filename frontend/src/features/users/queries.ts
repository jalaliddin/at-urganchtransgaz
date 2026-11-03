import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as usersApi from './api'
import type { AccessRow } from './types'

export const userKeys = {
  all: ['users'] as const,
  list: (params: Record<string, string | number>) => ['users', 'list', params] as const,
  detail: (id: number) => ['users', 'detail', id] as const,
}

export function useUsers(params: Record<string, string | number>) {
  return useQuery({
    queryKey: userKeys.list(params),
    queryFn: () => usersApi.listUsers(params),
    placeholderData: keepPreviousData,
  })
}

export function useUser(id: number) {
  return useQuery({ queryKey: userKeys.detail(id), queryFn: () => usersApi.getUser(id) })
}

function useInvalidate() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all(
      [userKeys.all, ['employees'], ['roles'], ['dashboard']].map((queryKey) =>
        queryClient.invalidateQueries({ queryKey }),
      ),
    )
}

export function useCreateUser() {
  const invalidate = useInvalidate()
  return useMutation({ mutationFn: usersApi.createUser, onSuccess: invalidate })
}

export function useUpdateUser(id: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (input: usersApi.UpdateUserInput) => usersApi.updateUser(id, input),
    onSuccess: invalidate,
  })
}

export function useSetUserActive(id: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (active: boolean) => usersApi.setUserActive(id, active),
    onSuccess: invalidate,
  })
}

export function useResetUserPassword(id: number) {
  const invalidate = useInvalidate()
  return useMutation({ mutationFn: () => usersApi.resetUserPassword(id), onSuccess: invalidate })
}

export function useSyncUserRoles(id: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (roles: string[]) => usersApi.syncUserRoles(id, roles),
    onSuccess: invalidate,
  })
}

export function useSyncUserAccess(id: number) {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: (access: AccessRow[]) => usersApi.syncUserAccess(id, access),
    onSuccess: invalidate,
  })
}
