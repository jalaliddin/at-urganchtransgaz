import { Card, Select, Table, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useProviders } from '@/features/services/queries'
import { useCan } from '@/hooks/usePermissions'
import { useOrgContext } from '@/hooks/useOrgContext'
import { formatMoney } from '@/lib/format'
import { useSimReport } from './queries'

const COUNTS = ['total', 'active', 'in_stock', 'blocked', 'returned'] as const

/** Cards per organization and operator (cancelled cards are not counted). */
export function SimReportPage() {
  const { t } = useTranslation(['sim', 'common'])
  const canExport = useCan('sim.export')
  const [params, setParams] = useSearchParams()
  const provider = params.get('provider')
  const { organizationId } = useOrgContext()
  const apiParams: Record<string, string | number> = {}
  if (provider) apiParams['filter[provider]'] = provider
  if (organizationId) apiParams['filter[organization]'] = organizationId
  const report = useSimReport(apiParams)
  const operators = useProviders({ 'filter[mobile]': 1 })

  return (
    <>
      <PageHeader
        title={t('report.title')}
        description={t('report.description')}
        actions={canExport && <ExportMenu type="sim_cards" filters={provider ? { provider } : {}} />}
      />
      <Select
        mb="md"
        maw={260}
        placeholder={t('fields.operator')}
        aria-label={t('fields.operator')}
        clearable
        data={(operators.data ?? []).map((o) => ({ value: String(o.id), label: o.name }))}
        value={provider}
        onChange={(v) =>
          setParams((prev) => {
            const next = new URLSearchParams(prev)
            if (v) next.set('provider', v)
            else next.delete('provider')
            return next
          })
        }
      />
      {report.error ? (
        <ErrorState error={report.error} onRetry={() => void report.refetch()} />
      ) : (
        <Card withBorder p={0}>
          <Table.ScrollContainer minWidth={720}>
            <Table striped>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('fields.organization')}</Table.Th>
                  <Table.Th>{t('fields.operator')}</Table.Th>
                  {COUNTS.map((c) => (
                    <Table.Th key={c} ta="right">
                      {c === 'total' ? t('report.total') : t(`status.${c}`)}
                    </Table.Th>
                  ))}
                  <Table.Th ta="right">{t('fields.monthlyFee')}</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {report.data?.rows.length === 0 && (
                  <Table.Tr>
                    <Table.Td colSpan={8}>
                      <Text c="dimmed">{t('empty')}</Text>
                    </Table.Td>
                  </Table.Tr>
                )}
                {report.data?.rows.map((r) => (
                  <Table.Tr key={`${r.organization.id}-${r.provider.id}`}>
                    <Table.Td>{r.organization.name}</Table.Td>
                    <Table.Td>{r.provider.name}</Table.Td>
                    {COUNTS.map((c) => (
                      <Table.Td key={c} ta="right">
                        {r[c]}
                      </Table.Td>
                    ))}
                    <Table.Td ta="right">{formatMoney(r.monthly_fee)}</Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
              {report.data && report.data.rows.length > 0 && (
                <Table.Tfoot>
                  <Table.Tr fw={600}>
                    <Table.Td colSpan={2}>{t('report.total')}</Table.Td>
                    {COUNTS.map((c) => (
                      <Table.Td key={c} ta="right">
                        {report.data.total[c]}
                      </Table.Td>
                    ))}
                    <Table.Td ta="right">{formatMoney(report.data.total.monthly_fee)}</Table.Td>
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
