import { BarChart } from '@mantine/charts'
import {
  Card,
  Group,
  Paper,
  SegmentedControl,
  Select,
  SimpleGrid,
  Stack,
  Table,
  Text,
  Title,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatNumber } from '@/lib/format'
import { useCategories, useTicketStats } from './queries'
import { TICKET_STATUSES } from './types'

function percent(met: number, total: number): string {
  return total === 0 ? '—' : `${Math.round((met / total) * 100)}%`
}

function duration(minutes: number | null, t: (key: string, o?: Record<string, unknown>) => string): string {
  if (minutes === null) return '—'
  return minutes < 120
    ? t('sla.minutes', { count: minutes })
    : t('sla.hours', { count: Math.round(minutes / 60) })
}

function Tile({ label, value, detail }: { label: string; value: string; detail?: string }) {
  return (
    <Paper withBorder p="md">
      <Text size="sm" c="dimmed" fw={500}>
        {label}
      </Text>
      <Text fz={28} fw={600} lh={1.2} style={{ fontVariantNumeric: 'tabular-nums' }}>
        {value}
      </Text>
      {detail && (
        <Text size="xs" c="dimmed" ff="monospace">
          {detail}
        </Text>
      )}
    </Paper>
  )
}

/** One series, one hue: bars carry magnitude, labels carry identity (no legend needed). */
function Breakdown({
  title,
  rows,
  view,
}: {
  title: string
  rows: { name: string; value: number }[]
  view: 'chart' | 'table'
}) {
  const { t } = useTranslation('helpdesk')
  return (
    <Card withBorder p="md">
      <Title order={3} fz="sm" mb="sm">
        {title}
      </Title>
      {view === 'chart' ? (
        <BarChart
          h={Math.max(160, rows.length * 34)}
          data={rows}
          dataKey="name"
          orientation="vertical"
          series={[{ name: 'value', label: t('reports.count'), color: 'cobalt.6' }]}
          gridAxis="none"
          yAxisProps={{ width: 150 }}
          xAxisProps={{ allowDecimals: false }}
          maxBarWidth={28}
          barProps={{ radius: [0, 4, 4, 0] }}
          withBarValueLabel
          aria-label={title}
        />
      ) : (
        <Table>
          <Table.Tbody>
            {rows.map((row) => (
              <Table.Tr key={row.name}>
                <Table.Td>{row.name}</Table.Td>
                <Table.Td ta="right" ff="monospace">
                  {formatNumber(row.value)}
                </Table.Td>
              </Table.Tr>
            ))}
          </Table.Tbody>
        </Table>
      )}
    </Card>
  )
}

export function HelpdeskReportPage() {
  const { t } = useTranslation('helpdesk')
  const canExport = useCan('helpdesk.export')
  const params = useTableParams({
    defaultSort: '-created_at',
    filterKeys: ['organization', 'category', 'created_from', 'created_to'],
    organizationContext: true,
  })
  const filterParams = Object.fromEntries(
    Object.entries(params.apiParams).filter(([key]) => key.startsWith('filter[')),
  )
  const stats = useTicketStats(filterParams)
  const categories = useCategories()
  const [view, setView] = useState<'chart' | 'table'>('chart')
  const data = stats.data

  const exportFilters = Object.fromEntries(
    Object.entries(filterParams).map(([key, value]) => [key.slice(7, -1), String(value)]),
  )

  return (
    <>
      <PageHeader
        title={t('reports.title')}
        description={t('reports.description')}
        actions={canExport ? <ExportMenu type="helpdesk_tickets" filters={exportFilters} /> : undefined}
      />
      <Stack gap="md">
        <Group gap="sm" wrap="wrap">
          <OrganizationSelect
            aria-label={t('filters.organization')}
            placeholder={t('filters.organization')}
            value={params.filters.organization ? Number(params.filters.organization) : null}
            onChange={(value) => params.setFilter('organization', value === null ? null : String(value))}
            w={{ base: '100%', sm: 240 }}
          />
          <Select
            aria-label={t('filters.category')}
            placeholder={t('filters.category')}
            data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
            value={params.filters.category ?? null}
            onChange={(value) => params.setFilter('category', value)}
            clearable
            w={{ base: '100%', sm: 200 }}
          />
          <DatePickerInput
            type="range"
            aria-label={`${t('filters.createdFrom')} – ${t('filters.createdTo')}`}
            placeholder={`${t('filters.createdFrom')} – ${t('filters.createdTo')}`}
            value={[params.filters.created_from ?? null, params.filters.created_to ?? null]}
            onChange={([from, to]) =>
              params.setFilters({ created_from: from ?? null, created_to: to ?? null })
            }
            valueFormat="DD.MM.YYYY"
            clearable
            w={{ base: '100%', sm: 260 }}
          />
          <SegmentedControl
            value={view}
            onChange={(v) => setView(v as 'chart' | 'table')}
            data={[
              { value: 'chart', label: t('reports.chart') },
              { value: 'table', label: t('reports.table') },
            ]}
            aria-label={t('reports.view')}
          />
        </Group>

        {stats.error ? (
          <ErrorState error={stats.error} onRetry={() => void stats.refetch()} />
        ) : !data ? null : data.total === 0 ? (
          <EmptyState title={t('reports.noData')} />
        ) : (
          <>
            <SimpleGrid cols={{ base: 2, md: 3, lg: 6 }}>
              <Tile label={t('reports.total')} value={formatNumber(data.total)} />
              <Tile label={t('reports.open')} value={formatNumber(data.open)} />
              <Tile label={t('reports.overdue')} value={formatNumber(data.overdue)} />
              <Tile
                label={t('reports.responseSla')}
                value={percent(data.sla.response_met, data.sla.response_total)}
                detail={t('reports.ofTotal', { met: data.sla.response_met, total: data.sla.response_total })}
              />
              <Tile
                label={t('reports.resolutionSla')}
                value={percent(data.sla.resolution_met, data.sla.resolution_total)}
                detail={t('reports.ofTotal', {
                  met: data.sla.resolution_met,
                  total: data.sla.resolution_total,
                })}
              />
              <Tile label={t('reports.avgResolution')} value={duration(data.avg_resolution_minutes, t)} />
            </SimpleGrid>
            <SimpleGrid cols={{ base: 1, lg: 2 }}>
              <Breakdown
                title={t('reports.byStatus')}
                view={view}
                rows={TICKET_STATUSES.map((s) => ({ name: t(`status.${s}`), value: data.by_status[s] }))}
              />
              <Breakdown
                title={t('reports.byCategory')}
                view={view}
                rows={data.by_category.map((c) => ({ name: c.name, value: c.total }))}
              />
            </SimpleGrid>
            <Card withBorder p={0}>
              <Title order={3} fz="sm" p="md" pb={0}>
                {t('reports.byOrganization')}
              </Title>
              <Table.ScrollContainer minWidth={480}>
                <Table verticalSpacing="xs">
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>{t('fields.organization')}</Table.Th>
                      <Table.Th ta="right">{t('reports.total')}</Table.Th>
                      <Table.Th ta="right">{t('reports.open')}</Table.Th>
                      <Table.Th ta="right">{t('reports.overdue')}</Table.Th>
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
                          {formatNumber(o.open)}
                        </Table.Td>
                        <Table.Td ta="right" ff="monospace" c={o.overdue > 0 ? 'red' : undefined}>
                          {formatNumber(o.overdue)}
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
