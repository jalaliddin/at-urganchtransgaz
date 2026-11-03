import {
  Anchor,
  Badge,
  Button,
  SegmentedControl,
  Select,
  Skeleton,
  Text,
  TextInput,
} from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { OrganizationFormDrawer } from './OrganizationFormDrawer'
import { OrganizationTree } from './OrganizationTree'
import { useOrganizations, useOrganizationTree } from './queries'
import type { Organization } from './types'

const TYPES = ['head_office', 'subsidiary', 'branch', 'other'] as const

function TreeView() {
  const { t } = useTranslation('organizations')
  const tree = useOrganizationTree()
  if (tree.isError) return <ErrorState error={tree.error} onRetry={() => void tree.refetch()} />
  if (!tree.data) return <Skeleton height={240} radius="md" />
  if (tree.data.length === 0) return <EmptyState title={t('empty.tree')} />
  return <OrganizationTree nodes={tree.data} />
}

function ListView() {
  const { t } = useTranslation(['organizations', 'common'])
  const navigate = useNavigate()
  const params = useTableParams({ defaultSort: 'sort_order,name', filterKeys: ['type', 'is_active'] })
  const list = useOrganizations(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)

  const toolbar = (
    <>
      <TextInput
        leftSection={<IconSearch size={16} />}
        placeholder={t('filters.search')}
        aria-label={t('filters.search')}
        value={search}
        onChange={(e) => {
          setSearch(e.currentTarget.value)
          pushSearch(e.currentTarget.value)
        }}
        w={{ base: '100%', sm: 300 }}
      />
      <Select
        aria-label={t('filters.type')}
        placeholder={t('filters.allTypes')}
        data={TYPES.map((type) => ({ value: type, label: t(`types.${type}`) }))}
        value={params.filters.type ?? null}
        onChange={(value) => params.setFilter('type', value)}
        clearable
        w={{ base: '100%', sm: 220 }}
      />
      <Select
        aria-label={t('filters.status')}
        placeholder={t('filters.any')}
        data={[
          { value: '1', label: t('filters.active') },
          { value: '0', label: t('filters.inactive') },
        ]}
        value={params.filters.is_active ?? null}
        onChange={(value) => params.setFilter('is_active', value)}
        clearable
        w={{ base: '100%', sm: 160 }}
      />
    </>
  )

  return (
    <DataTable<Organization>
      storeKey="organizations-table"
      params={params}
      records={list.data?.data}
      meta={list.data?.meta}
      isLoading={list.isPending}
      isFetching={list.isFetching}
      error={list.error}
      onRetry={() => void list.refetch()}
      emptyTitle={t('empty.title')}
      emptyDescription={t('empty.description')}
      onRowClick={(org) => navigate(`/organizations/${org.id}`)}
      toolbar={toolbar}
      columns={[
        {
          accessor: 'name',
          title: t('fields.name'),
          sortable: true,
          render: (org) => (
            <Anchor
              component={Link}
              to={`/organizations/${org.id}`}
              onClick={(e) => e.stopPropagation()}
              fw={500}
              size="sm"
            >
              {org.name}
            </Anchor>
          ),
        },
        {
          accessor: 'code',
          title: t('fields.code'),
          sortable: true,
          noWrap: true,
          render: (org) => (
            <Text ff="monospace" size="sm">
              {org.code}
            </Text>
          ),
        },
        { accessor: 'type', title: t('fields.type'), render: (org) => t(`types.${org.type}`) },
        { accessor: 'parent', title: t('fields.parent'), render: (org) => org.parent?.name ?? '—' },
        { accessor: 'children_count', title: t('fields.children'), textAlign: 'right' },
        {
          accessor: 'is_active',
          title: t('fields.is_active'),
          render: (org) =>
            org.is_active ? (
              <Badge color="green" variant="light" tt="none">
                {t('common:labels.active')}
              </Badge>
            ) : (
              <Badge color="red" variant="light" tt="none">
                {t('common:labels.inactive')}
              </Badge>
            ),
        },
      ]}
    />
  )
}

export function OrganizationsPage() {
  const { t } = useTranslation('organizations')
  const canCreate = useCan('organizations.create')
  const [params, setParams] = useSearchParams()
  const view = params.get('view') === 'list' ? 'list' : 'tree'
  const [creating, setCreating] = useState(false)
  const navigate = useNavigate()

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <>
            <SegmentedControl
              value={view}
              onChange={(value) =>
                setParams((prev) => {
                  const next = new URLSearchParams(prev)
                  if (value === 'list') next.set('view', 'list')
                  else next.delete('view')
                  return next
                })
              }
              data={[
                { value: 'tree', label: t('views.tree') },
                { value: 'list', label: t('views.list') },
              ]}
            />
            {canCreate && (
              <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
                {t('actions.create')}
              </Button>
            )}
          </>
        }
      />
      {view === 'list' ? <ListView /> : <TreeView />}
      <OrganizationFormDrawer
        opened={creating}
        onClose={(saved) => {
          setCreating(false)
          if (saved) navigate(`/organizations/${saved.id}`)
        }}
      />
    </>
  )
}
