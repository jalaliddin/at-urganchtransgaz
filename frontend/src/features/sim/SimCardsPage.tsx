import { Button, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useProviders } from '@/features/services/queries'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatMoney } from '@/lib/format'
import { useSims } from './queries'
import { SimFormModal } from './SimFormModal'
import { SimStatusBadge } from './SimBadges'
import { formatPhone, SIM_STATUSES, type SimCard } from './types'

export function SimCardsPage() {
  const { t } = useTranslation(['sim', 'common'])
  const navigate = useNavigate()
  const canManage = useCan('sim.manage')
  const canExport = useCan('sim.export')
  const params = useTableParams({
    defaultSort: 'phone_number',
    filterKeys: ['provider', 'status', 'organization'],
    organizationContext: true,
  })
  const list = useSims(params.apiParams)
  const operators = useProviders({ 'filter[mobile]': 1 })
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
            {canExport && <ExportMenu type="sim_cards" filters={params.filters} />}
            {canManage && (
              <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
                {t('form.add')}
              </Button>
            )}
          </Group>
        }
      />
      <DataTable<SimCard>
        storeKey="sim-cards"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty')}
        onRowClick={(c) => navigate(`/sim/${c.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
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
              placeholder={t('fields.operator')}
              aria-label={t('fields.operator')}
              clearable
              data={(operators.data ?? []).map((o) => ({ value: String(o.id), label: o.name }))}
              value={params.filters.provider ?? null}
              onChange={(v) => params.setFilter('provider', v)}
              w={{ base: '100%', sm: 180 }}
            />
            <Select
              placeholder={t('fields.status')}
              aria-label={t('fields.status')}
              clearable
              data={SIM_STATUSES.map((v) => ({ value: v, label: t(`status.${v}`) }))}
              value={params.filters.status ?? null}
              onChange={(v) => params.setFilter('status', v)}
              w={{ base: '100%', sm: 200 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'phone_number',
            title: t('fields.phone'),
            sortable: false,
            noWrap: true,
            render: (c) => (
              <Stack gap={0}>
                <Text size="sm" fw={500}>
                  {formatPhone(c.phone_number)}
                </Text>
                {c.iccid_masked && (
                  <Text size="xs" c="dimmed" ff="monospace">
                    {c.iccid_masked}
                  </Text>
                )}
              </Stack>
            ),
          },
          { accessor: 'provider', title: t('fields.operator'), render: (c) => c.provider.name },
          { accessor: 'organization', title: t('fields.organization'), render: (c) => c.organization.name },
          {
            accessor: 'holder',
            title: t('fields.holder'),
            render: (c) => c.employee?.full_name ?? c.department?.name ?? '—',
          },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (c) => <SimStatusBadge status={c.status} />,
          },
          { accessor: 'tariff', title: t('fields.tariff'), render: (c) => c.tariff ?? '—' },
          {
            accessor: 'monthly_fee',
            title: t('fields.monthlyFee'),
            textAlign: 'right',
            render: (c) => formatMoney(c.monthly_fee),
          },
        ]}
      />
      {creating && <SimFormModal onClose={() => setCreating(false)} />}
    </>
  )
}
