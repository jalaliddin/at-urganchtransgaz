import { useTranslation } from 'react-i18next'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useCurrentUser } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import { useOrgContext } from '@/hooks/useOrgContext'

/**
 * Narrows the dashboard and lists to one organization's subtree. Shown only to
 * users who can see more than one organization.
 */
export function OrgContextSwitcher() {
  const { t } = useTranslation('nav')
  const me = useCurrentUser()
  const canView = useCan('organizations.view')
  const { organizationId, setOrganizationId } = useOrgContext()

  const seesSeveral =
    me.is_super_admin || me.access.length > 1 || me.access.some((a) => a.include_descendants)
  if (!canView || !seesSeveral) return null

  return (
    <OrganizationSelect
      value={organizationId}
      onChange={setOrganizationId}
      placeholder={t('header.allOrganizations')}
      aria-label={t('header.orgContext')}
      w={260}
      visibleFrom="md"
      size="sm"
    />
  )
}
