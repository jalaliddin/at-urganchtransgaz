import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as s from './api'

type Params = Record<string, string | number>

export const useProviders = (params: Params = {}) =>
  useQuery({ queryKey: ['services', 'providers', params], queryFn: () => s.listProviders(params) })
export const useContracts = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['services', 'contracts', params],
    queryFn: () => s.listContracts(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useContract = (id: number) =>
  useQuery({ queryKey: ['services', 'contract', id], queryFn: () => s.getContract(id) })
export const useServices = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['services', 'list', params],
    queryFn: () => s.listServices(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useService = (id: number) =>
  useQuery({ queryKey: ['services', 'service', id], queryFn: () => s.getService(id) })
export const useInvoices = (params: Params) =>
  useQuery({
    queryKey: ['services', 'invoices', params],
    queryFn: () => s.listInvoices(params),
    placeholderData: keepPreviousData,
  })
export const useInvoice = (id: number) =>
  useQuery({ queryKey: ['services', 'invoice', id], queryFn: () => s.getInvoice(id) })
export const useCosts = (params: Params) =>
  useQuery({
    queryKey: ['services', 'costs', params],
    queryFn: () => s.serviceCosts(params),
    placeholderData: keepPreviousData,
  })

/** Service data feeds invoices, reports, SIM operators and the dashboard: refresh them together. */
export function useServicesMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [['services'], ['sim'], ['dashboard']].map((queryKey) => queryClient.invalidateQueries({ queryKey })),
      ),
  })
}
