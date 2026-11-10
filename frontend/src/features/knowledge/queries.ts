import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as k from './api'

type Params = Record<string, string | number>

export const useArticles = (params: Params) =>
  useQuery({
    queryKey: ['kb', 'list', params],
    queryFn: () => k.listArticles(params),
    placeholderData: keepPreviousData,
  })
export const useArticle = (id: number, enabled = true) =>
  useQuery({ queryKey: ['kb', 'detail', id], queryFn: () => k.getArticle(id), enabled })
export const useSuggestions = (title: string, category: number | null, enabled: boolean) =>
  useQuery({
    queryKey: ['kb', 'suggest', title, category],
    queryFn: () => k.suggestArticles(title, category),
    enabled,
    staleTime: 60_000,
  })

export function useKbMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({ mutationFn: fn, onSuccess: () => queryClient.invalidateQueries({ queryKey: ['kb'] }) })
}
