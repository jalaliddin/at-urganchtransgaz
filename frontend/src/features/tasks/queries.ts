import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as s from './api'

type Params = Record<string, string | number>

export const useBoards = (params: Params = {}) =>
  useQuery({ queryKey: ['tasks', 'boards', params], queryFn: () => s.listBoards(params) })
export const useBoard = (id: number) =>
  useQuery({ queryKey: ['tasks', 'board', id], queryFn: () => s.getBoard(id) })
export const useBoardMembers = (id: number, enabled = true) =>
  useQuery({
    queryKey: ['tasks', 'members', id],
    queryFn: () => s.boardMembers(id),
    enabled,
    staleTime: 60_000,
  })
export const useBoardCards = (id: number, params: Params) =>
  useQuery({
    queryKey: ['tasks', 'cards', id, params],
    queryFn: () => s.boardCards(id, params),
    placeholderData: keepPreviousData,
  })
export const useTask = (id: number | null) =>
  useQuery({ queryKey: ['tasks', 'task', id], queryFn: () => s.getTask(id ?? 0), enabled: id !== null })
export const useTaskList = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['tasks', 'list', params],
    queryFn: () => s.listTasks(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useTicketTasks = (ticketId: number, enabled = true) =>
  useQuery({ queryKey: ['tasks', 'ticket', ticketId], queryFn: () => s.ticketTasks(ticketId), enabled })
export const useRecurrences = (boardId: number) =>
  useQuery({ queryKey: ['tasks', 'recurrences', boardId], queryFn: () => s.listRecurrences(boardId) })
export const useWorkload = (params: Params) =>
  useQuery({ queryKey: ['tasks', 'workload', params], queryFn: () => s.workload(params) })

/** Task changes show up on boards, lists, the ticket card and the dashboard. */
export function useTaskMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all([['tasks'], ['dashboard']].map((queryKey) => queryClient.invalidateQueries({ queryKey }))),
  })
}
