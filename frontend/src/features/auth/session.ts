import type { QueryClient } from '@tanstack/react-query'
import { useUiStore } from '@/stores/ui'
import { meQueryKey } from './queries'

/**
 * Forgets everything the previous session loaded: cached server data, the signed-in
 * user and the header's organization context (shared computers).
 */
export function resetClientSession(queryClient: QueryClient): void {
  queryClient.clear()
  queryClient.setQueryData(meQueryKey, null)
  useUiStore.getState().setOrgContext(null)
}
