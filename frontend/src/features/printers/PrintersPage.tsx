import { Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { AssetStatusBadge } from '@/features/assets/AssetBadges'
import { ASSET_STATUSES } from '@/features/assets/types'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate, formatNumber } from '@/lib/format'
import { useCartridges, usePrinters } from './queries'
import { PRINTER_CONNECTIONS, type PrinterRow } from './types'

/** Printer registry: printer-category assets in the user's organizations. */
export function PrintersPage() {
  const { t } = useTranslation(['printers', 'assets', 'common'])
  const navigate = useNavigate()
  const canExport = useCan('printers.export')
  const params = useTableParams({
    defaultSort: 'inventory_number',
    filterKeys: ['connection', 'status', 'cartridge', 'organization'],
    organizationContext: true,
  })
  const list = usePrinters(params.apiParams)
  const cartridges = useCartridges({ per_page: 100, 'filter[active]': 1 })
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          canExport && <ExportMenu type="printers" filters={{ ...params.filters, search: params.search }} />
        }
      />
      <DataTable<PrinterRow>
        storeKey="printers"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty')}
        onRowClick={(p) => navigate(`/printers/${p.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('filters.search')}
              aria-label={t('filters.search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 260 }}
            />
            <Select
              placeholder={t('fields.connection')}
              aria-label={t('fields.connection')}
              clearable
              data={PRINTER_CONNECTIONS.map((v) => ({ value: v, label: t(`connection.${v}`) }))}
              value={params.filters.connection ?? null}
              onChange={(v) => params.setFilter('connection', v)}
              w={{ base: '100%', sm: 200 }}
            />
            <Select
              placeholder={t('fields.status')}
              aria-label={t('fields.status')}
              clearable
              data={ASSET_STATUSES.map((v) => ({ value: v, label: t(`assets:status.${v}`) }))}
              value={params.filters.status ?? null}
              onChange={(v) => params.setFilter('status', v)}
              w={{ base: '100%', sm: 180 }}
            />
            <Select
              placeholder={t('filters.cartridge')}
              aria-label={t('filters.cartridge')}
              clearable
              searchable
              data={(cartridges.data?.data ?? []).map((c) => ({
                value: String(c.id),
                label: c.part_number ? `${c.name} (${c.part_number})` : c.name,
              }))}
              value={params.filters.cartridge ?? null}
              onChange={(v) => params.setFilter('cartridge', v)}
              w={{ base: '100%', sm: 220 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'inventory_number',
            title: t('fields.printer'),
            sortable: false,
            render: (p) => (
              <Stack gap={0}>
                <Text size="sm" fw={500}>
                  {p.name}
                </Text>
                <Text size="xs" c="dimmed">
                  <Text span ff="monospace" inherit>
                    {p.inventory_number}
                  </Text>
                  {p.model ? `, ${[p.model.manufacturer, p.model.name].filter(Boolean).join(' ')}` : ''}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'organization',
            title: t('fields.place'),
            render: (p) => (
              <Stack gap={0}>
                <Text size="sm">{p.organization.name}</Text>
                {(p.location || p.room) && (
                  <Text size="xs" c="dimmed">
                    {[p.location?.name, p.room].filter(Boolean).join(', ')}
                  </Text>
                )}
              </Stack>
            ),
          },
          { accessor: 'holder', title: t('fields.holder'), render: (p) => p.holder?.full_name ?? '—' },
          {
            accessor: 'connection_type',
            title: t('fields.connection'),
            render: (p) => (
              <Stack gap={0}>
                <Text size="sm">{p.connection_type ? t(`connection.${p.connection_type}`) : '—'}</Text>
                {p.ip_address && (
                  <Text size="xs" c="dimmed" ff="monospace">
                    {p.ip_address}
                  </Text>
                )}
              </Stack>
            ),
          },
          {
            accessor: 'last_replacement',
            title: t('fields.lastReplacement'),
            render: (p) =>
              p.last_replacement ? (
                <Stack gap={0}>
                  <Text size="sm">{p.last_replacement.cartridge}</Text>
                  <Text size="xs" c="dimmed">
                    {formatDate(p.last_replacement.replaced_at)}
                  </Text>
                </Stack>
              ) : (
                '—'
              ),
          },
          {
            accessor: 'last_counter',
            title: t('fields.lastCounter'),
            textAlign: 'right',
            render: (p) =>
              p.last_counter ? (
                <Stack gap={0} align="flex-end">
                  <Text size="sm">{formatNumber(p.last_counter.total)}</Text>
                  <Text size="xs" c="dimmed">
                    {formatDate(p.last_counter.read_at)}
                  </Text>
                </Stack>
              ) : (
                '—'
              ),
          },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (p) => <AssetStatusBadge status={p.status} />,
          },
        ]}
      />
    </>
  )
}
