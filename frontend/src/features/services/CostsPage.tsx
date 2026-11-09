import { Card, Group, NumberInput, SegmentedControl, Table, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useOrgContext } from '@/hooks/useOrgContext'
import { formatMoney } from '@/lib/format'
import { useCosts } from './queries'
import type { CostCell, CostGroup } from './types'

const METRICS = ['planned', 'invoiced', 'paid'] as const
type Metric = (typeof METRICS)[number]

/** Monthly costs of a year by organization, provider or service type (by billing period start). */
export function CostsPage() {
  const { t } = useTranslation(['services', 'common'])
  const canExport = useCan('services.export')
  const [params, setParams] = useSearchParams()
  const year = Number(params.get('year')) || new Date().getFullYear()
  const group = (params.get('group_by') as CostGroup | null) ?? 'organization'
  const metric = (params.get('metric') as Metric | null) ?? 'planned'
  const { organizationId } = useOrgContext()
  const apiParams: Record<string, string | number> = { year, group_by: group }
  if (organizationId) apiParams['filter[organization]'] = organizationId
  const costs = useCosts(apiParams)
  const set = (key: string, value: string) =>
    setParams((prev) => {
      const next = new URLSearchParams(prev)
      next.set(key, value)
      return next
    })
  const months = Array.from({ length: 12 }, (_, i) => t(`months.${i + 1}`))
  const cell = (c: CostCell) => formatMoney(c[metric])

  return (
    <>
      <PageHeader
        title={t('costs.title')}
        description={t('costs.description')}
        actions={
          canExport && <ExportMenu type="service_costs" filters={{ year: String(year), group_by: group }} />
        }
      />
      <Group gap="sm" mb="md" wrap="wrap">
        <NumberInput
          aria-label={t('costs.year')}
          min={2000}
          max={2100}
          value={year}
          onChange={(v) => v && set('year', String(v))}
          w={110}
        />
        <SegmentedControl
          aria-label={t('costs.groupBy')}
          value={group}
          onChange={(v) => set('group_by', v)}
          data={(['organization', 'provider', 'type'] as const).map((v) => ({
            value: v,
            label: t(`costs.groups.${v}`),
          }))}
        />
        <SegmentedControl
          aria-label={t('costs.metric')}
          value={metric}
          onChange={(v) => set('metric', v)}
          data={METRICS.map((v) => ({ value: v, label: t(`invoices.${v}`) }))}
        />
      </Group>
      {costs.error ? (
        <ErrorState error={costs.error} onRetry={() => void costs.refetch()} />
      ) : (
        <Card withBorder p={0}>
          <Table.ScrollContainer minWidth={1100}>
            <Table striped>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t(`costs.groups.${group}`)}</Table.Th>
                  {months.map((m) => (
                    <Table.Th key={m} ta="right">
                      {m}
                    </Table.Th>
                  ))}
                  <Table.Th ta="right">{t('costs.total')}</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {costs.data?.rows.length === 0 && (
                  <Table.Tr>
                    <Table.Td colSpan={14}>
                      <Text c="dimmed">{t('costs.empty')}</Text>
                    </Table.Td>
                  </Table.Tr>
                )}
                {costs.data?.rows.map((r) => (
                  <Table.Tr key={String(r.key.id)}>
                    <Table.Td>{group === 'type' ? t(`serviceType.${r.key.id}`) : r.key.name}</Table.Td>
                    {r.months.map((c, i) => (
                      <Table.Td key={i} ta="right">
                        {cell(c)}
                      </Table.Td>
                    ))}
                    <Table.Td ta="right" fw={600}>
                      {cell(r.total)}
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
              {costs.data && costs.data.rows.length > 0 && (
                <Table.Tfoot>
                  <Table.Tr>
                    <Table.Td fw={600}>{t('costs.total')}</Table.Td>
                    <Table.Td colSpan={12} />
                    <Table.Td ta="right" fw={700}>
                      {cell(costs.data.total)}
                    </Table.Td>
                  </Table.Tr>
                </Table.Tfoot>
              )}
            </Table>
          </Table.ScrollContainer>
        </Card>
      )}
    </>
  )
}
