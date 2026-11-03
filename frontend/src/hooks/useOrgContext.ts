import { useMe } from '@/features/auth/queries'
import { useUiStore } from '@/stores/ui'

/** The header's organization context for the signed-in user (null = all accessible organizations). */
export function useOrgContext(): {
  organizationId: number | null
  setOrganizationId: (id: number | null) => void
} {
  const { data: me } = useMe()
  const context = useUiStore((s) => s.orgContext)
  const setOrgContext = useUiStore((s) => s.setOrgContext)

  return {
    organizationId: me && context?.userId === me.id ? context.organizationId : null,
    setOrganizationId: (id) =>
      setOrgContext(me && id !== null ? { userId: me.id, organizationId: id } : null),
  }
}
