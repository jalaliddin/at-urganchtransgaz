import { Anchor, Group, Select, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime, formatMoney } from '@/lib/format'
import { MaterialSelect, WarehouseSelect } from './Pickers'
import { useMovements } from './queries'
import { DocumentTypeBadge, qty } from './WarehouseBadges'
import { DOCUMENT_TYPES, type JournalRow } from './types'

export function MovementsPage() {
  const { t } = useTranslation('warehouse')
  const params = useTableParams({
    defaultSort: '-id',
    filterKeys: ['warehouse', 'material', 'type', 'date_from', 'date_to'],
  })
  const list = useMovements(params.apiParams)
  const canExport = useCan('warehouse.export')

  return (
    <>
      <PageHeader
        title={t('movements.title')}
        description={t('movements.description')}
        actions={canExport && <ExportMenu type="stock_movements" filters={params.filters} />}
      />
      <DataTable<JournalRow>
        storeKey="stock-movements"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('movements.empty')}
        toolbar={
          <Group gap="sm" wrap="wrap">
            <WarehouseSelect
              aria-label={t('filters.warehouse')}
              placeholder={t('filters.allWarehouses')}
              clearable
              value={params.filters.warehouse ? Number(params.filters.warehouse) : null}
              onChange={(v) => params.setFilter('warehouse', v ? String(v) : null)}
              w={{ base: '100%', sm: 240 }}
            />
            <MaterialSelect
              aria-label={t('filters.material')}
              placeholder={t('filters.material')}
              value={params.filters.material ? Number(params.filters.material) : null}
              onChange={(m) => params.setFilter('material', m ? String(m.id) : null)}
              w={{ base: '100%', sm: 260 }}
            />
            <Select
              aria-label={t('filters.type')}
              placeholder={t('filters.allTypes')}
              clearable
              data={DOCUMENT_TYPES.map((d) => ({ value: d, label: t(`types.${d}`) }))}
              value={params.filters.type ?? null}
              onChange={(v) => params.setFilter('type', v)}
              w={{ base: '100%', sm: 180 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'created_at',
            title: t('fields.time'),
            noWrap: true,
            render: (m) => formatDateTime(m.created_at),
          },
          {
            accessor: 'document',
            title: t('fields.document'),
            render: (m) => (
              <Group gap="xs" wrap="nowrap">
                <Anchor
                  component={Link}
                  to={`/warehouse/documents/${m.document.id}`}
                  size="sm"
                  ff="monospace"
                >
                  {m.document.number}
                </Anchor>
                <DocumentTypeBadge type={m.document.type} />
              </Group>
            ),
          },
          { accessor: 'warehouse', title: t('fields.warehouse'), render: (m) => m.warehouse.name },
          {
            accessor: 'material',
            title: t('fields.material'),
            render: (m) => (
              <Text size="sm">
                {m.material.name}
                {m.batch ? <Text span size="xs" c="dimmed" ff="monospace">{` · ${m.batch}`}</Text> : null}
              </Text>
            ),
          },
          {
            accessor: 'quantity',
            title: t('fields.quantity'),
            textAlign: 'right',
            render: (m) => (
              <Text size="sm" ff="monospace" fw={600} c={Number(m.quantity) < 0 ? 'orange.8' : 'green.8'}>
                {Number(m.quantity) > 0 ? '+' : ''}
                {qty(m.quantity)}
              </Text>
            ),
          },
          {
            accessor: 'balance_after',
            title: t('fields.balanceAfter'),
            textAlign: 'right',
            render: (m) => qty(m.balance_after),
          },
          {
            accessor: 'unit_cost',
            title: t('fields.unitCost'),
            textAlign: 'right',
            render: (m) => formatMoney(m.unit_cost),
          },
          { accessor: 'user', title: t('fields.user'), render: (m) => m.user?.name ?? '—' },
        ]}
      />
    </>
  )
}
