import { Anchor, Group, Select, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate, formatMoney } from '@/lib/format'
import { NewDocumentMenu } from './NewDocumentMenu'
import { WarehouseSelect } from './Pickers'
import { useDocuments } from './queries'
import { DocumentTypeBadge } from './WarehouseBadges'
import { DOCUMENT_TYPES, type StockDocument } from './types'

export function StockDocumentsPage() {
  const { t } = useTranslation('warehouse')
  const navigate = useNavigate()
  const params = useTableParams({
    defaultSort: '-id',
    filterKeys: ['type', 'warehouse', 'material', 'organization', 'date_from', 'date_to'],
    organizationContext: true,
  })
  const list = useDocuments(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)

  return (
    <>
      <PageHeader
        title={t('documents.title')}
        description={t('documents.description')}
        actions={<NewDocumentMenu />}
      />
      <DataTable<StockDocument>
        storeKey="stock-documents"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('documents.empty')}
        onRowClick={(d) => navigate(`/warehouse/documents/${d.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('filters.documentSearch')}
              aria-label={t('filters.documentSearch')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
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
            <WarehouseSelect
              aria-label={t('filters.warehouse')}
              placeholder={t('filters.allWarehouses')}
              clearable
              value={params.filters.warehouse ? Number(params.filters.warehouse) : null}
              onChange={(v) => params.setFilter('warehouse', v ? String(v) : null)}
              w={{ base: '100%', sm: 240 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'number',
            title: t('fields.number'),
            noWrap: true,
            render: (d) => (
              <Anchor
                component={Link}
                to={`/warehouse/documents/${d.id}`}
                onClick={(e) => e.stopPropagation()}
                size="sm"
                ff="monospace"
                fw={500}
              >
                {d.number}
              </Anchor>
            ),
          },
          { accessor: 'type', title: t('fields.type'), render: (d) => <DocumentTypeBadge type={d.type} /> },
          {
            accessor: 'document_date',
            title: t('fields.date'),
            noWrap: true,
            render: (d) => formatDate(d.document_date),
          },
          {
            accessor: 'warehouse',
            title: t('fields.warehouse'),
            render: (d) => (
              <Text size="sm">
                {d.warehouse.name}
                {d.target_warehouse ? ` → ${d.target_warehouse.name}` : ''}
              </Text>
            ),
          },
          {
            accessor: 'counterparty',
            title: t('fields.counterparty'),
            render: (d) =>
              d.supplier ??
              d.recipient_employee?.name ??
              d.recipient_organization?.name ??
              d.related_document?.number ??
              '—',
          },
          {
            accessor: 'lines_count',
            title: t('fields.lines'),
            textAlign: 'right',
            render: (d) => d.lines_count ?? 0,
          },
          {
            accessor: 'total_amount',
            title: t('fields.amount'),
            textAlign: 'right',
            render: (d) => formatMoney(d.total_amount),
          },
        ]}
      />
    </>
  )
}
