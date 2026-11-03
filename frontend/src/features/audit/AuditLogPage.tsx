import { Drawer, Group, Select, SimpleGrid, Stack, Text, Title } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import type { AuditEntry } from '@/features/dashboard/api'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { listAuditLogs } from './api'
import { AuditDiff } from './AuditDiff'

const EVENTS = [
  'created',
  'updated',
  'deleted',
  'restored',
  'login',
  'logout',
  'login_failed',
  'password_changed',
  'password_reset',
  'roles_changed',
  'access_changed',
  'settings_updated',
  'role_permissions_changed',
]
const ENTITIES = [
  'organization',
  'department',
  'employee',
  'user',
  'role',
  'organization_it_responsible',
  'system_setting',
]

function Meta({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text size="sm" style={{ wordBreak: 'break-all' }}>
        {children || '—'}
      </Text>
    </div>
  )
}

export function AuditLogPage() {
  const { t } = useTranslation(['audit', 'common'])
  const params = useTableParams({
    defaultSort: '-created_at',
    filterKeys: ['event', 'auditable_type', 'organization', 'created_from', 'created_to'],
    organizationContext: true,
  })
  const list = useQuery({
    queryKey: ['audit', params.apiParams],
    queryFn: () => listAuditLogs(params.apiParams),
    placeholderData: keepPreviousData,
  })
  const [selected, setSelected] = useState<AuditEntry | null>(null)
  const entityLabel = (type: string | null) =>
    type ? t(`common:entities.${type}`, { defaultValue: type }) : '—'

  const toolbar = (
    <>
      <Select
        aria-label={t('filters.event')}
        placeholder={t('filters.allEvents')}
        data={EVENTS.map((e) => ({ value: e, label: t(`audit:events.${e}`, { defaultValue: e }) }))}
        value={params.filters.event ?? null}
        onChange={(v) => params.setFilter('event', v)}
        clearable
        searchable
        w={{ base: '100%', sm: 220 }}
      />
      <Select
        aria-label={t('filters.entity')}
        placeholder={t('filters.allEntities')}
        data={ENTITIES.map((e) => ({ value: e, label: entityLabel(e) }))}
        value={params.filters.auditable_type ?? null}
        onChange={(v) => params.setFilter('auditable_type', v)}
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
      <DatePickerInput
        type="range"
        aria-label={t('filters.period')}
        placeholder={t('filters.period')}
        valueFormat="DD.MM.YYYY"
        clearable
        allowSingleDateInRange
        value={[params.filters.created_from ?? null, params.filters.created_to ?? null]}
        onChange={([from, to]) => {
          params.setFilter('created_from', from)
          params.setFilter('created_to', to)
        }}
        w={{ base: '100%', sm: 240 }}
      />
    </>
  )

  return (
    <>
      <PageHeader title={t('title')} description={t('description')} />
      <DataTable<AuditEntry>
        storeKey="audit-table"
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
        onRowClick={setSelected}
        columns={[
          {
            accessor: 'created_at',
            title: t('fields.created_at'),
            sortable: true,
            noWrap: true,
            render: (e) => formatDateTime(e.created_at),
          },
          { accessor: 'event', title: t('fields.event'), render: (e) => e.event_label },
          {
            accessor: 'auditable_type',
            title: t('fields.entity'),
            render: (e) => (e.auditable_type ? `${entityLabel(e.auditable_type)} #${e.auditable_id}` : '—'),
          },
          {
            accessor: 'user',
            title: t('fields.user'),
            render: (e) => e.user?.username ?? e.user_label ?? t('system'),
          },
          {
            accessor: 'organization',
            title: t('fields.organization'),
            render: (e) => e.organization?.name ?? '—',
          },
          {
            accessor: 'ip_address',
            title: t('fields.ip'),
            noWrap: true,
            render: (e) => (
              <Text size="sm" ff="monospace">
                {e.ip_address ?? '—'}
              </Text>
            ),
          },
        ]}
      />
      <Drawer
        opened={selected !== null}
        onClose={() => setSelected(null)}
        title={<Text fw={600}>{t('detail.title')}</Text>}
        size="xl"
      >
        {selected && (
          <Stack gap="lg">
            <Group gap="xs">
              <Text fw={600}>{selected.event_label}</Text>
              <Text c="dimmed">{formatDateTime(selected.created_at)}</Text>
            </Group>
            <SimpleGrid cols={{ base: 1, sm: 2 }}>
              <Meta label={t('fields.entity')}>
                {selected.auditable_type
                  ? `${entityLabel(selected.auditable_type)} #${selected.auditable_id}`
                  : null}
              </Meta>
              <Meta label={t('fields.user')}>
                {selected.user?.username ?? selected.user_label ?? t('system')}
              </Meta>
              <Meta label={t('fields.organization')}>{selected.organization?.name}</Meta>
            </SimpleGrid>
            <div>
              <Title order={3} size="h5" mb="xs">
                {t('detail.changes')}
              </Title>
              <AuditDiff oldValues={selected.old_values} newValues={selected.new_values} />
            </div>
            <div>
              <Title order={3} size="h5" mb="xs">
                {t('detail.context')}
              </Title>
              <SimpleGrid cols={{ base: 1, sm: 2 }}>
                <Meta label={t('fields.ip')}>{selected.ip_address}</Meta>
                <Meta label={t('fields.url')}>
                  {selected.http_method ? `${selected.http_method} ${selected.url ?? ''}` : null}
                </Meta>
                <Meta label={t('fields.requestId')}>{selected.request_id}</Meta>
                <Meta label={t('fields.userAgent')}>{selected.user_agent}</Meta>
              </SimpleGrid>
            </div>
          </Stack>
        )}
      </Drawer>
    </>
  )
}
