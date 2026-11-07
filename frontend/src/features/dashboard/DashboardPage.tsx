import { Grid, SimpleGrid, Skeleton, Stack } from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { useCurrentUser } from '@/features/auth/queries'
import { useOrgContext } from '@/hooks/useOrgContext'
import { fetchDashboard } from './api'
import { CoverageMeter } from './widgets/CoverageMeter'
import { EmployeesByStatus } from './widgets/EmployeesByStatus'
import { ProfileCard } from './widgets/ProfileCard'
import { RecentAudit } from './widgets/RecentAudit'
import { StatTile } from './widgets/StatTile'

export function DashboardPage() {
  const { t } = useTranslation('dashboard')
  const { t: th } = useTranslation('helpdesk')
  const { t: ta } = useTranslation('assets')
  const me = useCurrentUser()
  const { organizationId: orgContextId } = useOrgContext()
  const query = useQuery({
    queryKey: ['dashboard', orgContextId],
    queryFn: () => fetchDashboard(orgContextId),
  })

  const header = (
    <PageHeader
      title={t('title')}
      description={orgContextId ? t('scope') : t('greeting', { name: me.name })}
    />
  )

  if (query.isError) {
    return (
      <>
        {header}
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      </>
    )
  }

  const data = query.data
  if (!data) {
    return (
      <>
        {header}
        <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }}>
          {Array.from({ length: 4 }, (_, i) => (
            <Skeleton key={i} height={112} radius="md" />
          ))}
        </SimpleGrid>
      </>
    )
  }

  const tiles = [
    data.helpdesk && (
      <StatTile
        key="helpdesk-open"
        label={th('dashboard.open')}
        value={data.helpdesk.open}
        detail={th('dashboard.newToday') + ': ' + data.helpdesk.new_today}
        href="/helpdesk?filter[status]=new,in_review,assigned,in_progress,waiting_user"
      />
    ),
    data.helpdesk && (
      <StatTile
        key="helpdesk-overdue"
        label={th('dashboard.overdue')}
        value={data.helpdesk.overdue}
        detail={th('dashboard.assignedToMe') + ': ' + data.helpdesk.assigned_to_me}
        href="/helpdesk?filter[overdue]=1"
      />
    ),
    data.assets && (
      <StatTile
        key="assets"
        label={ta('dashboard.active')}
        value={data.assets.active}
        detail={ta('dashboard.inRepair', { count: data.assets.in_repair })}
        href="/assets"
      />
    ),
    data.assets && data.assets.warranty_expiring > 0 && (
      <StatTile
        key="assets-warranty"
        label={ta('dashboard.warranty')}
        value={data.assets.warranty_expiring}
        href="/assets?filter[warranty]=expiring"
      />
    ),
    data.my_assets && (
      <StatTile
        key="my-assets"
        label={ta('dashboard.mine')}
        value={data.my_assets.total}
        href="/assets/mine"
      />
    ),
    data.my_tickets && (
      <StatTile
        key="my-tickets"
        label={th('dashboard.myOpen')}
        value={data.my_tickets.open}
        detail={th('dashboard.myTotal') + ': ' + data.my_tickets.total}
        href="/helpdesk"
      />
    ),
    data.organizations && (
      <StatTile
        key="organizations"
        label={t('organizations.title')}
        value={data.organizations.total}
        detail={t('organizations.active', { count: data.organizations.active })}
        href="/organizations"
      />
    ),
    data.employees && (
      <StatTile
        key="employees"
        label={t('employees.title')}
        value={data.employees.total}
        detail={`${t('common:status.active')}: ${data.employees.by_status.active}`}
        href="/employees"
      />
    ),
    data.it_coverage && <CoverageMeter key="coverage" data={data.it_coverage} />,
    data.users && (
      <StatTile
        key="users"
        label={t('users.title')}
        value={data.users.total}
        detail={
          data.users.pending_password_change > 0
            ? t('users.pending', { count: data.users.pending_password_change })
            : t('users.active', { count: data.users.active })
        }
        href="/users"
      />
    ),
  ].filter(Boolean)

  return (
    <>
      {header}
      <Stack gap="lg">
        {tiles.length > 0 && <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }}>{tiles}</SimpleGrid>}
        <Grid gap="lg">
          <Grid.Col span={{ base: 12, md: data.recent_audit ? 5 : 6 }}>
            <Stack gap="lg">
              {data.employees && <EmployeesByStatus data={data.employees} />}
              <ProfileCard profile={data.profile} />
            </Stack>
          </Grid.Col>
          {data.recent_audit && (
            <Grid.Col span={{ base: 12, md: 7 }}>
              <RecentAudit entries={data.recent_audit} />
            </Grid.Col>
          )}
        </Grid>
      </Stack>
    </>
  )
}
