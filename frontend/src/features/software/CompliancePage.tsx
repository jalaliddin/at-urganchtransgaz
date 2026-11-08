import { Anchor, Card, Group, SegmentedControl, Table, Text, Title } from '@mantine/core'
import { useMediaQuery } from '@mantine/hooks'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { useComplianceDevices, useComplianceSummary } from './queries'
import { AntivirusBadge, antivirusColor } from './SoftwareBadges'
import { AV_STATES, type ComplianceDevice } from './types'

export function CompliancePage() {
  const { t } = useTranslation('software')
  const canExport = useCan('software.export')
  const phone = useMediaQuery('(max-width: 48em)')
  const params = useTableParams({
    defaultSort: 'inventory_number',
    filterKeys: ['problem', 'organization'],
    organizationContext: true,
  })
  const orgFilter = params.apiParams['filter[organization]']
  const summary = useComplianceSummary(orgFilter ? { 'filter[organization]': orgFilter } : {})
  const devices = useComplianceDevices(params.apiParams)
  const rows = summary.data?.organizations ?? []

  return (
    <>
      <PageHeader
        title={t('compliance.title')}
        description={t('compliance.description')}
        actions={canExport && <ExportMenu type="software_compliance" filters={params.filters} />}
      />
      <Card withBorder p={0} mb="md">
        <Table.ScrollContainer minWidth={760}>
          <Table verticalSpacing="xs" highlightOnHover>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>{t('compliance.organization')}</Table.Th>
                <Table.Th ta="right">{t('compliance.devices')}</Table.Th>
                {AV_STATES.map((a) => (
                  <Table.Th key={a} ta="right">
                    {t(`antivirus.status.${a}`)}
                  </Table.Th>
                ))}
                <Table.Th ta="right">{t('compliance.missing')}</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {rows.map((r) => (
                <Table.Tr key={r.organization.id}>
                  <Table.Td>{r.organization.name}</Table.Td>
                  <Table.Td ta="right" fw={600}>
                    {r.devices}
                  </Table.Td>
                  {AV_STATES.map((a) => (
                    <Table.Td key={a} ta="right">
                      <Text
                        size="sm"
                        c={
                          r.antivirus[a] > 0 && a !== 'updated' && a !== 'installed'
                            ? `${antivirusColor(a)}.8`
                            : undefined
                        }
                      >
                        {r.antivirus[a]}
                      </Text>
                    </Table.Td>
                  ))}
                  <Table.Td ta="right">
                    <Text size="sm" c={r.missing_required > 0 ? 'orange.8' : undefined}>
                      {r.missing_required}
                    </Text>
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
        {rows.length === 0 && (
          <Text size="sm" c="dimmed" p="md">
            {t('compliance.noDevices')}
          </Text>
        )}
      </Card>
      <Title order={2} size="h4" mb="sm">
        {t('compliance.deviceList')}
      </Title>
      <DataTable<ComplianceDevice>
        storeKey="software-compliance-devices"
        params={params}
        records={devices.data?.data}
        meta={devices.data?.meta}
        isLoading={devices.isPending}
        isFetching={devices.isFetching}
        error={devices.error}
        onRetry={() => void devices.refetch()}
        emptyTitle={t('compliance.noProblems')}
        toolbar={
          <SegmentedControl
            aria-label={t('compliance.problem')}
            orientation={phone ? 'vertical' : 'horizontal'}
            fullWidth={phone}
            value={params.filters.problem ?? 'all'}
            onChange={(v) => params.setFilter('problem', v === 'all' ? null : v)}
            data={[
              { value: 'all', label: t('compliance.problems.all') },
              { value: 'unprotected', label: t('compliance.problems.unprotected') },
              { value: 'outdated', label: t('compliance.problems.outdated') },
              { value: 'missing_required', label: t('compliance.problems.missing_required') },
            ]}
          />
        }
        columns={[
          {
            accessor: 'inventory_number',
            title: t('compliance.device'),
            render: (d) => (
              <Group gap={6} wrap="nowrap">
                <Anchor component={Link} to={`/assets/${d.id}`} size="sm" ff="monospace">
                  {d.inventory_number}
                </Anchor>
                <Text size="sm">{d.name}</Text>
              </Group>
            ),
          },
          {
            accessor: 'organization',
            title: t('compliance.organization'),
            render: (d) => d.organization.name,
          },
          {
            accessor: 'antivirus',
            title: t('antivirus.title'),
            render: (d) => <AntivirusBadge status={d.antivirus} />,
          },
          {
            accessor: 'missing',
            title: t('compliance.missing'),
            render: (d) =>
              d.missing.length ? (
                <Text size="sm" c="orange.8">
                  {d.missing.join(', ')}
                </Text>
              ) : (
                '—'
              ),
          },
          {
            accessor: 'inventoried_at',
            title: t('compliance.inventoried'),
            noWrap: true,
            render: (d) => (d.inventoried_at ? formatDateTime(d.inventoried_at) : '—'),
          },
        ]}
      />
    </>
  )
}
