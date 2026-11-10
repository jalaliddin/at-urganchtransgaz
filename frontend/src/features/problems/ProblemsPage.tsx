import { Alert, Button, Group, Select, Stack, Switch, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconRepeat, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useOrgContext } from '@/hooks/useOrgContext'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate } from '@/lib/format'
import { ProblemPriorityBadge, ProblemStatusBadge } from './ProblemBadges'
import { ProblemFormModal } from './ProblemFormModal'
import { useCandidates, useProblems } from './queries'
import { PROBLEM_PRIORITIES, PROBLEM_STATUSES, type Candidate, type ProblemRow } from './types'

/** Recurring tickets nobody covers yet; each can become a problem with those tickets linked (design R4). */
function Candidates({ onCreate }: { onCreate: (c: Candidate) => void }) {
  const { t } = useTranslation('problems')
  const canManage = useCan('problems.manage')
  const { organizationId } = useOrgContext()
  const query = useCandidates(organizationId ? { 'filter[organization]': organizationId } : {})
  const rows = query.data ?? []
  if (rows.length === 0) return null

  return (
    <Alert
      icon={<IconRepeat size={18} />}
      color="orange"
      variant="light"
      title={t('candidates.title')}
      mb="md"
    >
      <Stack gap={6}>
        <Text size="sm">{t('candidates.hint')}</Text>
        {rows.map((c) => (
          <Group key={`${c.category?.id}-${c.organization?.id}`} justify="space-between" wrap="wrap" gap="xs">
            <Text size="sm">
              {t('candidates.row', {
                category: c.category?.name ?? '—',
                organization: c.organization?.name ?? '—',
                count: c.count,
              })}
            </Text>
            {canManage && (
              <Button size="compact-xs" variant="light" color="orange" onClick={() => onCreate(c)}>
                {t('candidates.create')}
              </Button>
            )}
          </Group>
        ))}
      </Stack>
    </Alert>
  )
}

export function ProblemsPage() {
  const { t } = useTranslation(['problems', 'common'])
  const navigate = useNavigate()
  const canManage = useCan('problems.manage')
  const canExport = useCan('problems.export')
  const params = useTableParams({
    defaultSort: 'status',
    filterKeys: ['status', 'priority', 'open', 'organization'],
    organizationContext: true,
  })
  const list = useProblems(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState<Candidate | true | null>(null)

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <Group gap="sm">
            {canExport && <ExportMenu type="problems" filters={params.filters} />}
            {canManage && (
              <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
                {t('form.new')}
              </Button>
            )}
          </Group>
        }
      />
      <Candidates onCreate={setCreating} />
      <DataTable<ProblemRow>
        storeKey="problems"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty')}
        onRowClick={(p) => navigate(`/problems/${p.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('search')}
              aria-label={t('search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 240 }}
            />
            <Select
              placeholder={t('fields.status')}
              aria-label={t('fields.status')}
              clearable
              data={PROBLEM_STATUSES.map((v) => ({ value: v, label: t(`status.${v}`) }))}
              value={params.filters.status ?? null}
              onChange={(v) => params.setFilter('status', v)}
              w={{ base: '100%', sm: 180 }}
            />
            <Select
              placeholder={t('fields.priority')}
              aria-label={t('fields.priority')}
              clearable
              data={PROBLEM_PRIORITIES.map((v) => ({ value: v, label: t(`priority.${v}`) }))}
              value={params.filters.priority ?? null}
              onChange={(v) => params.setFilter('priority', v)}
              w={{ base: '100%', sm: 160 }}
            />
            <Switch
              label={t('openOnly')}
              checked={params.filters.open === '1'}
              onChange={(e) => params.setFilter('open', e.currentTarget.checked ? '1' : null)}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'number',
            title: t('fields.problem'),
            sortable: false,
            render: (p) => (
              <Stack gap={0}>
                <Text size="sm" fw={500}>
                  {p.title}
                </Text>
                <Text size="xs" c="dimmed">
                  <Text span ff="monospace" inherit>
                    {p.number}
                  </Text>
                  , {p.organization.name}
                </Text>
              </Stack>
            ),
          },
          { accessor: 'category', title: t('fields.category'), render: (p) => p.category?.name ?? '—' },
          {
            accessor: 'priority',
            title: t('fields.priority'),
            render: (p) => <ProblemPriorityBadge priority={p.priority} />,
          },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (p) => <ProblemStatusBadge status={p.status} />,
          },
          { accessor: 'assignee', title: t('fields.assignee'), render: (p) => p.assignee?.name ?? '—' },
          {
            accessor: 'tickets_count',
            title: t('fields.tickets'),
            textAlign: 'right',
            render: (p) => p.tickets_count,
          },
          {
            accessor: 'created_at',
            title: t('fields.createdAt'),
            noWrap: true,
            render: (p) => formatDate(p.created_at),
          },
        ]}
      />
      {creating && (
        <ProblemFormModal
          prefill={
            creating === true
              ? undefined
              : {
                  organizationId: creating.organization?.id ?? null,
                  categoryId: creating.category?.id ?? null,
                  title: t('candidates.defaultTitle', { category: creating.category?.name ?? '' }),
                  ticketIds: creating.ticket_ids,
                }
          }
          onClose={() => setCreating(null)}
        />
      )}
    </>
  )
}
