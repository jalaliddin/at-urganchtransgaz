import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as depApi from './api'

export const departmentKeys = {
  all: ['departments'] as const,
  list: (params: Record<string, string | number>) => ['departments', 'list', params] as const,
}

export function useDepartments(params: Record<string, string | number>, enabled = true) {
  return useQuery({
    queryKey: departmentKeys.list(params),
    queryFn: () => depApi.listDepartments(params),
    placeholderData: keepPreviousData,
    enabled,
  })
}

function useInvalidate() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: departmentKeys.all }),
      queryClient.invalidateQueries({ queryKey: ['employees'] }),
    ])
}

export function useSaveDepartment() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: ({ id, input }: { id?: number; input: depApi.DepartmentInput }) =>
      id ? depApi.updateDepartment(id, input) : depApi.createDepartment(input),
    onSuccess: invalidate,
  })
}

export function useDeleteDepartment() {
  const invalidate = useInvalidate()
  return useMutation({ mutationFn: depApi.deleteDepartment, onSuccess: invalidate })
}
