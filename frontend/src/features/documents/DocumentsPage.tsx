import { Anchor, Button, Select, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate } from '@/lib/format'
import { DOCUMENT_TYPES, type DocumentRecord } from './api'
import { DocumentFormModal } from './DocumentFormModal'
import { useDocuments } from './queries'

export function DocumentsPage() {
  const { t } = useTranslation('documents')
  const navigate = useNavigate()
  const canManage = useCan('documents.manage')
  const params = useTableParams({
    defaultSort: '-document_date',
    filterKeys: ['type', 'organization'],
    organizationContext: true,
  })
  const list = useDocuments(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          canManage && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
              {t('actions.create')}
            </Button>
          )
        }
      />
      <DataTable<DocumentRecord>
        storeKey="documents"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty.title')}
        emptyDescription={t('empty.description')}
        onRowClick={(d) => navigate(`/documents/${d.id}`)}
        toolbar={
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
              w={{ base: '100%', sm: 260 }}
            />
            <Select
              aria-label={t('filters.type')}
              placeholder={t('filters.allTypes')}
              data={DOCUMENT_TYPES.map((v) => ({ value: v, label: t(`types.${v}`) }))}
              value={params.filters.type ?? null}
              onChange={(v) => params.setFilter('type', v)}
              clearable
              w={{ base: '100%', sm: 200 }}
            />
            <OrganizationSelect
              aria-label={t('filters.organization')}
              placeholder={t('filters.organization')}
              value={params.filters.organization ? Number(params.filters.organization) : null}
              onChange={(v) => params.setFilter('organization', v === null ? null : String(v))}
              w={{ base: '100%', sm: 220 }}
            />
          </>
        }
        columns={[
          {
            accessor: 'title',
            title: t('fields.title'),
            sortable: true,
            render: (d) => (
              <Anchor
                component={Link}
                to={`/documents/${d.id}`}
                onClick={(e) => e.stopPropagation()}
                size="sm"
                fw={500}
              >
                {d.title}
              </Anchor>
            ),
          },
          { accessor: 'type', title: t('fields.type'), render: (d) => t(`types.${d.type}`) },
          {
            accessor: 'number',
            title: t('fields.number'),
            sortable: true,
            render: (d) => (
              <Text size="sm" ff="monospace">
                {d.number ?? '—'}
              </Text>
            ),
          },
          {
            accessor: 'document_date',
            title: t('fields.date'),
            sortable: true,
            noWrap: true,
            render: (d) => formatDate(d.document_date),
          },
          {
            accessor: 'organization',
            title: t('fields.organization'),
            render: (d) => d.organization?.name ?? '—',
          },
          {
            accessor: 'current_version',
            title: t('fields.version'),
            render: (d) => (d.current_version ? `v${d.current_version.version}` : '—'),
          },
        ]}
      />
      {creating && (
        <DocumentFormModal
          onClose={(saved) => {
            setCreating(false)
            if (saved) navigate(`/documents/${saved.id}`)
          }}
        />
      )}
    </>
  )
}
