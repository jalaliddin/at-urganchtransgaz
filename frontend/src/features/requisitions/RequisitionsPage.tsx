import { Anchor, Button, Group, SegmentedControl, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate, formatDateTime } from '@/lib/format'
import { RequisitionFormDrawer } from './RequisitionFormDrawer'
import { RequisitionStatusBadge } from './RequisitionBadges'
import { useRequisitions } from './queries'
import { REQUISITION_STATUSES, type RequisitionRow } from './types'

export function RequisitionsPage() {
  const { t } = useTranslation('requisitions')
  const navigate = useNavigate()
  const canCreate = useCan('requisitions.create')
  const canExport = useCan('requisitions.export')
  const params = useTableParams({
    defaultSort: '-updated_at',
    filterKeys: ['tab', 'status', 'organization'],
    organizationContext: true,
  })
  const list = useRequisitions(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState(false)

  const exportFilters: Record<string, string> = { ...params.filters }
  if (params.search) exportFilters.search = params.search

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <Group gap="sm">
            {canExport && <ExportMenu type="requisitions" filters={exportFilters} />}
            {canCreate && (
              <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
                {t('actions.create')}
              </Button>
            )}
          </Group>
        }
      />
      <DataTable<RequisitionRow>
        storeKey="requisitions"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty.title')}
        emptyDescription={t('empty.description')}
        onRowClick={(r) => navigate(`/requisitions/${r.id}`)}
        toolbar={
          <Stack gap="sm" w="100%">
            <SegmentedControl
              aria-label={t('tabs.label')}
              value={params.filters.tab ?? 'all'}
              onChange={(v) => params.setFilter('tab', v === 'all' ? null : v)}
              data={[
                { value: 'action', label: t('tabs.action') },
                { value: 'mine', label: t('tabs.mine') },
                { value: 'all', label: t('tabs.all') },
              ]}
              w={{ base: '100%', sm: 'auto' }}
            />
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
                w={{ base: '100%', sm: 280 }}
              />
              <Select
                aria-label={t('filters.status')}
                placeholder={t('filters.allStatuses')}
                clearable
                data={REQUISITION_STATUSES.map((s) => ({ value: s, label: t(`status.${s}`) }))}
                value={params.filters.status ?? null}
                onChange={(v) => params.setFilter('status', v)}
                w={{ base: '100%', sm: 240 }}
              />
            </Group>
          </Stack>
        }
        columns={[
          {
            accessor: 'number',
            title: t('fields.number'),
            noWrap: true,
            render: (r) => (
              <Anchor
                component={Link}
                to={`/requisitions/${r.id}`}
                onClick={(e) => e.stopPropagation()}
                size="sm"
                ff="monospace"
                fw={500}
              >
                {r.number}
              </Anchor>
            ),
          },
          {
            accessor: 'title',
            title: t('fields.title'),
            render: (r) => (
              <Stack gap={0}>
                <Text size="sm">{r.title}</Text>
                <Text size="xs" c="dimmed">
                  {r.organization.name}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (r) => <RequisitionStatusBadge status={r.status} />,
          },
          { accessor: 'requester', title: t('fields.requester'), render: (r) => r.requester?.name ?? '—' },
          {
            accessor: 'needed_by',
            title: t('fields.neededBy'),
            noWrap: true,
            render: (r) => formatDate(r.needed_by),
          },
          {
            accessor: 'updated_at',
            title: t('fields.updatedAt'),
            noWrap: true,
            render: (r) => formatDateTime(r.updated_at),
          },
        ]}
      />
      {creating && (
        <RequisitionFormDrawer
          onClose={(created) => {
            setCreating(false)
            if (created) navigate(`/requisitions/${created.id}`)
          }}
        />
      )}
    </>
  )
}
