import { Checkbox, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'
import dayjs from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate, formatMoney } from '@/lib/format'
import { NewDocumentMenu } from './NewDocumentMenu'
import { WarehouseSelect } from './Pickers'
import { useBalances, useMaterialCategories } from './queries'
import { ExpiryBadge, LowStockBadge, qty } from './WarehouseBadges'
import type { BalanceRow } from './types'

const FILTER_KEYS = ['warehouse', 'category', 'material', 'low', 'expiring', 'organization']
const NOTICE_DAYS = 30

export function BalancesPage() {
  const { t } = useTranslation('warehouse')
  const params = useTableParams({
    defaultSort: 'material',
    filterKeys: FILTER_KEYS,
    organizationContext: true,
  })
  const list = useBalances(params.apiParams)
  const categories = useMaterialCategories()
  const canExport = useCan('warehouse.export')
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const soon = (date: string | null) => date !== null && dayjs(date).diff(dayjs(), 'day') <= NOTICE_DAYS

  const exportFilters: Record<string, string> = { ...params.filters }
  if (params.search) exportFilters.search = params.search

  return (
    <>
      <PageHeader
        title={t('balances.title')}
        description={t('balances.description')}
        actions={
          <Group gap="sm">
            {canExport && <ExportMenu type="stock_balances" filters={exportFilters} />}
            <NewDocumentMenu />
          </Group>
        }
      />
      <DataTable<BalanceRow>
        storeKey="stock-balances"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('balances.empty')}
        emptyDescription={t('balances.emptyHint')}
        toolbar={
          <Group gap="sm" wrap="wrap">
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
            <WarehouseSelect
              aria-label={t('filters.warehouse')}
              placeholder={t('filters.allWarehouses')}
              clearable
              value={params.filters.warehouse ? Number(params.filters.warehouse) : null}
              onChange={(v) => params.setFilter('warehouse', v ? String(v) : null)}
              w={{ base: '100%', sm: 240 }}
            />
            <Select
              aria-label={t('filters.category')}
              placeholder={t('filters.category')}
              clearable
              data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
              value={params.filters.category ?? null}
              onChange={(v) => params.setFilter('category', v)}
              w={{ base: '100%', sm: 220 }}
            />
            <Checkbox
              label={t('filters.low')}
              checked={params.filters.low === '1'}
              onChange={(e) => params.setFilter('low', e.currentTarget.checked ? '1' : null)}
            />
            <Checkbox
              label={t('filters.expiring')}
              checked={params.filters.expiring === '1'}
              onChange={(e) => params.setFilter('expiring', e.currentTarget.checked ? '1' : null)}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'material',
            title: t('fields.material'),
            render: (r) => (
              <Stack gap={0}>
                <Text size="sm" fw={500}>
                  {r.material.name}
                </Text>
                <Text size="xs" c="dimmed">
                  <Text span size="xs" ff="monospace">
                    {r.material.code}
                  </Text>{' '}
                  · {r.material.category}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'warehouse',
            title: t('fields.warehouse'),
            render: (r) => (
              <Stack gap={0}>
                <Text size="sm">{r.warehouse.name}</Text>
                <Text size="xs" c="dimmed">
                  {r.warehouse.organization.name}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'quantity',
            title: t('fields.quantity'),
            textAlign: 'right',
            render: (r) => (
              <Stack gap={2} align="flex-end">
                <Text size="sm" ff="monospace" fw={600}>
                  {qty(r.quantity)} {r.material.unit}
                </Text>
                {r.is_low && <LowStockBadge />}
              </Stack>
            ),
          },
          {
            accessor: 'min_quantity',
            title: t('fields.minQuantity'),
            textAlign: 'right',
            render: (r) => (Number(r.min_quantity) > 0 ? qty(r.min_quantity) : '—'),
          },
          {
            accessor: 'value',
            title: t('fields.value'),
            textAlign: 'right',
            render: (r) => formatMoney(r.value),
          },
          {
            accessor: 'lots',
            title: t('fields.lots'),
            render: (r) =>
              r.material.track_batches ? (
                <Stack gap={2}>
                  {r.lots.map((l) => (
                    <Text key={l.id} size="xs" ff="monospace">
                      {l.batch}: {qty(l.quantity)}
                      {l.expires_at ? ` · ${formatDate(l.expires_at)}` : ''}
                    </Text>
                  ))}
                </Stack>
              ) : (
                '—'
              ),
          },
          {
            accessor: 'nearest_expiry',
            title: t('fields.nearestExpiry'),
            render: (r) =>
              r.nearest_expiry ? (
                <Stack gap={2}>
                  <Text size="sm">{formatDate(r.nearest_expiry)}</Text>
                  <ExpiryBadge date={formatDate(r.nearest_expiry)} soon={soon(r.nearest_expiry)} />
                </Stack>
              ) : (
                '—'
              ),
          },
        ]}
      />
    </>
  )
}
