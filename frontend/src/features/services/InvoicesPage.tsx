import { Group, Select, SimpleGrid, Stack, Text } from '@mantine/core'
import { MonthPickerInput } from '@mantine/dates'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate, formatMoney } from '@/lib/format'
import { useInvoices, useProviders } from './queries'
import { InvoiceStatusBadge } from './ServiceBadges'
import { INVOICE_STATUSES, SERVICE_TYPES, type Invoice } from './types'

function Sum({ label, value }: { label: string; value: string | undefined }) {
  return (
    <Stack gap={0}>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text fw={600}>{formatMoney(value ?? '0')}</Text>
    </Stack>
  )
}

/** Payment control: every billing period and extra cost of the user's organizations. */
export function InvoicesPage() {
  const { t } = useTranslation(['services', 'common'])
  const navigate = useNavigate()
  const canExport = useCan('services.export')
  const params = useTableParams({
    defaultSort: 'due_date',
    filterKeys: ['status', 'period', 'provider', 'type', 'approval', 'organization', 'service'],
    organizationContext: true,
  })
  const list = useInvoices(params.apiParams)
  const providers = useProviders()
  const summary = list.data?.summary

  return (
    <>
      <PageHeader
        title={t('invoices.title')}
        description={t('invoices.description')}
        actions={canExport && <ExportMenu type="service_invoices" filters={params.filters} />}
      />
      <SimpleGrid cols={{ base: 3 }} mb="md" maw={520}>
        <Sum label={t('invoices.planned')} value={summary?.planned} />
        <Sum label={t('invoices.invoiced')} value={summary?.invoiced} />
        <Sum label={t('invoices.paid')} value={summary?.paid} />
      </SimpleGrid>
      <DataTable<Invoice>
        storeKey="service-invoices"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('invoices.empty')}
        onRowClick={(i) => navigate(`/services/invoices/${i.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <Select
              placeholder={t('fields.status')}
              aria-label={t('fields.status')}
              clearable
              data={[
                { value: 'open', label: t('invoices.open') },
                ...INVOICE_STATUSES.map((v) => ({ value: v, label: t(`invoiceStatus.${v}`) })),
              ]}
              value={params.filters.status ?? null}
              onChange={(v) => params.setFilter('status', v)}
              w={{ base: '100%', sm: 200 }}
            />
            <MonthPickerInput
              placeholder={t('invoices.period')}
              aria-label={t('invoices.period')}
              clearable
              valueFormat="MM.YYYY"
              value={params.filters.period ? `${params.filters.period}-01` : null}
              onChange={(v) => params.setFilter('period', v ? v.slice(0, 7) : null)}
              w={{ base: '100%', sm: 160 }}
            />
            <Select
              placeholder={t('services.type')}
              aria-label={t('services.type')}
              clearable
              data={SERVICE_TYPES.map((v) => ({ value: v, label: t(`serviceType.${v}`) }))}
              value={params.filters.type ?? null}
              onChange={(v) => params.setFilter('type', v)}
              w={{ base: '100%', sm: 190 }}
            />
            <Select
              placeholder={t('fields.provider')}
              aria-label={t('fields.provider')}
              clearable
              searchable
              data={(providers.data ?? []).map((p) => ({ value: String(p.id), label: p.name }))}
              value={params.filters.provider ?? null}
              onChange={(v) => params.setFilter('provider', v)}
              w={{ base: '100%', sm: 190 }}
            />
            <Select
              placeholder={t('invoices.approval')}
              aria-label={t('invoices.approval')}
              clearable
              data={[
                { value: 'pending', label: t('invoices.approvalPending') },
                { value: 'approved', label: t('invoices.approvalDone') },
              ]}
              value={params.filters.approval ?? null}
              onChange={(v) => params.setFilter('approval', v)}
              w={{ base: '100%', sm: 190 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'service',
            title: t('invoices.service'),
            render: (i) => (
              <Stack gap={0}>
                <Text size="sm" fw={500}>
                  {i.kind === 'extra' ? (i.description ?? i.service.name) : i.service.name}
                </Text>
                <Text size="xs" c="dimmed">
                  {[i.service.provider.name, i.organization.name].join(', ')}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'period_start',
            title: t('invoices.period'),
            noWrap: true,
            render: (i) =>
              i.kind === 'extra'
                ? t('invoices.extra')
                : `${formatDate(i.period_start)} – ${formatDate(i.period_end)}`,
          },
          {
            accessor: 'amount',
            title: t('invoices.amount'),
            textAlign: 'right',
            render: (i) => (
              <Stack gap={0} align="flex-end">
                <Text size="sm">{formatMoney(i.amount ?? i.planned_amount)}</Text>
                {i.amount === null && (
                  <Text size="xs" c="dimmed">
                    {t('invoices.plannedShort')}
                  </Text>
                )}
              </Stack>
            ),
          },
          {
            accessor: 'paid_amount',
            title: t('invoices.paid'),
            textAlign: 'right',
            render: (i) => formatMoney(i.paid_amount),
          },
          {
            accessor: 'due_date',
            title: t('invoices.dueDate'),
            noWrap: true,
            render: (i) => formatDate(i.due_date),
          },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (i) => (
              <Stack gap={4} align="flex-start">
                <InvoiceStatusBadge status={i.status} />
                {i.amount !== null && !i.approved_at && i.status !== 'paid' && (
                  <Text size="xs" c="orange.8">
                    {t('invoices.approvalPending')}
                  </Text>
                )}
              </Stack>
            ),
          },
        ]}
      />
    </>
  )
}
