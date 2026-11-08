import { Badge, Button, Group, Select, Stack, Table, Tabs, Text, TextInput, Card } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { CatalogTable } from '@/components/CatalogTable'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useAssetCategories } from '@/features/assets/queries'
import { useCurrentUser } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import * as s from './api'
import { SoftwareFormModal } from './SoftwareFormModal'
import { usePolicies, useSoftwareList, useSoftwareMutation, useUnrecognized } from './queries'
import { SOFTWARE_CATEGORIES, type Software, type Unrecognized } from './types'

function CatalogTab() {
  const { t } = useTranslation('software')
  const navigate = useNavigate()
  const params = useTableParams({ defaultSort: 'name', filterKeys: ['category', 'antivirus'] })
  const list = useSoftwareList(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)

  return (
    <DataTable<Software>
      storeKey="software"
      params={params}
      records={list.data?.data}
      meta={list.data?.meta}
      isLoading={list.isPending}
      isFetching={list.isFetching}
      error={list.error}
      onRetry={() => void list.refetch()}
      emptyTitle={t('catalog.empty')}
      onRowClick={(r) => navigate(`/software/${r.id}`)}
      toolbar={
        <Group gap="sm" wrap="wrap">
          <TextInput
            leftSection={<IconSearch size={16} />}
            placeholder={t('catalog.search')}
            aria-label={t('catalog.search')}
            value={search}
            onChange={(e) => {
              setSearch(e.currentTarget.value)
              pushSearch(e.currentTarget.value)
            }}
            w={{ base: '100%', sm: 260 }}
          />
          <Select
            aria-label={t('fields.category')}
            placeholder={t('fields.category')}
            clearable
            data={SOFTWARE_CATEGORIES.map((c) => ({ value: c, label: t(`categories.${c}`) }))}
            value={params.filters.category ?? null}
            onChange={(v) => params.setFilter('category', v)}
            w={{ base: '100%', sm: 200 }}
          />
        </Group>
      }
      columns={[
        {
          accessor: 'name',
          title: t('fields.name'),
          render: (r) => (
            <Group gap="xs" wrap="nowrap">
              <Text size="sm" fw={500}>
                {r.name}
              </Text>
              {r.is_antivirus && (
                <Badge size="xs" color="cobalt" variant="light" tt="none">
                  {t('catalog.antivirus')}
                </Badge>
              )}
              {!r.is_active && (
                <Badge size="xs" color="gray" variant="light" tt="none">
                  {t('catalog.inactive')}
                </Badge>
              )}
            </Group>
          ),
        },
        { accessor: 'vendor', title: t('fields.vendor'), render: (r) => r.vendor ?? '—' },
        { accessor: 'category', title: t('fields.category'), render: (r) => t(`categories.${r.category}`) },
        {
          accessor: 'license_type',
          title: t('fields.licenseType'),
          render: (r) => t(`licenseTypes.${r.license_type}`),
        },
        {
          accessor: 'installations_count',
          title: t('fields.devices'),
          textAlign: 'right',
          render: (r) => r.installations_count ?? 0,
        },
      ]}
    />
  )
}

