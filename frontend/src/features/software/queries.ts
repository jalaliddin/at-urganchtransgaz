import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as s from './api'

type Params = Record<string, string | number>

export const useSoftwareList = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['software', 'list', params],
    queryFn: () => s.listSoftware(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useSoftware = (id: number) =>
  useQuery({ queryKey: ['software', 'detail', id], queryFn: () => s.getSoftware(id) })
export const useUnrecognized = () =>
  useQuery({ queryKey: ['software', 'unrecognized'], queryFn: s.listUnrecognized })
export const useLicenses = (params: Params, enabled = true) =>
  useQuery({
    queryKey: ['software', 'licenses', params],
    queryFn: () => s.listLicenses(params),
    placeholderData: keepPreviousData,
    enabled,
  })
export const useDeviceSoftware = (assetId: number) =>
  useQuery({ queryKey: ['software', 'device', assetId], queryFn: () => s.deviceSoftware(assetId) })
export const usePolicies = () => useQuery({ queryKey: ['software', 'policies'], queryFn: s.listPolicies })
export const useComplianceSummary = (params: Params) =>
  useQuery({ queryKey: ['software', 'compliance', params], queryFn: () => s.complianceSummary(params) })
export const useComplianceDevices = (params: Params) =>
  useQuery({
    queryKey: ['software', 'compliance-devices', params],
    queryFn: () => s.complianceDevices(params),
    placeholderData: keepPreviousData,
  })
export const useAgents = (params: Params) =>
  useQuery({
    queryKey: ['software', 'agents', params],
    queryFn: () => s.listAgents(params),
    placeholderData: keepPreviousData,
  })

/** Software data feeds the compliance report and the dashboard: refresh them together. */
export function useSoftwareMutation<TArgs, TResult>(fn: (args: TArgs) => Promise<TResult>) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () =>
      Promise.all(
        [['software'], ['dashboard']].map((queryKey) => queryClient.invalidateQueries({ queryKey })),
      ),
  })
}
