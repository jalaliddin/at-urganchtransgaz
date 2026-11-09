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
import { formatMoney } from '@/lib/format'
import { useProviders, useServices } from './queries'
import { ServiceModal } from './ServiceModal'
import { SERVICE_TYPES, type ItService } from './types'

export function ServicesPage() {
  const { t } = useTranslation(['services', 'common'])
  const navigate = useNavigate()
  const canManage = useCan('services.manage')
  const params = useTableParams({
    defaultSort: 'name',
    filterKeys: ['type', 'provider', 'active', 'organization'],
    organizationContext: true,
  })
  const list = useServices(params.apiParams)
  const providers = useProviders()
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title={t('services.title')}
        description={t('services.description')}
        actions={
          canManage && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
              {t('services.add')}
            </Button>
          )
        }
      />
      <DataTable<ItService>
        storeKey="it-services"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('services.empty')}
        onRowClick={(sv) => navigate(`/services/${sv.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('services.search')}
              aria-label={t('services.search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 240 }}
            />
            <Select
              placeholder={t('services.type')}
              aria-label={t('services.type')}
              clearable
              data={SERVICE_TYPES.map((v) => ({ value: v, label: t(`serviceType.${v}`) }))}
              value={params.filters.type ?? null}
              onChange={(v) => params.setFilter('type', v)}
              w={{ base: '100%', sm: 200 }}
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
          </Group>
        }
        columns={[
          {
            accessor: 'name',
            title: t('services.name'),
            render: (sv) => (
              <Stack gap={0}>
                <Text size="sm" fw={500} c={sv.is_active ? undefined : 'dimmed'}>
                  {sv.name}
                </Text>
                <Text size="xs" c="dimmed">
                  {[t(`serviceType.${sv.type}`), sv.identifier, sv.is_active ? null : t('services.inactive')]
                    .filter(Boolean)
                    .join(', ')}
                </Text>
              </Stack>
            ),
          },
          { accessor: 'provider', title: t('fields.provider'), render: (sv) => sv.provider.name },
          { accessor: 'organization', title: t('fields.organization'), render: (sv) => sv.organization.name },
          { accessor: 'tariff', title: t('services.tariff'), render: (sv) => sv.tariff ?? '—' },
          {
            accessor: 'fee',
            title: t('services.fee'),
            textAlign: 'right',
            render: (sv) => (
              <Stack gap={0} align="flex-end">
                <Text size="sm">{formatMoney(sv.fee)}</Text>
                <Text size="xs" c="dimmed">
                  {t(`billingCycle.${sv.billing_cycle}`)}
                </Text>
              </Stack>
            ),
          },
        ]}
      />
      {creating && <ServiceModal onClose={() => setCreating(false)} />}
    </>
  )
}
