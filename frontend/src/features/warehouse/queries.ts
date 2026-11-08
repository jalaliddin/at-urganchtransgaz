import { keepPreviousData, useMutation, useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query'
import * as w from './api'

type Params = Record<string, string | number>

export const warehouseKeys = { all: ['warehouse'] as const }

export const useUnits = () =>
  useQuery({ queryKey: ['warehouse', 'catalog', 'units'], queryFn: w.listUnits, staleTime: 300_000 })
export const useMaterialCategories = () =>
  useQuery({
    queryKey: ['warehouse', 'catalog', 'categories'],
    queryFn: w.listMaterialCategories,
    staleTime: 300_000,
  })
export const useMaterials = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['warehouse', 'catalog', 'materials', params],
    queryFn: () => w.listMaterials(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useWarehouses = (params: Params = {}) =>
  useQuery({
    queryKey: ['warehouse', 'catalog', 'warehouses', params],
    queryFn: () => w.listWarehouses(params),
  })

export const useBalances = (params: Params) =>
  useQuery({
    queryKey: ['warehouse', 'balances', params],
    queryFn: () => w.listBalances(params),
    placeholderData: keepPreviousData,
  })
export const useAvailability = (warehouseId: number | null, materialId: number | null) =>
  useQuery({
    queryKey: ['warehouse', 'availability', warehouseId, materialId],
    queryFn: () => w.getAvailability(warehouseId ?? 0, materialId ?? 0),
    enabled: warehouseId !== null && materialId !== null,
  })
export const useDocuments = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['warehouse', 'documents', params],
    queryFn: () => w.listDocuments(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useDocument = (id: number) =>
  useQuery({ queryKey: ['warehouse', 'document', id], queryFn: () => w.getDocument(id) })
export const useMovements = (params: Params) =>
  useQuery({
    queryKey: ['warehouse', 'movements', params],
    queryFn: () => w.listMovements(params),
    placeholderData: keepPreviousData,
  })
export const useCounts = (params: Params) =>
  useQuery({
    queryKey: ['warehouse', 'counts', params],
    queryFn: () => w.listCounts(params),
    placeholderData: keepPreviousData,
  })
export const useCount = (id: number) =>
  useQuery({ queryKey: ['warehouse', 'count', id], queryFn: () => w.getCount(id) })

/** Stock changes ripple into balances, journals, alerts and requisitions: refresh broadly. */
export function useWarehouseMutation<TArgs, TResult>(
  fn: (args: TArgs) => Promise<TResult>,
  extra: QueryKey[] = [],
) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [warehouseKeys.all, ['dashboard'], ['requisitions'], ...extra].map((queryKey) =>
          queryClient.invalidateQueries({ queryKey }),
        ),
      ),
  })
}
