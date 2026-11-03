import { ActionIcon, Badge, Button, Group, Text, TextInput, Tooltip } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconEdit, IconPlus, IconSearch, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { confirmAction } from '@/components/confirm'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { DepartmentFormDrawer } from './DepartmentFormDrawer'
import { useDeleteDepartment, useDepartments } from './queries'
import type { Department } from './types'

interface Props {
  storeKey: string
  /** Fixed organization (organization page tab); otherwise filters come from the URL. */
  organizationId?: number
}

export function DepartmentsTable({ storeKey, organizationId }: Props) {
  const { t } = useTranslation(['departments', 'common'])
  const params = useTableParams({
    defaultSort: 'sort_order,name',
    filterKeys: ['organization'],
    organizationContext: !organizationId,
  })
  const apiParams = organizationId
    ? { ...params.apiParams, 'filter[organization]': organizationId }
    : params.apiParams
  const list = useDepartments(apiParams)
  const remove = useDeleteDepartment()
  const canCreate = useCan('departments.create')
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [editing, setEditing] = useState<Department | null>(null)
  const [creating, setCreating] = useState(false)

  const onDelete = async (department: Department) => {
    const ok = await confirmAction({
      message: t('delete.message', { name: department.name }),
      confirmLabel: t('actions.delete'),
      danger: true,
    })
    if (!ok) return
    try {
      await remove.mutateAsync(department.id)
      notifications.show({ color: 'teal', message: t('delete.done') })
    } catch (error) {
      notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

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
        w={{ base: '100%', sm: 260 }}
      />
      {!organizationId && (
        <OrganizationSelect
          aria-label={t('filters.organization')}
          placeholder={t('filters.allOrganizations')}
          value={params.filters.organization ? Number(params.filters.organization) : null}
          onChange={(value) => params.setFilter('organization', value === null ? null : String(value))}
          w={{ base: '100%', sm: 280 }}
        />
      )}
      {canCreate && (
        <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
          {t('actions.create')}
        </Button>
      )}
    </>
  )

  return (
    <>
      <DataTable<Department>
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
        columns={[
          {
            accessor: 'name',
            title: t('fields.name'),
            sortable: true,
            render: (d) => (
              <Text size="sm" fw={500}>
                {d.name}
              </Text>
            ),
          },
          {
            accessor: 'code',
            title: t('fields.code'),
            sortable: true,
            noWrap: true,
            render: (d) => (
              <Text ff="monospace" size="sm">
                {d.code ?? '—'}
              </Text>
            ),
          },
          ...(organizationId
            ? []
            : [
                {
                  accessor: 'organization',
                  title: t('fields.organization'),
                  render: (d: Department) => d.organization?.name ?? '—',
                },
              ]),
          { accessor: 'parent', title: t('fields.parent'), render: (d) => d.parent?.name ?? '—' },
          { accessor: 'employees_count', title: t('fields.employees'), textAlign: 'right' },
          {
            accessor: 'is_active',
            title: t('fields.is_active'),
            width: 120,
            noWrap: true,
            render: (d) => (
              <Badge color={d.is_active ? 'green' : 'red'} variant="light" tt="none">
                {d.is_active ? t('common:labels.active') : t('common:labels.inactive')}
              </Badge>
            ),
          },
          {
            accessor: 'actions',
            title: '',
            width: 96,
            textAlign: 'right',
            render: (d) => (
              <Group gap={4} justify="flex-end" wrap="nowrap">
                {d.can.update && (
                  <Tooltip label={t('actions.edit')}>
                    <ActionIcon
                      variant="subtle"
                      color="gray"
                      aria-label={`${t('actions.edit')}: ${d.name}`}
                      onClick={() => setEditing(d)}
                    >
                      <IconEdit size={16} />
                    </ActionIcon>
                  </Tooltip>
                )}
                {d.can.delete && (
                  <Tooltip label={t('actions.delete')}>
                    <ActionIcon
                      variant="subtle"
                      color="red"
                      aria-label={`${t('actions.delete')}: ${d.name}`}
                      onClick={() => void onDelete(d)}
                    >
                      <IconTrash size={16} />
                    </ActionIcon>
                  </Tooltip>
                )}
              </Group>
            ),
          },
        ]}
      />
      <DepartmentFormDrawer
        opened={creating}
        organizationId={organizationId ?? null}
        onClose={() => setCreating(false)}
      />
      <DepartmentFormDrawer
        opened={editing !== null}
        department={editing ?? undefined}
        onClose={() => setEditing(null)}
      />
    </>
  )
}
