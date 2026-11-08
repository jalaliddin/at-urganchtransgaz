import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as r from './api'

type Params = Record<string, string | number>

export const useRequisitions = (params: Params) =>
  useQuery({
    queryKey: ['requisitions', 'list', params],
    queryFn: () => r.listRequisitions(params),
    placeholderData: keepPreviousData,
  })
export const useRequisition = (id: number) =>
  useQuery({ queryKey: ['requisitions', 'detail', id], queryFn: () => r.getRequisition(id) })

/** Requisition steps touch stock, assets and the dashboard: refresh them together. */
export function useRequisitionMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [['requisitions'], ['warehouse'], ['assets'], ['dashboard']].map((queryKey) =>
          queryClient.invalidateQueries({ queryKey }),
        ),
      ),
  })
}
