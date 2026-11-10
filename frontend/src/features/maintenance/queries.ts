import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as m from './api'

type Params = Record<string, string | number>

export const usePlans = (params: Params) =>
  useQuery({
    queryKey: ['maintenance', 'plans', params],
    queryFn: () => m.listPlans(params),
    placeholderData: keepPreviousData,
  })
export const usePlan = (id: number, enabled = true) =>
  useQuery({ queryKey: ['maintenance', 'plan', id], queryFn: () => m.getPlan(id), enabled })
export const useWork = (params: Params) =>
  useQuery({
    queryKey: ['maintenance', 'work', params],
    queryFn: () => m.listWork(params),
    placeholderData: keepPreviousData,
  })

export function useMaintenanceMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [['maintenance'], ['assets'], ['dashboard']].map((queryKey) =>
          queryClient.invalidateQueries({ queryKey }),
        ),
      ),
  })
}
