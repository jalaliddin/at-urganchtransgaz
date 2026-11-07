import { BarChart } from '@mantine/charts'
import { Card, Group, Paper, SimpleGrid, Stack, Table, Text, Title } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { ErrorState } from '@/components/ErrorState'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatMoney, formatNumber } from '@/lib/format'
import { useAssetStats } from './queries'
import { ASSET_STATUSES } from './types'

function Tile({ label, value }: { label: string; value: string }) {
  return (
    <Paper withBorder p="md">
      <Text size="sm" c="dimmed" fw={500}>
        {label}
      </Text>
      <Text fz={26} fw={600} style={{ fontVariantNumeric: 'tabular-nums' }}>
        {value}
      </Text>
    </Paper>
  )
}

export function AssetReportPage() {
  const { t } = useTranslation('assets')
  const canExport = useCan('assets.export')
  const params = useTableParams({
    defaultSort: 'inventory_number',
    filterKeys: ['organization'],
    organizationContext: true,
  })
  const filterParams = Object.fromEntries(
    Object.entries(params.apiParams).filter(([k]) => k.startsWith('filter[')),
  )
  const stats = useAssetStats(filterParams)
  const data = stats.data
  const exportFilters = Object.fromEntries(
    Object.entries(filterParams).map(([k, v]) => [k.slice(7, -1), String(v)]),
  )

  return (
    <>
      <PageHeader
        title={t('report.title')}
        description={t('report.description')}
        actions={canExport ? <ExportMenu type="assets" filters={exportFilters} /> : undefined}
      />
      <Stack gap="md">
        <Group>
          <OrganizationSelect
            aria-label={t('filters.organization')}
            placeholder={t('filters.organization')}
            value={params.filters.organization ? Number(params.filters.organization) : null}
            onChange={(v) => params.setFilter('organization', v === null ? null : String(v))}
            w={{ base: '100%', sm: 280 }}
          />
        </Group>
        {stats.error && <ErrorState error={stats.error} onRetry={() => void stats.refetch()} />}
        {data && (
          <>
            <SimpleGrid cols={{ base: 2, md: 3, lg: 5 }}>
              <Tile label={t('report.active')} value={formatNumber(data.active)} />
              <Tile label={t('report.cost')} value={formatMoney(data.total_cost)} />
              <Tile label={t('report.inRepair')} value={formatNumber(data.by_status.in_repair)} />
              <Tile label={t('report.warrantyExpiring')} value={formatNumber(data.warranty_expiring)} />
              <Tile label={t('report.warrantyExpired')} value={formatNumber(data.warranty_expired)} />
            </SimpleGrid>
            <SimpleGrid cols={{ base: 1, lg: 2 }}>
              {[
                {
                  title: t('report.byStatus'),
                  rows: ASSET_STATUSES.map((s) => ({ name: t(`status.${s}`), value: data.by_status[s] })),
                },
                {
                  title: t('report.byCategory'),
                  rows: data.by_category.map((c) => ({ name: c.name, value: c.total })),
                },
              ].map((block) => (
                <Card key={block.title} withBorder p="md">
                  <Title order={3} fz="sm" mb="sm">
                    {block.title}
                  </Title>
                  <BarChart
                    h={Math.max(160, block.rows.length * 32)}
                    data={block.rows}
                    dataKey="name"
                    orientation="vertical"
                    series={[{ name: 'value', label: t('report.count'), color: 'cobalt.6' }]}
                    gridAxis="none"
                    yAxisProps={{ width: 170 }}
                    xAxisProps={{ allowDecimals: false }}
                    maxBarWidth={26}
                    withBarValueLabel
                  />
                </Card>
              ))}
            </SimpleGrid>
            <Card withBorder p={0}>
              <Title order={3} fz="sm" p="md" pb={0}>
                {t('report.byOrganization')}
              </Title>
              <Table.ScrollContainer minWidth={460}>
                <Table verticalSpacing="xs">
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>{t('fields.organization')}</Table.Th>
                      <Table.Th ta="right">{t('report.active')}</Table.Th>
                      <Table.Th ta="right">{t('report.inUse')}</Table.Th>
                      <Table.Th ta="right">{t('report.inRepair')}</Table.Th>
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {data.by_organization.map((o) => (
                      <Table.Tr key={o.id}>
                        <Table.Td>{o.name}</Table.Td>
                        <Table.Td ta="right" ff="monospace">
                          {formatNumber(o.total)}
                        </Table.Td>
                        <Table.Td ta="right" ff="monospace">
                          {formatNumber(o.in_use)}
                        </Table.Td>
                        <Table.Td ta="right" ff="monospace">
                          {formatNumber(o.in_repair)}
                        </Table.Td>
                      </Table.Tr>
                    ))}
                  </Table.Tbody>
                </Table>
              </Table.ScrollContainer>
            </Card>
          </>
        )}
      </Stack>
    </>
  )
}
