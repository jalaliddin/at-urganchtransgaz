import { Anchor, Button, Select, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { DepartmentSelect } from '@/components/DepartmentSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { StatusBadge } from '@/components/StatusBadge'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { EmployeeFormDrawer } from './EmployeeFormDrawer'
import { useEmployees } from './queries'
import { EMPLOYEE_STATUSES } from './schemas'
import type { Employee } from './types'

interface Props {
  storeKey: string
  /** Fixed organization subtree (organization page tab). */
  organizationId?: number
}

export function EmployeesTable({ storeKey, organizationId }: Props) {
  const { t } = useTranslation(['employees', 'common'])
  const navigate = useNavigate()
  const params = useTableParams({
    defaultSort: 'last_name',
    filterKeys: ['organization', 'department', 'status'],
    organizationContext: !organizationId,
  })
  const apiParams = organizationId
    ? { ...params.apiParams, 'filter[organization]': organizationId }
    : params.apiParams
  const list = useEmployees(apiParams)
  const canCreate = useCan('employees.create')
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState(false)
  const filterOrganization =
    organizationId ?? (params.filters.organization ? Number(params.filters.organization) : null)

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
        w={{ base: '100%', sm: 280 }}
      />
      {!organizationId && (
        <OrganizationSelect
          aria-label={t('filters.organization')}
          placeholder={t('filters.organization')}
          value={filterOrganization}
          onChange={(value) =>
            params.setFilters({ organization: value === null ? null : String(value), department: null })
          }
          w={{ base: '100%', sm: 240 }}
        />
      )}
      {filterOrganization !== null && (
        <DepartmentSelect
          aria-label={t('filters.department')}
          placeholder={t('filters.department')}
          organizationId={filterOrganization}
          value={params.filters.department ? Number(params.filters.department) : null}
          onChange={(value) => params.setFilter('department', value === null ? null : String(value))}
          w={{ base: '100%', sm: 220 }}
        />
      )}
      <Select
        aria-label={t('filters.status')}
        placeholder={t('filters.allStatuses')}
        data={EMPLOYEE_STATUSES.map((s) => ({ value: s, label: t(`common:status.${s}`) }))}
        value={params.filters.status ?? null}
        onChange={(value) => params.setFilter('status', value)}
        clearable
        w={{ base: '100%', sm: 180 }}
      />
      {canCreate && (
        <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
          {t('actions.create')}
        </Button>
      )}
    </>
  )

  return (
    <>
      <DataTable<Employee>
        storeKey={storeKey}
        params={params}
        toolbar={toolbar}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty.title')}
        emptyDescription={t('empty.description')}
        onRowClick={(e) => navigate(`/employees/${e.id}`)}
        columns={[
          {
            accessor: 'last_name',
            title: t('fields.full_name'),
            sortable: true,
            noWrap: true,
            render: (e) => (
              <Anchor
                component={Link}
                to={`/employees/${e.id}`}
                onClick={(ev) => ev.stopPropagation()}
                size="sm"
                fw={500}
              >
                {e.full_name}
              </Anchor>
            ),
          },
          { accessor: 'position', title: t('fields.position'), render: (e) => e.position ?? '—' },
          ...(organizationId
            ? []
            : [
                {
                  accessor: 'organization',
                  title: t('fields.organization'),
                  render: (e: Employee) => e.organization?.name ?? '—',
                },
              ]),
          { accessor: 'department', title: t('fields.department'), render: (e) => e.department?.name ?? '—' },
          {
            accessor: 'personnel_number',
            title: t('fields.personnel_number'),
            sortable: true,
            noWrap: true,
            render: (e) => (
              <Text ff="monospace" size="sm">
                {e.personnel_number ?? '—'}
              </Text>
            ),
          },
          { accessor: 'phone', title: t('fields.phone'), noWrap: true, render: (e) => e.phone ?? '—' },
          {
            accessor: 'status',
            title: t('fields.status'),
            sortable: true,
            width: 150,
            noWrap: true,
            render: (e) => <StatusBadge status={e.status} />,
          },
        ]}
      />
      <EmployeeFormDrawer
        opened={creating}
        organizationId={organizationId ?? null}
        onClose={(saved) => {
          setCreating(false)
          if (saved) navigate(`/employees/${saved.id}`)
        }}
      />
    </>
  )
}
