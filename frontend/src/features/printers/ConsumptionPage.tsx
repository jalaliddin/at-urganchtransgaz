import { Anchor, Card, Group, SegmentedControl, Stack, Table, Text, Title } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useOrgContext } from '@/hooks/useOrgContext'
import dayjs, { APP_TIMEZONE } from '@/lib/dayjs'
import { formatMoney, formatNumber } from '@/lib/format'
import { usePrinterReport } from './queries'
import { REPORT_GROUPS, type ConsumptionRow, type ReportGroup } from './types'

/** Cartridges used, their cost and the pages printed, per organization, cartridge model or printer. */
export function ConsumptionPage() {
  const { t, i18n } = useTranslation(['printers', 'common'])
  const canExport = useCan('printers.export')
  const [params, setParams] = useSearchParams()
  const year = dayjs().tz(APP_TIMEZONE).year()
  const from = params.get('date_from') ?? `${year}-01-01`
  const to = params.get('date_to') ?? `${year}-12-31`
  const group = (params.get('group_by') as ReportGroup | null) ?? 'organization'
  const { organizationId } = useOrgContext()
  const filters: Record<string, string> = { date_from: from, date_to: to, group_by: group }
  if (organizationId) filters.organization = String(organizationId)
  const report = usePrinterReport(filters)
  const set = (key: string, value: string | null) =>
    setParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value) next.set(key, value)
      else next.delete(key)
      return next
    })
  const keyCell = (r: ConsumptionRow) => {
    const label = r.key.code ? `${r.key.code}, ${r.key.name}` : r.key.name
    return group === 'printer' ? (
      <Anchor component={Link} to={`/printers/${r.key.id}`} size="sm">
        {label}
      </Anchor>
    ) : (
      label
    )
  }
  const month = (m: string) =>
    dayjs(`${m}-01`)
      .locale(i18n.language === 'ru' ? 'ru' : 'uz-latn')
      .format('MMMM YYYY')

  return (
    <>
      <PageHeader
        title={t('report.title')}
        description={t('report.description')}
        actions={canExport && <ExportMenu type="printer_consumption" filters={filters} />}
      />
      <Group gap="sm" mb="md" wrap="wrap" align="flex-end">
        <DateInput
          label={t('report.from')}
          value={from}
          onChange={(v) => set('date_from', v)}
          valueFormat="DD.MM.YYYY"
          w={150}
        />
        <DateInput
          label={t('report.to')}
          value={to}
          onChange={(v) => set('date_to', v)}
          valueFormat="DD.MM.YYYY"
          w={150}
        />
        <SegmentedControl
          aria-label={t('report.groupBy')}
          value={group}
          onChange={(v) => set('group_by', v)}
          data={REPORT_GROUPS.map((v) => ({ value: v, label: t(`report.groups.${v}`) }))}
        />
      </Group>
      {report.error ? (
        <ErrorState error={report.error} onRetry={() => void report.refetch()} />
      ) : (
        <Stack>
          <Card withBorder p={0}>
            <Table.ScrollContainer minWidth={640}>
              <Table striped>
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>{t(`report.groups.${group}`)}</Table.Th>
                    <Table.Th ta="right">{t('report.replacements')}</Table.Th>
                    <Table.Th ta="right">{t('report.cost')}</Table.Th>
                    <Table.Th ta="right">{t('report.pages')}</Table.Th>
                    <Table.Th ta="right">{t('report.costPerPage')}</Table.Th>
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {report.data?.rows.length === 0 && (
                    <Table.Tr>
                      <Table.Td colSpan={5}>
                        <Text c="dimmed" ta="center" py="md">
                          {t('report.empty')}
                        </Text>
                      </Table.Td>
                    </Table.Tr>
                  )}
                  {report.data?.rows.map((r) => (
                    <Table.Tr key={r.key.id}>
                      <Table.Td>{keyCell(r)}</Table.Td>
                      <Table.Td ta="right">{formatNumber(r.replacements)}</Table.Td>
                      <Table.Td ta="right">{formatMoney(r.cost)}</Table.Td>
                      <Table.Td ta="right">{group === 'cartridge' ? '—' : formatNumber(r.pages)}</Table.Td>
                      <Table.Td ta="right">{r.cost_per_page ? formatMoney(r.cost_per_page) : '—'}</Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
                {report.data && report.data.rows.length > 0 && (
                  <Table.Tfoot>
                    <Table.Tr fw={600}>
                      <Table.Td>{t('report.total')}</Table.Td>
                      <Table.Td ta="right">{formatNumber(report.data.total.replacements)}</Table.Td>
                      <Table.Td ta="right">{formatMoney(report.data.total.cost)}</Table.Td>
                      <Table.Td ta="right">{formatNumber(report.data.total.pages)}</Table.Td>
                      <Table.Td ta="right">
                        {report.data.total.cost_per_page ? formatMoney(report.data.total.cost_per_page) : '—'}
                      </Table.Td>
                    </Table.Tr>
                  </Table.Tfoot>
                )}
              </Table>
            </Table.ScrollContainer>
          </Card>
          <Text size="xs" c="dimmed">
            {t('report.note')}
          </Text>
          {report.data && report.data.monthly.length > 0 && (
            <Card withBorder p={0}>
              <Title order={4} p="md" pb={0}>
                {t('report.monthly')}
              </Title>
              <Table.ScrollContainer minWidth={480}>
                <Table>
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>{t('report.month')}</Table.Th>
                      <Table.Th ta="right">{t('report.replacements')}</Table.Th>
                      <Table.Th ta="right">{t('report.cost')}</Table.Th>
                      <Table.Th ta="right">{t('report.pages')}</Table.Th>
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {report.data.monthly.map((m) => (
                      <Table.Tr key={m.month}>
                        <Table.Td tt="capitalize">{month(m.month)}</Table.Td>
                        <Table.Td ta="right">{formatNumber(m.replacements)}</Table.Td>
                        <Table.Td ta="right">{formatMoney(m.cost)}</Table.Td>
                        <Table.Td ta="right">{formatNumber(m.pages)}</Table.Td>
                      </Table.Tr>
                    ))}
                  </Table.Tbody>
                </Table>
              </Table.ScrollContainer>
            </Card>
          )}
        </Stack>
      )}
    </>
  )
}
