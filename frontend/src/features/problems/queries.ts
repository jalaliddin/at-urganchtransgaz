import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as p from './api'

type Params = Record<string, string | number>

export const useProblems = (params: Params) =>
  useQuery({
    queryKey: ['problems', 'list', params],
    queryFn: () => p.listProblems(params),
    placeholderData: keepPreviousData,
  })
export const useProblem = (id: number) =>
  useQuery({ queryKey: ['problems', 'detail', id], queryFn: () => p.getProblem(id) })
export const useCandidates = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['problems', 'candidates', params],
    queryFn: () => p.problemCandidates(params),
    enabled,
  })
export const useTicketProblems = (ticketId: number, enabled: boolean) =>
  useQuery({ queryKey: ['problems', 'ticket', ticketId], queryFn: () => p.ticketProblems(ticketId), enabled })

export function useProblemMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [['problems'], ['dashboard']].map((queryKey) => queryClient.invalidateQueries({ queryKey })),
      ),
  })
}