function UnrecognizedTab() {
  const { t } = useTranslation(['software', 'common'])
  const canManage = useCan('software.manage')
  const list = useUnrecognized()
  const [adding, setAdding] = useState<Unrecognized | null>(null)
  const adopt = useSoftwareMutation(({ id, name }: { id: number; name: string }) => s.adoptName(id, name))
  const rows = list.data ?? []

  return (
    <Card withBorder p={0}>
      <Text size="sm" c="dimmed" p="md">
        {t('unrecognized.hint')}
      </Text>
      {rows.length === 0 ? (
        <Text size="sm" p="md" pt={0}>
          {t('unrecognized.empty')}
        </Text>
      ) : (
        <Table.ScrollContainer minWidth={480}>
          <Table verticalSpacing="xs">
            <Table.Thead>
              <Table.Tr>
                <Table.Th>{t('fields.name')}</Table.Th>
                <Table.Th>{t('fields.vendor')}</Table.Th>
                <Table.Th ta="right">{t('fields.devices')}</Table.Th>
                {canManage && <Table.Th />}
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {rows.map((r) => (
                <Table.Tr key={r.normalized_name}>
                  <Table.Td>{r.name}</Table.Td>
                  <Table.Td>{r.vendor ?? '—'}</Table.Td>
                  <Table.Td ta="right">{r.devices}</Table.Td>
                  {canManage && (
                    <Table.Td>
                      <Button
                        size="compact-sm"
                        variant="light"
                        leftSection={<IconPlus size={14} />}
                        onClick={() => setAdding(r)}
                      >
                        {t('unrecognized.add')}
                      </Button>
                    </Table.Td>
                  )}
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      )}
      {adding && (
        <SoftwareFormModal
          initial={{ name: adding.name, vendor: adding.vendor }}
          onClose={(created) => {
            setAdding(null)
            if (created)
              adopt.mutate(
                { id: created.id, name: adding.normalized_name },
                {
                  onSuccess: (r) =>
                    notifications.show({
                      color: 'green',
                      message: t('unrecognized.linked', { count: r.linked }),
                    }),
                  onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
                },
              )
          }}
        />
      )}
    </Card>
  )
}

function PoliciesTab() {
  const { t } = useTranslation('software')
  const canManage = useCan('software.manage')
  // A policy without an organization covers every organization: only unrestricted users may set one.
  const unrestricted = useCurrentUser().is_super_admin
  const policies = usePolicies()
  const catalog = useSoftwareList({ per_page: 100, 'filter[active]': 1 })
  const categories = useAssetCategories()

  return (
    <Stack>
      <Text size="sm" c="dimmed">
        {t('policies.hint')}
      </Text>
      <CatalogTable
        canEdit={canManage}
        invalidate={[['software'], ['dashboard']]}
        deleteConfirm={(name) => t('policies.deleteConfirm', { name })}
        headers={[t('fields.name'), t('policies.organization'), t('policies.category'), t('fields.note')]}
        addLabel={t('policies.add')}
        rows={(policies.data ?? []).map((p) => ({
          id: p.id,
          name: p.software.name,
          cells: [
            p.software.name,
            p.organization?.name ?? t('policies.all'),
            p.asset_category?.name ?? t('policies.computers'),
            p.note ?? '—',
          ],
          values: {
            software_id: p.software.id,
            organization_id: p.organization?.id ?? null,
            asset_category_id: p.asset_category?.id ?? null,
            note: p.note,
          },
        }))}
        fields={[
          {
            key: 'software_id',
            label: t('fields.name'),
            kind: 'select',
            required: true,
            data: (catalog.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.name })),
          },
          {
            key: 'organization_id',
            label: unrestricted ? t('policies.organizationHint') : t('policies.organization'),
            kind: 'organization',
            required: !unrestricted,
          },
          {
            key: 'asset_category_id',
            label: t('policies.category'),
            kind: 'select',
            data: (categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label })),
          },
          { key: 'note', label: t('fields.note'), kind: 'text' },
        ]}
        save={(v, id) => s.savePolicy(v, id)}
        remove={s.deletePolicy}
      />
    </Stack>
  )
}

export function SoftwarePage() {
  const { t } = useTranslation('software')
  const canManage = useCan('software.manage')
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          canManage && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
              {t('catalog.add')}
            </Button>
          )
        }
      />
      <Tabs defaultValue="catalog" keepMounted={false}>
        <Tabs.List mb="md">
          <Tabs.Tab value="catalog">{t('tabs.catalog')}</Tabs.Tab>
          <Tabs.Tab value="unrecognized">{t('tabs.unrecognized')}</Tabs.Tab>
          <Tabs.Tab value="policies">{t('tabs.policies')}</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="catalog">
          <CatalogTab />
        </Tabs.Panel>
        <Tabs.Panel value="unrecognized">
          <UnrecognizedTab />
        </Tabs.Panel>
        <Tabs.Panel value="policies">
          <PoliciesTab />
        </Tabs.Panel>
      </Tabs>
      {creating && <SoftwareFormModal onClose={() => setCreating(false)} />}
    </>
  )
}
