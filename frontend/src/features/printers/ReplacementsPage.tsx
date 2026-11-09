import { Anchor, Button, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate, formatMoney, formatNumber } from '@/lib/format'
import { CancelledBadge } from './PrinterBadges'
import { CancelReplacementModal } from './PrinterDialogs'
import { useCartridges, useReplacements } from './queries'
import type { Replacement } from './types'

/** Which cartridge went into which printer, across the user's organizations. */
export function ReplacementsPage() {
  const { t } = useTranslation(['printers', 'common'])
  const canExport = useCan('printers.export')
  const params = useTableParams({
    defaultSort: 'replaced_at',
    filterKeys: ['source', 'cancelled', 'cartridge', 'printer', 'organization', 'date_from', 'date_to'],
    organizationContext: true,
  })
  const list = useReplacements(params.apiParams)
  const cartridges = useCartridges({ per_page: 100 })
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [cancelling, setCancelling] = useState<Replacement | null>(null)

  return (
    <>
      <PageHeader
        title={t('replacements.title')}
        description={t('replacements.description')}
        actions={
          canExport && (
            <ExportMenu
              type="cartridge_replacements"
              filters={{ ...params.filters, search: params.search }}
            />
          )
        }
      />
      <DataTable<Replacement>
        storeKey="cartridge-replacements"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('replacements.empty')}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('replacements.search')}
              aria-label={t('replacements.search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 240 }}
            />
            <Select
              placeholder={t('fields.cartridge')}
              aria-label={t('fields.cartridge')}
              clearable
              searchable
              data={(cartridges.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
              value={params.filters.cartridge ?? null}
              onChange={(v) => params.setFilter('cartridge', v)}
              w={{ base: '100%', sm: 200 }}
            />
            <Select
              placeholder={t('fields.source')}
              aria-label={t('fields.source')}
              clearable
              data={(['warehouse', 'other'] as const).map((v) => ({ value: v, label: t(`source.${v}`) }))}
              value={params.filters.source ?? null}
              onChange={(v) => params.setFilter('source', v)}
              w={{ base: '100%', sm: 180 }}
            />
            <Select
              placeholder={t('replacements.state')}
              aria-label={t('replacements.state')}
              clearable
              data={[
                { value: '0', label: t('replacements.activeOnly') },
                { value: '1', label: t('replacements.cancelledOnly') },
              ]}
              value={params.filters.cancelled ?? null}
              onChange={(v) => params.setFilter('cancelled', v)}
              w={{ base: '100%', sm: 180 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'replaced_at',
            title: t('fields.replacedAt'),
            sortable: false,
            noWrap: true,
            render: (r) => formatDate(r.replaced_at),
          },
          {
            accessor: 'printer',
            title: t('fields.printer'),
            render: (r) => (
              <Stack gap={0}>
                <Anchor
                  component={Link}
                  to={`/printers/${r.printer.id}`}
                  size="sm"
                  onClick={(e) => e.stopPropagation()}
                >
                  {r.printer.name}
                </Anchor>
                <Text size="xs" c="dimmed">
                  <Text span ff="monospace" inherit>
                    {r.printer.inventory_number}
                  </Text>
                  , {r.organization.name}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'cartridge',
            title: t('fields.cartridge'),
            render: (r) => (
              <Stack gap={0}>
                <Text size="sm">{r.cartridge.name}</Text>
                {r.cartridge.part_number && (
                  <Text size="xs" c="dimmed" ff="monospace">
                    {r.cartridge.part_number}
                  </Text>
                )}
              </Stack>
            ),
          },
          {
            accessor: 'source',
            title: t('fields.source'),
            render: (r) => (
              <Stack gap={0}>
                <Text size="sm">{r.warehouse?.name ?? t(`source.${r.source}`)}</Text>
                {r.stock_document && (
                  <Anchor
                    component={Link}
                    to={`/warehouse/documents/${r.stock_document.id}`}
                    size="xs"
                    onClick={(e) => e.stopPropagation()}
                  >
                    {r.stock_document.number}
                  </Anchor>
                )}
              </Stack>
            ),
          },
          {
            accessor: 'cost',
            title: t('fields.cost'),
            textAlign: 'right',
            render: (r) => formatMoney(r.cost),
          },
          {
            accessor: 'counter',
            title: t('fields.counter'),
            textAlign: 'right',
            render: (r) => (r.counter ? formatNumber(r.counter.total) : '—'),
          },
          {
            accessor: 'performed_by',
            title: t('fields.performedBy'),
            render: (r) => r.performed_by?.name ?? '—',
          },
          {
            accessor: 'actions',
            title: '',
            textAlign: 'right',
            render: (r) =>
              r.cancelled_at ? (
                <CancelledBadge />
              ) : (
                r.can_cancel && (
                  <Button size="compact-xs" variant="subtle" color="red" onClick={() => setCancelling(r)}>
                    {t('cancel.action')}
                  </Button>
                )
              ),
          },
        ]}
      />
      {cancelling && <CancelReplacementModal replacement={cancelling} onClose={() => setCancelling(null)} />}
    </>
  )
}
