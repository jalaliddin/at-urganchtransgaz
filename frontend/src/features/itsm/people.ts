import { useQuery } from '@tanstack/react-query'
import { api } from '@/api/client'
import type { Resource } from '@/api/types'

export type PeopleFor = 'problem' | 'change' | 'maintenance'

/** People who may be responsible for a problem, implement a change or perform maintenance in an organization. */
export function usePeople(kind: PeopleFor, organizationId: number | null) {
  return useQuery({
    queryKey: ['itsm', 'people', kind, organizationId],
    queryFn: () =>
      api
        .get<Resource<{ id: number; name: string }[]>>('/itsm/people', {
          params: { for: kind, organization_id: organizationId },
        })
        .then((r) => r.data.data),
    enabled: organizationId !== null,
    staleTime: 60_000,
  })
}
