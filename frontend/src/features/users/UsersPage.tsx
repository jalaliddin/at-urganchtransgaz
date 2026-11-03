import { Anchor, Badge, Button, Group, Select, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { useRoles } from '@/features/roles/queries'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { useUsers } from './queries'
import { TemporaryPasswordModal } from './TemporaryPasswordModal'
import type { User } from './types'
import { CreateUserDrawer } from './UserFormDrawer'

export function UsersPage() {
  const { t } = useTranslation(['users', 'common'])
  const navigate = useNavigate()
  const params = useTableParams({
    defaultSort: 'username',
    filterKeys: ['organization', 'role', 'is_active'],
    organizationContext: true,
  })
  const list = useUsers(params.apiParams)
  const canCreate = useCan('users.create')
  const canViewRoles = useCan('roles.view')
  const roles = useRoles(canViewRoles)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState(false)
  const [created, setCreated] = useState<{ user: User; temporaryPassword: string } | null>(null)

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
      <OrganizationSelect
        aria-label={t('filters.organization')}
        placeholder={t('filters.organization')}
        value={params.filters.organization ? Number(params.filters.organization) : null}
        onChange={(value) => params.setFilter('organization', value === null ? null : String(value))}
        w={{ base: '100%', sm: 240 }}
      />
      {canViewRoles && (
        <Select
          aria-label={t('filters.role')}
          placeholder={t('filters.allRoles')}
          data={(roles.data ?? []).map((r) => ({ value: r.name, label: r.display_name || r.name }))}
          value={params.filters.role ?? null}
          onChange={(value) => params.setFilter('role', value)}
          clearable
          w={{ base: '100%', sm: 220 }}
        />
      )}
      <Select
        aria-label={t('filters.status')}
        placeholder={t('filters.any')}
        data={[
          { value: '1', label: t('status.active') },
          { value: '0', label: t('status.inactive') },
        ]}
        value={params.filters.is_active ?? null}
        onChange={(value) => params.setFilter('is_active', value)}
        clearable
        w={{ base: '100%', sm: 150 }}
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
      <PageHeader title={t('title')} description={t('description')} />
      <DataTable<User>
        storeKey="users-table"
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
        onRowClick={(u) => navigate(`/users/${u.id}`)}
        columns={[
          {
            accessor: 'username',
            title: t('fields.username'),
            sortable: true,
            noWrap: true,
            render: (u) => (
              <Anchor
                component={Link}
                to={`/users/${u.id}`}
                onClick={(e) => e.stopPropagation()}
                ff="monospace"
                size="sm"
                fw={500}
              >
                {u.username}
              </Anchor>
            ),
          },
          { accessor: 'name', title: t('fields.name'), sortable: true },
          {
            accessor: 'organization',
            title: t('fields.organization'),
            render: (u) => u.organization?.name ?? t('system'),
          },
          {
            accessor: 'roles',
            title: t('fields.roles'),
            render: (u) => (
              <Group gap={4}>
                {u.roles.map((r) => (
                  <Badge key={r.name} variant="light" color="gray" tt="none" fw={500}>
                    {r.display_name || r.name}
                  </Badge>
                ))}
              </Group>
            ),
          },
          {
            accessor: 'is_active',
            title: t('fields.status'),
            noWrap: true,
            render: (u) =>
              !u.is_active ? (
                <StatusBadge status="inactive" label={t('status.inactive')} />
              ) : u.must_change_password ? (
                <StatusBadge status="must_change_password" label={t('status.mustChange')} />
              ) : (
                <StatusBadge status="active" label={t('status.active')} />
              ),
          },
          {
            accessor: 'last_login_at',
            title: t('fields.last_login_at'),
            sortable: true,
            noWrap: true,
            render: (u) => (
              <Text size="sm">{u.last_login_at ? formatDateTime(u.last_login_at) : t('never')}</Text>
            ),
          },
        ]}
      />
      <CreateUserDrawer
        opened={creating}
        onClose={(result) => {
          setCreating(false)
          if (result) setCreated(result)
        }}
      />
      <TemporaryPasswordModal
        password={created?.temporaryPassword ?? null}
        username={created?.user.username ?? ''}
        onClose={() => {
          const id = created?.user.id
          setCreated(null)
          if (id) navigate(`/users/${id}`)
        }}
      />
    </>
  )
}
