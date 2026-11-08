import { Anchor, Badge, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { useAgents } from './queries'
import type { Agent } from './types'

export function AgentsPage() {
  const { t } = useTranslation('software')
  const params = useTableParams({ defaultSort: '-last_checkin_at' })
  const list = useAgents(params.apiParams)

  return (
    <>
      <PageHeader title={t('agents.listTitle')} description={t('agents.listDescription')} />
      <DataTable<Agent>
        storeKey="software-agents"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('agents.empty')}
        emptyDescription={t('agents.emptyHint')}
        columns={[
          {
            accessor: 'asset',
            title: t('compliance.device'),
            render: (a) =>
              a.asset && (
                <>
                  <Anchor component={Link} to={`/assets/${a.asset.id}`} size="sm" ff="monospace">
                    {a.asset.inventory_number}
                  </Anchor>
                  <Text size="xs" c="dimmed">
                    {a.asset.name} · {a.asset.organization.name}
                  </Text>
                </>
              ),
          },
          { accessor: 'name', title: t('agents.name'), render: (a) => a.name },
          { accessor: 'platform', title: t('agents.platform'), render: (a) => a.platform ?? '—' },
          {
            accessor: 'is_active',
            title: t('agents.state'),
            render: (a) => (
              <Badge color={a.is_active ? 'green' : 'gray'} variant="light" tt="none">
                {a.is_active ? t('agents.active') : t('agents.revoked')}
              </Badge>
            ),
          },
          {
            accessor: 'last_checkin_at',
            title: t('agents.lastCheckin'),
            noWrap: true,
            render: (a) => (a.last_checkin_at ? formatDateTime(a.last_checkin_at) : t('agents.never')),
          },
        ]}
      />
    </>
  )
}
