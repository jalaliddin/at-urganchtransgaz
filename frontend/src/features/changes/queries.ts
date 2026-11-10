import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as c from './api'

type Params = Record<string, string | number>

export const useChanges = (params: Params) =>
  useQuery({
    queryKey: ['changes', 'list', params],
    queryFn: () => c.listChanges(params),
    placeholderData: keepPreviousData,
  })
export const useChange = (id: number) =>
  useQuery({ queryKey: ['changes', 'detail', id], queryFn: () => c.getChange(id) })

/** Change steps also touch problems (linked changes), assets (history) and the dashboard. */
export function useChangeMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [['changes'], ['problems'], ['assets'], ['dashboard']].map((queryKey) =>
          queryClient.invalidateQueries({ queryKey }),
        ),
      ),
  })
}
