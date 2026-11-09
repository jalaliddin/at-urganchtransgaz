import { Button, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate } from '@/lib/format'
import { ContractModal } from './ContractModal'
import { useContracts, useProviders } from './queries'
import { ContractStatusBadge } from './ServiceBadges'
import type { Contract } from './types'

const STATUSES = ['active', 'expiring', 'expired', 'terminated'] as const

export function ContractsPage() {
  const { t } = useTranslation(['services', 'common'])
  const navigate = useNavigate()
  const canManage = useCan('services.manage')
  const params = useTableParams({
    defaultSort: '-valid_to',
    filterKeys: ['status', 'provider', 'organization'],
    organizationContext: true,
  })
  const list = useContracts(params.apiParams)
  const providers = useProviders()
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title={t('contracts.title')}
        description={t('contracts.description')}
        actions={
          canManage && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
              {t('contracts.add')}
            </Button>
          )
        }
      />
      <DataTable<Contract>
        storeKey="service-contracts"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('contracts.empty')}
        onRowClick={(c) => navigate(`/services/contracts/${c.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('contracts.search')}
              aria-label={t('contracts.search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 240 }}
            />
            <Select
              placeholder={t('fields.provider')}
              aria-label={t('fields.provider')}
              clearable
              searchable
              data={(providers.data ?? []).map((p) => ({ value: String(p.id), label: p.name }))}
              value={params.filters.provider ?? null}
              onChange={(v) => params.setFilter('provider', v)}
              w={{ base: '100%', sm: 200 }}
            />
            <Select
              placeholder={t('fields.status')}
              aria-label={t('fields.status')}
              clearable
              data={STATUSES.map((v) => ({ value: v, label: t(`contractFilter.${v}`) }))}
              value={params.filters.status ?? null}
              onChange={(v) => params.setFilter('status', v)}
              w={{ base: '100%', sm: 200 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'number',
            title: t('contracts.number'),
            render: (c) => (
              <Stack gap={0}>
                <Text size="sm" fw={500}>
                  {c.number}
                </Text>
                {c.subject && (
                  <Text size="xs" c="dimmed">
                    {c.subject}
                  </Text>
                )}
              </Stack>
            ),
          },
          { accessor: 'provider', title: t('fields.provider'), render: (c) => c.provider.name },
          { accessor: 'organization', title: t('fields.organization'), render: (c) => c.organization.name },
          {
            accessor: 'valid_to',
            title: t('contracts.validTo'),
            noWrap: true,
            render: (c) => (c.valid_to ? formatDate(c.valid_to) : '—'),
          },
          { accessor: 'services_count', title: t('contracts.services'), textAlign: 'right' },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (c) => <ContractStatusBadge status={c.status} />,
          },
        ]}
      />
      {creating && <ContractModal onClose={() => setCreating(false)} />}
    </>
  )
}
