import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as empApi from './api'

export const employeeKeys = {
  all: ['employees'] as const,
  list: (params: Record<string, string | number>) => ['employees', 'list', params] as const,
  detail: (id: number) => ['employees', 'detail', id] as const,
}

export function useEmployees(params: Record<string, string | number>) {
  return useQuery({ queryKey: employeeKeys.list(params), queryFn: () => empApi.listEmployees(params), placeholderData: keepPreviousData })
}

export function useEmployee(id: number) {
  return useQuery({ queryKey: employeeKeys.detail(id), queryFn: () => empApi.getEmployee(id) })
}

function useInvalidate() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all(
      [employeeKeys.all, ['departments'], ['organizations'], ['users'], ['dashboard']].map((queryKey) =>
        queryClient.invalidateQueries({ queryKey }),
      ),
    )
}

export function useSaveEmployee() {
  const invalidate = useInvalidate()
  return useMutation({
    mutationFn: ({ id, input }: { id?: number; input: empApi.EmployeeInput }) =>
      id ? empApi.updateEmployee(id, input) : empApi.createEmployee(input),
    onSuccess: invalidate,
  })
}

export function useDeleteEmployee() {
  const invalidate = useInvalidate()
  return useMutation({ mutationFn: empApi.deleteEmployee, onSuccess: invalidate })
}
