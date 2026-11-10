import { Button, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { ChangeRiskBadge, ChangeStatusBadge, ChangeTypeBadge } from './ChangeBadges'
import { ChangeFormModal } from './ChangeFormModal'
import { useChanges } from './queries'
import { CHANGE_CATEGORIES, CHANGE_STATUSES, CHANGE_TYPES, type ChangeRow } from './types'

/** Planned network, server and configuration changes of the user's organizations. */
export function ChangesPage() {
  const { t } = useTranslation(['changes', 'common'])
  const navigate = useNavigate()
  const canCreate = useCan('changes.create')
  const canExport = useCan('changes.export')
  const params = useTableParams({
    defaultSort: 'planned_start',
    filterKeys: ['status', 'type', 'category', 'organization'],
    organizationContext: true,
  })
  const list = useChanges(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <Group gap="sm">
            {canExport && <ExportMenu type="changes" filters={params.filters} />}
            {canCreate && (
              <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
                {t('form.new')}
              </Button>
            )}
          </Group>
        }
      />
      <DataTable<ChangeRow>
        storeKey="changes"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty')}
        onRowClick={(c) => navigate(`/changes/${c.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('search')}
              aria-label={t('search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 240 }}
            />
            <Select
              placeholder={t('fields.status')}
              aria-label={t('fields.status')}
              clearable
              data={CHANGE_STATUSES.map((v) => ({ value: v, label: t(`status.${v}`) }))}
              value={params.filters.status ?? null}
              onChange={(v) => params.setFilter('status', v)}
              w={{ base: '100%', sm: 190 }}
            />
            <Select
              placeholder={t('fields.type')}
              aria-label={t('fields.type')}
              clearable
              data={CHANGE_TYPES.map((v) => ({ value: v, label: t(`type.${v}`) }))}
              value={params.filters.type ?? null}
              onChange={(v) => params.setFilter('type', v)}
              w={{ base: '100%', sm: 160 }}
            />
            <Select
              placeholder={t('fields.category')}
              aria-label={t('fields.category')}
              clearable
              data={CHANGE_CATEGORIES.map((v) => ({ value: v, label: t(`category.${v}`) }))}
              value={params.filters.category ?? null}
              onChange={(v) => params.setFilter('category', v)}
              w={{ base: '100%', sm: 170 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'number',
            title: t('fields.change'),
            sortable: false,
            render: (c) => (
              <Stack gap={2}>
                <Group gap={6}>
                  <Text size="sm" fw={500}>
                    {c.title}
                  </Text>
                  <ChangeTypeBadge type={c.type} />
                </Group>
                <Text size="xs" c="dimmed">
                  <Text span ff="monospace" inherit>
                    {c.number}
                  </Text>
                  , {c.organization.name}, {t(`category.${c.category}`)}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'planned_start',
            title: t('fields.window'),
            noWrap: true,
            render: (c) => (c.planned_start ? formatDateTime(c.planned_start) : '—'),
          },
          { accessor: 'risk', title: t('fields.risk'), render: (c) => <ChangeRiskBadge risk={c.risk} /> },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (c) => <ChangeStatusBadge status={c.status} />,
          },
          {
            accessor: 'implementer',
            title: t('fields.implementer'),
            render: (c) => c.implementer?.name ?? '—',
          },
          {
            accessor: 'assets_count',
            title: t('fields.assets'),
            textAlign: 'right',
            render: (c) => c.assets_count,
          },
        ]}
      />
      {creating && <ChangeFormModal onClose={() => setCreating(false)} />}
    </>
  )
}
