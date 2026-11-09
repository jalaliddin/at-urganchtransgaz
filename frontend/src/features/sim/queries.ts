import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as s from './api'

type Params = Record<string, string | number>

export const useSims = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['sim', 'list', params],
    queryFn: () => s.listSims(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useSim = (id: number) =>
  useQuery({ queryKey: ['sim', 'detail', id], queryFn: () => s.getSim(id) })
export const useEmployeeSims = (employeeId: number, enabled = true) =>
  useQuery({ queryKey: ['sim', 'employee', employeeId], queryFn: () => s.employeeSims(employeeId), enabled })
export const useSimReport = (params: Params) =>
  useQuery({ queryKey: ['sim', 'report', params], queryFn: () => s.simReport(params) })

/** SIM changes show up in lists, the employee card, requisitions and the dashboard. */
export function useSimMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [['sim'], ['requisitions'], ['dashboard']].map((queryKey) =>
          queryClient.invalidateQueries({ queryKey }),
        ),
      ),
  })
}
