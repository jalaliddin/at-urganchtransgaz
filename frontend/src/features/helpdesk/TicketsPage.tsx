import {
  Anchor,
  Button,
  Checkbox,
  Group,
  SegmentedControl,
  Select,
  Stack,
  Text,
  TextInput,
} from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconChartBar, IconPlus, IconSearch, IconSettings } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { useCategories, useTickets } from './queries'
import { PriorityBadge, SlaBadge, TicketStatusBadge } from './TicketBadges'
import { OPEN_STATUSES, TICKET_PRIORITIES, TICKET_STATUSES, type TicketListItem } from './types'

type Tab = 'mine' | 'assigned' | 'all'

const FILTER_KEYS = [
  'status',
  'priority',
  'category',
  'organization',
  'overdue',
  'requester',
  'assignee',
  'created_from',
  'created_to',
]

export function TicketsPage() {
  const { t } = useTranslation(['helpdesk', 'common'])
  const navigate = useNavigate()
  const canViewAll = useCan('helpdesk.view')
  const canProcess = useCan('helpdesk.process')
  const canCreate = useCan('helpdesk.create')
  const canExport = useCan('helpdesk.export')
  const canManage = useCan('helpdesk.manage')
  const params = useTableParams({
    defaultSort: '-created_at',
    filterKeys: FILTER_KEYS,
    organizationContext: canViewAll,
  })
  const categories = useCategories()
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)

  const tab: Tab =
    params.filters.requester === 'me'
      ? 'mine'
      : params.filters.assignee === 'me'
        ? 'assigned'
        : canViewAll
          ? 'all'
          : 'mine'
  // Without helpdesk.view the API returns only the user's own tickets anyway.
  const list = useTickets(params.apiParams)

  const tabs = [
    { value: 'mine', label: t('tabs.mine') },
    ...(canProcess ? [{ value: 'assigned', label: t('tabs.assigned') }] : []),
    ...(canViewAll ? [{ value: 'all', label: t('tabs.all') }] : []),
  ]
  const openOnly = params.filters.status === OPEN_STATUSES.join(',')

  const exportFilters: Record<string, string> = { ...params.filters }
  if (params.search) exportFilters.search = params.search
  const orgParam = params.apiParams['filter[organization]']
  if (orgParam !== undefined) exportFilters.organization = String(orgParam)

  const toolbar = (
    <Stack gap="sm" w="100%">
      {tabs.length > 1 && (
        <SegmentedControl
          aria-label={t('title')}
          value={tab}
          data={tabs}
          onChange={(value) =>
            params.setFilters({
              requester: value === 'mine' ? 'me' : null,
              assignee: value === 'assigned' ? 'me' : null,
            })
          }
          w={{ base: '100%', sm: 'auto' }}
          style={{ alignSelf: 'flex-start' }}
        />
      )}
      <Group gap="sm" wrap="wrap">
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
          aria-label={t('filters.status')}
          placeholder={t('filters.allStatuses')}
          data={TICKET_STATUSES.map((s) => ({ value: s, label: t(`status.${s}`) }))}
          value={params.filters.status && !openOnly ? params.filters.status : null}
          onChange={(value) => params.setFilter('status', value)}
          clearable
          w={{ base: '100%', sm: 200 }}
        />
        <Select
          aria-label={t('filters.priority')}
          placeholder={t('filters.priority')}
          data={TICKET_PRIORITIES.map((p) => ({ value: p, label: t(`priority.${p}`) }))}
          value={params.filters.priority ?? null}
          onChange={(value) => params.setFilter('priority', value)}
          clearable
          w={{ base: '100%', sm: 160 }}
        />
        <Select
          aria-label={t('filters.category')}
          placeholder={t('filters.category')}
          data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
          value={params.filters.category ?? null}
          onChange={(value) => params.setFilter('category', value)}
          clearable
          searchable
          w={{ base: '100%', sm: 200 }}
        />
        {canViewAll && (
          <OrganizationSelect
            aria-label={t('filters.organization')}
            placeholder={t('filters.organization')}
            value={params.filters.organization ? Number(params.filters.organization) : null}
            onChange={(value) => params.setFilter('organization', value === null ? null : String(value))}
            w={{ base: '100%', sm: 220 }}
          />
        )}
        <Checkbox
          label={t('filters.openOnly')}
          checked={openOnly}
          onChange={(e) =>
            params.setFilter('status', e.currentTarget.checked ? OPEN_STATUSES.join(',') : null)
          }
        />
        <Checkbox
          label={t('filters.overdue')}
          checked={params.filters.overdue === '1'}
          onChange={(e) => params.setFilter('overdue', e.currentTarget.checked ? '1' : null)}
        />
        {canExport && <ExportMenu type="helpdesk_tickets" filters={exportFilters} />}
      </Group>
    </Stack>
  )

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <Group gap="sm">
            {canViewAll && (
              <Button
                component={Link}
                to="/helpdesk/reports"
                variant="default"
                leftSection={<IconChartBar size={16} />}
              >
                {t('actions.reports')}
              </Button>
            )}
            {canManage && (
              <Button
                component={Link}
                to="/helpdesk/settings"
                variant="default"
                leftSection={<IconSettings size={16} />}
              >
                {t('actions.settings')}
              </Button>
            )}
            {canCreate && (
              <Button component={Link} to="/helpdesk/new" leftSection={<IconPlus size={16} />}>
                {t('actions.create')}
              </Button>
            )}
          </Group>
        }
      />
      <DataTable<TicketListItem>
        storeKey="helpdesk-tickets"
        params={params}
        toolbar={toolbar}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty.title')}
        emptyDescription={
          Object.keys(params.filters).length > 0 ? t('empty.filtered') : t('empty.description')
        }
        emptyAction={
          canCreate ? (
            <Button component={Link} to="/helpdesk/new" leftSection={<IconPlus size={16} />}>
              {t('actions.create')}
            </Button>
          ) : undefined
        }
        onRowClick={(ticket) => navigate(`/helpdesk/${ticket.id}`)}
        columns={[
          {
            accessor: 'number',
            title: t('fields.number'),
            sortable: true,
            noWrap: true,
            render: (ticket) => (
              <Anchor
                component={Link}
                to={`/helpdesk/${ticket.id}`}
                onClick={(e) => e.stopPropagation()}
                size="sm"
                ff="monospace"
                fw={500}
              >
                {ticket.number}
              </Anchor>
            ),
          },
          {
            accessor: 'title',
            title: t('fields.title'),
            render: (ticket) => (
              <Stack gap={0}>
                <Text size="sm" fw={500} lineClamp={1}>
                  {ticket.title}
                </Text>
                <Text size="xs" c="dimmed">
                  {ticket.category?.name ?? '—'}
                  {ticket.requester.name ? ` · ${ticket.requester.name}` : ''}
                </Text>
              </Stack>
            ),
          },
          ...(canViewAll
            ? [
                {
                  accessor: 'organization',
                  title: t('fields.organization'),
                  render: (ticket: TicketListItem) => ticket.organization?.name ?? '—',
                },
              ]
            : []),
          {
            accessor: 'priority',
            title: t('fields.priority'),
            sortable: true,
            noWrap: true,
            render: (ticket) => <PriorityBadge priority={ticket.priority} />,
          },
          {
            accessor: 'status',
            title: t('fields.status'),
            sortable: true,
            noWrap: true,
            render: (ticket) => <TicketStatusBadge status={ticket.status} />,
          },
          {
            accessor: 'resolution_due_at',
            title: t('fields.sla'),
            sortable: true,
            noWrap: true,
            render: (ticket) => <SlaBadge ticket={ticket} />,
          },
          {
            accessor: 'assignee',
            title: t('fields.assignee'),
            noWrap: true,
            render: (ticket) => ticket.assignee?.name ?? '—',
          },
          {
            accessor: 'created_at',
            title: t('fields.createdAt'),
            sortable: true,
            noWrap: true,
            render: (ticket) => formatDateTime(ticket.created_at),
          },
        ]}
      />
    </>
  )
}
