import { Grid, SimpleGrid, Skeleton, Stack } from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { useCurrentUser } from '@/features/auth/queries'
import { useOrgContext } from '@/hooks/useOrgContext'
import { formatMoney } from '@/lib/format'
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
  const { t: tw } = useTranslation('warehouse')
  const { t: tr } = useTranslation('requisitions')
  const { t: ts } = useTranslation('software')
  const { t: tsim } = useTranslation('sim')
  const { t: ttask } = useTranslation('tasks')
  const { t: tsv } = useTranslation('services')
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
    data.requisitions && (
      <StatTile
        key="requisitions"
        label={tr('dashboard.awaiting')}
        value={data.requisitions.awaiting_action}
        detail={tr('dashboard.open', { count: data.requisitions.open })}
        href="/requisitions?filter[tab]=action"
      />
    ),
    data.warehouse && (
      <StatTile
        key="warehouse-low"
        label={tw('dashboard.lowStock')}
        value={data.warehouse.low_stock}
        detail={tw('dashboard.documentsMonth', { count: data.warehouse.documents_month })}
        href="/warehouse?filter[low]=1"
      />
    ),
    data.warehouse && data.warehouse.expiring > 0 && (
      <StatTile
        key="warehouse-expiring"
        label={tw('dashboard.expiring')}
        value={data.warehouse.expiring}
        href="/warehouse?filter[expiring]=1"
      />
    ),
    data.software && data.software.devices > 0 && (
      <StatTile
        key="software-av"
        label={ts('dashboard.protected')}
        value={data.software.protected}
        detail={ts('dashboard.unprotected', {
          devices: data.software.devices,
          count: data.software.unprotected,
        })}
        href="/software/compliance?filter[problem]=unprotected"
      />
    ),
    data.software && data.software.licenses_expiring > 0 && (
      <StatTile
        key="software-licenses"
        label={ts('dashboard.licenses')}
        value={data.software.licenses_expiring}
        href="/software/licenses?filter[expiring]=1"
      />
    ),
    data.tasks && (data.tasks.my_open > 0 || data.tasks.overdue > 0) && (
      <StatTile
        key="tasks"
        label={ttask('dashboard.mine')}
        value={data.tasks.my_open}
        detail={ttask('dashboard.overdue', { mine: data.tasks.my_overdue, all: data.tasks.overdue })}
        href="/tasks/my"
      />
    ),
    data.sim && data.sim.total > 0 && (
      <StatTile
        key="sim"
        label={tsim('dashboard.cards')}
        value={data.sim.total}
        detail={[
          tsim('dashboard.active', { count: data.sim.by_status.active }),
          ...data.sim.by_operator.slice(0, 3).map((o) => `${o.provider.name}: ${o.count}`),
        ].join(', ')}
        href="/sim/report"
      />
    ),
    data.services && (
      <StatTile
        key="services-month"
        label={tsv('dashboard.month')}
        value={Math.round(Number(data.services.month_planned))}
        detail={tsv('dashboard.paid', { amount: formatMoney(data.services.month_paid) })}
        href="/services/invoices"
      />
    ),
    data.services && data.services.overdue + data.services.due_soon > 0 && (
      <StatTile
        key="services-due"
        label={tsv('dashboard.due')}
        value={data.services.due_soon}
        detail={tsv('dashboard.overdue', { count: data.services.overdue })}
        href={
          data.services.overdue > 0
            ? '/services/invoices?filter[status]=overdue'
            : '/services/invoices?filter[status]=open'
        }
      />
    ),
    data.services && data.services.awaiting_approval > 0 && (
      <StatTile
        key="services-approval"
        label={tsv('dashboard.approval')}
        value={data.services.awaiting_approval}
        href="/services/invoices?filter[approval]=pending"
      />
    ),
    data.services && data.services.contracts_expiring > 0 && (
      <StatTile
        key="services-contracts"
        label={tsv('dashboard.contracts')}
        value={data.services.contracts_expiring}
        href="/services/contracts?filter[status]=expiring"
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
