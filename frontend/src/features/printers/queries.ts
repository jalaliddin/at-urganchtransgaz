import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as p from './api'

type Params = Record<string, string | number>

export const usePrinters = (params: Params) =>
  useQuery({
    queryKey: ['printers', 'list', params],
    queryFn: () => p.listPrinters(params),
    placeholderData: keepPreviousData,
  })
export const usePrinter = (id: number) =>
  useQuery({ queryKey: ['printers', 'detail', id], queryFn: () => p.getPrinter(id) })
export const useCounters = (id: number, params: Params) =>
  useQuery({
    queryKey: ['printers', 'counters', id, params],
    queryFn: () => p.listCounters(id, params),
    placeholderData: keepPreviousData,
  })
export const usePrinterReplacements = (id: number, params: Params) =>
  useQuery({
    queryKey: ['printers', 'history', id, params],
    queryFn: () => p.listPrinterReplacements(id, params),
    placeholderData: keepPreviousData,
  })
export const useReplacements = (params: Params) =>
  useQuery({
    queryKey: ['printers', 'replacements', params],
    queryFn: () => p.listReplacements(params),
    placeholderData: keepPreviousData,
  })
export const useCartridges = (params: Params) =>
  useQuery({
    queryKey: ['printers', 'cartridges', params],
    queryFn: () => p.listCartridges(params),
    placeholderData: keepPreviousData,
  })
export const usePrinterModels = (enabled = true) =>
  useQuery({ queryKey: ['printers', 'models'], queryFn: p.listPrinterModels, enabled })
export const usePrinterReport = (params: Params) =>
  useQuery({
    queryKey: ['printers', 'report', params],
    queryFn: () => p.printerReport(params),
    placeholderData: keepPreviousData,
  })

/** Printer changes move stock too: refresh printers, warehouse and dashboard. */
export function usePrinterMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [['printers'], ['warehouse'], ['dashboard']].map((queryKey) =>
          queryClient.invalidateQueries({ queryKey }),
        ),
      ),
  })
}
