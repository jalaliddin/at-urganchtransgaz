import { keepPreviousData, useMutation, useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query'
import * as a from './api'

type Params = Record<string, string | number>

export const assetKeys = {
  all: ['assets'] as const,
  list: (params: Params) => ['assets', 'list', params] as const,
  detail: (id: number) => ['assets', 'detail', id] as const,
  sub: (id: number, part: string) => ['assets', 'detail', id, part] as const,
}

export const useAssets = (params: Params, enabled = true) =>
  useQuery({
    queryKey: assetKeys.list(params),
    queryFn: () => a.listAssets(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useAsset = (id: number) =>
  useQuery({ queryKey: assetKeys.detail(id), queryFn: () => a.getAsset(id) })
export const useAssetPart = <T>(id: number, part: string, fn: (id: number) => Promise<T>, enabled = true) =>
  useQuery({ queryKey: assetKeys.sub(id, part), queryFn: () => fn(id), enabled })
export const useAssetStats = (params: Params) =>
  useQuery({
    queryKey: ['assets', 'stats', params],
    queryFn: () => a.assetStats(params),
    placeholderData: keepPreviousData,
  })
export const useMyAssets = () => useQuery({ queryKey: ['assets', 'mine'], queryFn: a.myAssets })
export const useEmployeeAssets = (employeeId: number, enabled: boolean) =>
  useQuery({
    queryKey: ['assets', 'employee', employeeId],
    queryFn: () => a.employeeAssets(employeeId),
    enabled,
  })

export const useAssetCategories = () =>
  useQuery({ queryKey: ['assets', 'catalog', 'categories'], queryFn: a.listCategories, staleTime: 300_000 })
export const useManufacturers = () =>
  useQuery({
    queryKey: ['assets', 'catalog', 'manufacturers'],
    queryFn: a.listManufacturers,
    staleTime: 300_000,
  })
export const useAssetModels = (params: Params = {}) =>
  useQuery({
    queryKey: ['assets', 'catalog', 'models', params],
    queryFn: () => a.listModels(params),
    staleTime: 300_000,
  })
export const useLocations = (organizationId: number | null) =>
  useQuery({
    queryKey: ['assets', 'catalog', 'locations', organizationId ?? 'all'],
    queryFn: () => a.listLocations(organizationId ? { 'filter[organization]': organizationId } : {}),
  })

export const useTransfers = (params: Params) =>
  useQuery({
    queryKey: ['assets', 'transfers', params],
    queryFn: () => a.listTransfers(params),
    placeholderData: keepPreviousData,
  })
export const useTransfer = (id: number) =>
  useQuery({ queryKey: ['assets', 'transfer', id], queryFn: () => a.getTransfer(id) })
export const useDisposals = (params: Params) =>
  useQuery({
    queryKey: ['assets', 'disposals', params],
    queryFn: () => a.listDisposals(params),
    placeholderData: keepPreviousData,
  })
export const useSessions = (params: Params) =>
  useQuery({
    queryKey: ['assets', 'sessions', params],
    queryFn: () => a.listSessions(params),
    placeholderData: keepPreviousData,
  })
export const useSession = (id: number) =>
  useQuery({ queryKey: ['assets', 'session', id], queryFn: () => a.getSession(id) })
export const useSessionItems = (id: number, params: Params) =>
  useQuery({
    queryKey: ['assets', 'session', id, 'items', params],
    queryFn: () => a.sessionItems(id, params),
    placeholderData: keepPreviousData,
  })

/**
 * Mutation that refreshes everything asset-related afterwards (lists, cards,
 * dashboard). Asset data is interlinked (holder, transfers, stats), so a broad
 * refresh is simpler and safe at this data size.
 */
export function useAssetMutation<TArgs, TResult>(
  fn: (args: TArgs) => Promise<TResult>,
  extra: QueryKey[] = [],
) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [assetKeys.all, ['dashboard'], ['documents'], ...extra].map((queryKey) =>
          queryClient.invalidateQueries({ queryKey }),
        ),
      ),
  })
}
